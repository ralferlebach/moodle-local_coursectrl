<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Course-wide bulk execute manager for the Course Control Hub bulk pipeline.
 *
 * Orchestrates the full execute path:
 *   1. Persist a batch row with status 'pending'.
 *   2. Open a delegated transaction.
 *   3. For each cmid, route to the responsible adapter, capture a snapshot
 *      via export_state(), persist the snapshot, then call execute_action.
 *   4. Persist a batch_item per cmid with the resulting status and result.
 *   5. Commit the transaction.
 *   6. Update the batch row status to 'executed' (or 'failed' if any
 *      adapter call returned errors at the batch level).
 *   7. Trigger refresh_calendar_for_cmids on each adapter that processed
 *      cmids successfully.
 *   8. Fire the batch_executed event.
 *
 * Capability gating is intentionally NOT performed here; it lives one
 * layer up in the external function wrapper and is checked against
 * 'local/coursectrl:bulkaction'. The batch_manager trusts its caller.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\manager;

use local_coursectrl\event\batch_executed;
use local_coursectrl\local\contract\activity_adapter;
use local_coursectrl\local\persistent\batch;
use local_coursectrl\local\persistent\batch_item;
use local_coursectrl\local\persistent\snapshot;

/**
 * Orchestrates course-wide bulk execute calls and rollback persistence.
 */
class batch_manager {
    /** @var registry Adapter registry used to look up activity adapters. */
    private registry $registry;

    /**
     * Constructor.
     *
     * @param registry|null $registry optional registry instance, mainly for
     *                                tests. When null, a fresh registry with
     *                                live discovery is created.
     */
    public function __construct(?registry $registry = null) {
        $this->registry = $registry ?? new registry();
    }

    /**
     * Returns the registry instance backing this manager.
     *
     * @return registry
     */
    public function get_registry(): registry {
        return $this->registry;
    }

    /**
     * Execute a course-wide bulk action.
     *
     * Returns the persisted batch row id. Inspect the batch_item rows
     * via get_records(['batchid' => $id]) for per-cmid outcomes.
     *
     * @param int    $courseid target course id.
     * @param string $action   canonical action identifier, e.g. 'shift_dates'.
     * @param array  $payload  action-specific parameters.
     * @param int[]  $cmids    target course module ids; empty means "all".
     * @param int    $userid   acting user id for audit purposes.
     * @return int batch id of the persisted batch row.
     */
    public function execute(
        int $courseid,
        string $action,
        array $payload,
        array $cmids,
        int $userid
    ): int {
        global $DB;

        if (empty($cmids)) {
            $cmids = $this->collect_supported_cmids_for_course($courseid);
        }

        $batch = $this->create_batch_row($courseid, $userid, $action, $payload);
        $batchid = (int)$batch->get('id');

        $byadapter = $this->group_cmids_by_adapter($cmids, $action);
        $hasanyfailure = false;
        $successfulbyadapter = [];
        $summary = [
            'total'   => count($cmids),
            'success' => 0,
            'noop'    => 0,
            'skipped' => 0,
            'error'   => 0,
        ];

        $transaction = $DB->start_delegated_transaction();
        try {
            foreach ($byadapter['skipped'] as $skip) {
                $reason = $skip['reason'] ?? 'no_adapter';
                // For no-adapter CMs, apply system-level CM field shifts instead
                // of simply skipping them. These CMs can still have completionexpected
                // and availability date conditions that should be shifted.
                if ($reason === 'no_adapter' && $action === 'shift_dates') {
                    $delta = (int) ($payload['delta'] ?? 0);
                    $syscmid = (int) $skip['cmid'];
                    if ($delta !== 0) {
                        $this->shift_cm_level_dates($syscmid, $delta, $batchid, $summary);
                    } else {
                        $this->persist_skipped_item($batchid, $syscmid, $skip);
                        $summary['skipped']++;
                    }
                } else {
                    $this->persist_skipped_item($batchid, (int)$skip['cmid'], $skip);
                    $summary['skipped']++;
                }
            }
            foreach ($byadapter['routed'] as $component => $entry) {
                /** @var activity_adapter $adapter */
                $adapter = $entry['adapter'];
                $adaptercmids = $entry['cmids'];
                $result = $adapter->execute_action($action, $payload, $adaptercmids, $userid);
                if (!empty($result['errors'])) {
                    $hasanyfailure = true;
                }
                $itemresults = $result['items'] ?? [];
                $successfulcmids = [];
                foreach ($itemresults as $item) {
                    $cmid = (int)$item['cmid'];
                    $status = (string)($item['status'] ?? 'failed');
                    if (in_array($status, ['ok', 'noop'], true) && !empty($item['snapshot'])) {
                        $this->persist_snapshot($batchid, $cmid, $component, $item['snapshot']);
                    }
                    $this->persist_executed_item($batchid, $cmid, $component, $status, $item);
                    if ($status === 'ok') {
                        $summary['success']++;
                        $successfulcmids[] = $cmid;
                    } else if ($status === 'noop') {
                        $summary['noop']++;
                    } else {
                        $summary['error']++;
                        $hasanyfailure = true;
                    }
                }
                if (!empty($successfulcmids)) {
                    $successfulbyadapter[$component] = [
                        'adapter' => $adapter,
                        'cmids'   => $successfulcmids,
                    ];
                }
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            $batch->set('status', batch::STATUS_FAILED);
            $batch->update();
            throw $e;
        }

        $batch->set('status', $hasanyfailure ? batch::STATUS_FAILED : batch::STATUS_EXECUTED);
        $batch->update();

        foreach ($successfulbyadapter as $entry) {
            try {
                $entry['adapter']->refresh_calendar_for_cmids($entry['cmids']);
            } catch (\Throwable $e) {
                debugging(
                    'Course Control Hub: calendar refresh failed for batch ' . $batchid . ': ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }

        $event = batch_executed::create([
            'context'  => \context_course::instance($courseid),
            'objectid' => $batchid,
            'userid'   => $userid,
            'courseid' => $courseid,
            'other'    => [
                'action'  => $action,
                'summary' => $summary,
            ],
        ]);
        $event->trigger();

        return $batchid;
    }

    /**
     * Persist the head batch row in pending status.
     *
     * @param int    $courseid target course id.
     * @param int    $userid   acting user id.
     * @param string $action   canonical action identifier.
     * @param array  $payload  action-specific parameters.
     * @return batch
     */
    private function create_batch_row(int $courseid, int $userid, string $action, array $payload): batch {
        $data = (object)[
            'courseid'    => $courseid,
            'userid'      => $userid,
            'action'      => $action,
            'payloadjson' => json_encode($payload),
        ];
        return (new batch(0, $data))->create();
    }

    /**
     * Persist a skipped batch_item row.
     *
     * @param int   $batchid parent batch id.
     * @param int   $cmid    course module id.
     * @param array $skip    skip descriptor with 'reason' and optional 'component'.
     * @return void
     */
    private function persist_skipped_item(int $batchid, int $cmid, array $skip): void {
        $data = (object)[
            'batchid'    => $batchid,
            'entitytype' => 'cm',
            'entityid'   => $cmid,
            'component'  => $skip['component'] ?? null,
            'status'     => batch_item::STATUS_SKIPPED,
            'resultjson' => json_encode(['reason' => $skip['reason'] ?? 'unknown']),
        ];
        (new batch_item(0, $data))->create();
    }

    /**
     * Persist a snapshot row for one cmid.
     *
     * @param int    $batchid   parent batch id.
     * @param int    $cmid      course module id.
     * @param string $component frankenstyle component name.
     * @param array  $state     snapshot payload.
     * @return void
     */
    private function persist_snapshot(int $batchid, int $cmid, string $component, array $state): void {
        $data = (object)[
            'batchid'    => $batchid,
            'entitytype' => 'cm',
            'entityid'   => $cmid,
            'component'  => $component,
            'statejson'  => json_encode($state),
        ];
        (new snapshot(0, $data))->create();
    }

    /**
     * Persist an executed batch_item row.
     *
     * @param int    $batchid   parent batch id.
     * @param int    $cmid      course module id.
     * @param string $component frankenstyle component name.
     * @param string $status    one of 'ok', 'noop', 'failed'.
     * @param array  $item      raw adapter item result.
     * @return void
     */
    private function persist_executed_item(
        int $batchid,
        int $cmid,
        string $component,
        string $status,
        array $item
    ): void {
        $itemstatus = ($status === 'ok' || $status === 'noop')
            ? batch_item::STATUS_SUCCESS
            : batch_item::STATUS_ERROR;
        $data = (object)[
            'batchid'    => $batchid,
            'entitytype' => 'cm',
            'entityid'   => $cmid,
            'component'  => $component,
            'status'     => $itemstatus,
            'resultjson' => json_encode($item),
        ];
        (new batch_item(0, $data))->create();
    }

    /**
     * Shift CM-level date fields for a single course module without an adapter.
     *
     * Handles the two system-level date fields that apply to ALL activity types:
     *   - completionexpected  in {course_modules}
     *   - Availability date conditions in {course_modules}.availability (JSON)
     *
     * Creates a batch_item and snapshot so the shift can be rolled back.
     *
     * @param int   $cmid    Course module id.
     * @param int   $delta   Seconds to shift (positive = forward).
     * @param int   $batchid Parent batch id.
     * @param array $summary Reference to summary counters.
     */
    private function shift_cm_level_dates(
        int $cmid,
        int $delta,
        int $batchid,
        array &$summary
    ): void {
        global $DB;

        $cm = $DB->get_record(
            'course_modules',
            ['id' => $cmid],
            'id, completionexpected, availability',
            IGNORE_MISSING
        );
        if (!$cm) {
            return;
        }

        $changed = [];
        $snapshot = [
            'completionexpected' => (int)$cm->completionexpected,
            'availability' => (string)($cm->availability ?? ''),
        ];

        $update = new \stdClass();
        $update->id = $cmid;

        // Shift completionexpected.
        if ((int)$cm->completionexpected > 0) {
            $update->completionexpected = (int)$cm->completionexpected + $delta;
            $changed[] = 'completionexpected';
        }

        // Shift date conditions within availability JSON.
        if (!empty($cm->availability)) {
            $newavail = $this->shift_availability_dates((string)$cm->availability, $delta);
            if ($newavail !== (string)$cm->availability) {
                $update->availability = $newavail;
                $changed[] = 'availability';
            }
        }

        if (empty($changed)) {
            // Nothing to shift — no batch_item needed.
            return;
        }

        try {
            $DB->update_record('course_modules', $update);
        } catch (\Throwable $e) {
            debugging(
                'local_coursectrl: shift_cm_level_dates failed for cmid ' . $cmid .
                ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return;
        }

        // Persist snapshot for rollback.
        $snapdata = (object)[
            'batchid'    => $batchid,
            'entitytype' => 'cm',
            'entityid'   => $cmid,
            'component'  => 'core_coursemodule',
            'statejson'  => json_encode($snapshot),
        ];
        (new \local_coursectrl\local\persistent\snapshot(0, $snapdata))->create();

        // Persist batch_item as success.
        $itemdata = (object)[
            'batchid'    => $batchid,
            'entitytype' => 'cm',
            'entityid'   => $cmid,
            'component'  => 'core_coursemodule',
            'status'     => batch_item::STATUS_SUCCESS,
            'resultjson' => json_encode(['changed' => $changed, 'delta' => $delta]),
        ];
        (new batch_item(0, $itemdata))->create();
        $summary['success']++;
    }

    /**
     * Shift all date timestamps within a Moodle availability JSON string.
     *
     * Walks the condition tree and adds $delta to every node with
     * type === 'date' and a 't' (timestamp) value greater than zero.
     *
     * @param string $json  Raw availability JSON from {course_modules}.availability.
     * @param int    $delta Seconds to add.
     * @return string Modified JSON string, or the original on parse failure.
     */
    private function shift_availability_dates(string $json, int $delta): string {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return $json;
        }
        $data = $this->walk_availability_node($data, $delta);
        $encoded = json_encode($data);
        return ($encoded !== false) ? $encoded : $json;
    }

    /**
     * Recursively walk an availability condition node and shift date timestamps.
     *
     * @param array $node  Decoded availability node.
     * @param int   $delta Seconds to shift.
     * @return array Modified node.
     */
    private function walk_availability_node(array $node, int $delta): array {
        if (($node['type'] ?? '') === 'date' && isset($node['t']) && (int)$node['t'] > 0) {
            $node['t'] = (int)$node['t'] + $delta;
            return $node;
        }
        if (isset($node['c']) && is_array($node['c'])) {
            foreach ($node['c'] as $i => $child) {
                if (is_array($child)) {
                    $node['c'][$i] = $this->walk_availability_node($child, $delta);
                }
            }
        }
        return $node;
    }

    /**
     * Group the input cmids by responsible adapter.
     *
     * @param int[]  $cmids  input cmid list.
     * @param string $action canonical action identifier.
     * @return array{routed: array, skipped: array}
     */
    private function group_cmids_by_adapter(array $cmids, string $action): array {
        $routed  = [];
        $skipped = [];
        foreach ($cmids as $rawcmid) {
            $cmid = (int)$rawcmid;
            $adapter = $this->registry->get_for_cmid($cmid);
            if ($adapter === null) {
                $skipped[] = [
                    'cmid'   => $cmid,
                    'reason' => 'no_adapter',
                ];
                continue;
            }
            if (!in_array($action, $adapter->get_supported_actions(), true)) {
                $skipped[] = [
                    'cmid'      => $cmid,
                    'component' => $adapter::component(),
                    'reason'    => 'unsupported_action',
                ];
                continue;
            }
            $component = $adapter::component();
            if (!isset($routed[$component])) {
                $routed[$component] = [
                    'adapter' => $adapter,
                    'cmids'   => [],
                ];
            }
            $routed[$component]['cmids'][] = $cmid;
        }
        return [
            'routed'  => $routed,
            'skipped' => $skipped,
        ];
    }

    /**
     * Collect every cmid in the course whose component has a registered adapter.
     *
     * @param int $courseid target course id.
     * @return int[]
     */
    private function collect_supported_cmids_for_course(int $courseid): array {
        $result = [];
        foreach ($this->registry->get_all() as $adapter) {
            $instances = $adapter->get_instances_for_course($courseid);
            foreach ($instances as $entry) {
                $result[] = (int)$entry['cmid'];
            }
        }
        sort($result, SORT_NUMERIC);
        return $result;
    }
}
