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
 *   3. For each adapter group, run the adapter's execute_action once and
 *      persist one snapshot and one batch_item per affected cmid.
 *   4. For cmids without an adapter on shift_dates, apply system-level
 *      shifts (completionexpected, availability date conditions).
 *   5. Commit the transaction.
 *   6. Update the batch row status to 'executed' or 'failed'.
 *   7. Trigger refresh_calendar_for_cmids on each adapter with successes.
 *   8. Fire the batch_executed event.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\manager;

use core\persistent;
use local_coursectrl\event\batch_created;
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
        } else {
            $requested = array_values(array_unique(array_map('intval', $cmids)));
            $cmids = $this->filter_cmids_to_course($courseid, $requested);
            if (count($cmids) !== count($requested)) {
                throw new \moodle_exception('invalidcmid', 'local_coursectrl');
            }
        }

        $batch = $this->create_batch_row($courseid, $userid, $action, $payload);
        $batchid = (int) $batch->get('id');

        $createdevent = batch_created::create([
            'context'  => \context_course::instance($courseid),
            'objectid' => $batchid,
            'userid'   => $userid,
            'courseid' => $courseid,
            'other'    => ['action' => $action],
        ]);
        $createdevent->trigger();

        $grouping = $this->registry->group_cmids_by_component($courseid, $cmids, $action);
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
            $this->process_skipped_cmids(
                $grouping['skipped'],
                $action,
                $payload,
                $batchid,
                $summary,
                $hasanyfailure
            );
            foreach ($grouping['routed'] as $component => $entry) {
                /** @var activity_adapter $adapter */
                $adapter = $entry['adapter'];
                $adaptercmids = $entry['cmids'];
                $result = $adapter->execute_action($action, $payload, $adaptercmids, $userid);
                if (!empty($result['errors'])) {
                    $hasanyfailure = true;
                }
                $successfulcmids = $this->persist_adapter_results(
                    $batchid,
                    $component,
                    $result['items'] ?? [],
                    $summary,
                    $hasanyfailure
                );
                // Persist core_coursemodule snapshots for completionexpected
                // captured by the executor before shifting. This enables
                // the rollback_manager to restore these CM-level fields
                // alongside the adapter's own field snapshots.
                foreach ($result['cm_snapshots'] ?? [] as $cmid => $state) {
                    $this->persist_snapshot(
                        $batchid,
                        (int) $cmid,
                        'core_coursemodule',
                        $state
                    );
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

        $this->refresh_calendars($successfulbyadapter, $batchid);

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
     * Process cmids for which no adapter is responsible.
     *
     * On shift_dates, the CM-level fields (completionexpected, availability
     * date conditions) are shifted directly. Everything else is persisted
     * as a skipped batch_item.
     *
     * @param array[] $skipped        Skip descriptors from registry.
     * @param string  $action         Canonical action identifier.
     * @param array   $payload        Action payload.
     * @param int     $batchid        Parent batch id.
     * @param array   $summary        Summary counters (by reference).
     * @param bool    $hasanyfailure  Failure flag (by reference).
     * @return void
     */
    private function process_skipped_cmids(
        array $skipped,
        string $action,
        array $payload,
        int $batchid,
        array &$summary,
        bool &$hasanyfailure
    ): void {
        foreach ($skipped as $skip) {
            $reason = $skip['reason'] ?? 'no_adapter';
            $cmid = (int) $skip['cmid'];
            if ($reason === 'no_adapter' && $action === 'shift_dates') {
                $delta = (int) ($payload['delta'] ?? 0);
                if ($delta !== 0) {
                    $this->shift_cm_level_dates($cmid, $delta, $batchid, $summary, $hasanyfailure);
                    continue;
                }
            }
            $this->persist_skipped_item($batchid, $cmid, $skip);
            $summary['skipped']++;
        }
    }

    /**
     * Persist snapshot and batch_item rows for the items returned by one adapter.
     *
     * @param int     $batchid       Parent batch id.
     * @param string  $component     Frankenstyle component name.
     * @param array[] $items         Items from adapter::execute_action().
     * @param array   $summary       Summary counters (by reference).
     * @param bool    $hasanyfailure Failure flag (by reference).
     * @return int[] Cmids reported as 'ok' (for calendar refresh).
     */
    private function persist_adapter_results(
        int $batchid,
        string $component,
        array $items,
        array &$summary,
        bool &$hasanyfailure
    ): array {
        $successfulcmids = [];
        foreach ($items as $item) {
            $cmid = (int) $item['cmid'];
            $status = (string) ($item['status'] ?? 'failed');
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
        return $successfulcmids;
    }

    /**
     * Trigger calendar refresh for every adapter that produced successes.
     *
     * Failures are swallowed with a debug message so a broken calendar
     * handler cannot abort an otherwise successful batch.
     *
     * @param array $successfulbyadapter Map of component -> {adapter, cmids}.
     * @param int $batchid Parent batch id (for debug output).
     * @return void
     */
    private function refresh_calendars(array $successfulbyadapter, int $batchid): void {
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
    }

    /**
     * Persist the head batch row in pending status.
     *
     * @param int $courseid target course id.
     * @param int $userid acting user id.
     * @param string $action canonical action identifier.
     * @param array $payload action-specific parameters.
     * @return batch
     */
    private function create_batch_row(int $courseid, int $userid, string $action, array $payload): batch {
        $data = (object) [
            'courseid'    => $courseid,
            'userid'      => $userid,
            'action'      => $action,
            'payloadjson' => json_encode($payload),
        ];
        return $this->persist_row(batch::class, $data);
    }

    /**
     * Persist a skipped batch_item row.
     *
     * @param int   $batchid Parent batch id.
     * @param int   $cmid    Course module id.
     * @param array $skip    Skip descriptor with 'reason' and optional 'component'.
     * @return void
     */
    private function persist_skipped_item(int $batchid, int $cmid, array $skip): void {
        $this->persist_row(batch_item::class, (object) [
            'batchid'    => $batchid,
            'entitytype' => 'cm',
            'entityid'   => $cmid,
            'component'  => $skip['component'] ?? null,
            'status'     => batch_item::STATUS_SKIPPED,
            'resultjson' => json_encode(['reason' => $skip['reason'] ?? 'unknown']),
        ]);
    }

    /**
     * Persist a snapshot row for one cmid.
     *
     * @param int    $batchid   Parent batch id.
     * @param int    $cmid      Course module id.
     * @param string $component Frankenstyle component name.
     * @param array  $state     Snapshot payload.
     * @return void
     */
    private function persist_snapshot(int $batchid, int $cmid, string $component, array $state): void {
        $this->persist_row(snapshot::class, (object) [
            'batchid'    => $batchid,
            'entitytype' => 'cm',
            'entityid'   => $cmid,
            'component'  => $component,
            'statejson'  => json_encode($state),
        ]);
    }

    /**
     * Persist an executed batch_item row.
     *
     * @param int $batchid Parent batch id.
     * @param int $cmid Course module id.
     * @param string $component Frankenstyle component name.
     * @param string $status One of 'ok', 'noop', 'failed'.
     * @param array $item Raw adapter item result.
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
        $this->persist_row(batch_item::class, (object) [
            'batchid'    => $batchid,
            'entitytype' => 'cm',
            'entityid'   => $cmid,
            'component'  => $component,
            'status'     => $itemstatus,
            'resultjson' => json_encode($item),
        ]);
    }

    /**
     * Create and persist a persistent row.
     *
     * Single helper for all persistent instantiations in this manager so the
     * (new class(0, $data))->create() pattern is written only once.
     *
     * @param string $class Persistent subclass name (must be a persistent subclass).
     * @param \stdClass                $data  Row data.
     * @return persistent The created instance.
     */
    private function persist_row(string $class, \stdClass $data): persistent {
        return (new $class(0, $data))->create();
    }

    /**
     * Shift CM-level date fields for a single course module without an adapter.
     *
     * Handles the two system-level date fields that apply to ALL activity types:
     *   - completionexpected  in {course_modules}
     *   - availability date conditions in {course_modules}.availability (JSON)
     *
     * Persists a batch_item and snapshot so the shift can be rolled back.
     *
     * @param int   $cmid          Course module id.
     * @param int   $delta         Seconds to shift (positive = forward).
     * @param int   $batchid       Parent batch id.
     * @param array $summary       Reference to summary counters.
     * @param bool  $hasanyfailure Failure flag (by reference).
     * @return void
     */
    private function shift_cm_level_dates(
        int $cmid,
        int $delta,
        int $batchid,
        array &$summary,
        bool &$hasanyfailure
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

        $snapshotstate = [
            'completionexpected' => (int) $cm->completionexpected,
            'availability'       => (string) ($cm->availability ?? ''),
        ];

        $update = new \stdClass();
        $update->id = $cmid;
        $changed = [];

        if ((int) $cm->completionexpected > 0) {
            $update->completionexpected = (int) $cm->completionexpected + $delta;
            $changed[] = 'completionexpected';
        }
        if (!empty($cm->availability)) {
            $newavail = $this->shift_availability_dates((string) $cm->availability, $delta);
            if ($newavail !== (string) $cm->availability) {
                $update->availability = $newavail;
                $changed[] = 'availability';
            }
        }

        if (empty($changed)) {
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
            $hasanyfailure = true;
            return;
        }

        $this->persist_snapshot($batchid, $cmid, 'core_coursemodule', $snapshotstate);
        $this->persist_row(batch_item::class, (object) [
            'batchid'    => $batchid,
            'entitytype' => 'cm',
            'entityid'   => $cmid,
            'component'  => 'core_coursemodule',
            'status'     => batch_item::STATUS_SUCCESS,
            'resultjson' => json_encode(['changed' => $changed, 'delta' => $delta]),
        ]);
        $summary['success']++;
    }

    /**
     * Shift all date timestamps within a Moodle availability JSON string.
     *
     * Walks the condition tree and adds $delta to every node with
     * type === 'date' and a 't' (timestamp) value greater than zero. No new
     * nodes are inserted; only existing timestamp values are rewritten.
     *
     * @param string $json Raw availability JSON from {course_modules}.availability.
     * @param int $delta Seconds to add.
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
        if (($node['type'] ?? '') === 'date' && isset($node['t']) && (int) $node['t'] > 0) {
            $node['t'] = (int) $node['t'] + $delta;
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
     * Collect every cmid in the course whose component has a registered adapter.
     *
     * @param int $courseid target course id.
     * @return int[]
     */
    /**
     * Return only course module ids that belong to the given course.
     *
     * Prevents cross-course injection by rejecting cmids that are not
     * owned by the requested course. deletioninprogress rows are also
     * excluded so callers never act on modules being removed.
     *
     * @param int   $courseid Course id to filter against.
     * @param int[] $cmids    Caller-supplied course module ids.
     * @return int[] Subset of $cmids that belong to $courseid.
     */
    private function filter_cmids_to_course(int $courseid, array $cmids): array {
        global $DB;
        $cmids = array_values(array_unique(array_map('intval', $cmids)));
        if (empty($cmids)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
        $params['courseid'] = $courseid;
        $validids = $DB->get_fieldset_select(
            'course_modules',
            'id',
            "course = :courseid AND deletioninprogress = 0 AND id {$insql}",
            $params
        );
        return array_values(array_map('intval', $validids));
    }

    /**
     * Collect every cmid in the course whose component has a registered adapter.
     *
     * Used as the default target set when execute() is called with an empty cmids list.
     *
     * @param int $courseid Target course id.
     * @return int[]
     */
    private function collect_supported_cmids_for_course(int $courseid): array {
        $result = [];
        foreach ($this->registry->get_all() as $adapter) {
            $instances = $adapter->get_instances_for_course($courseid);
            foreach ($instances as $entry) {
                $result[] = (int) $entry['cmid'];
            }
        }
        sort($result, SORT_NUMERIC);
        return $result;
    }
}
