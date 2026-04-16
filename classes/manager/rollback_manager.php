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
 * Rollback manager for the Course Control Hub.
 *
 * Provides two public capabilities:
 *
 *   get_course_batches(courseid)
 *     Returns all batch head records for a course, newest first, enriched
 *     with a flag indicating whether rollback snapshots exist for the batch.
 *
 *   rollback_batch(batchid, userid)
 *     For every snapshot belonging to the batch, loads the saved state JSON
 *     and calls the appropriate adapter's restore_state() method inside a
 *     DB transaction. Updates the batch status to 'rolled_back' on success.
 *     Returns a structured result with per-entity outcomes.
 *
 * Only batches in status 'executed' can be rolled back; other statuses are
 * rejected with an error result so that the caller does not need to guard.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\manager;

use local_coursectrl\local\persistent\batch;
use local_coursectrl\local\persistent\batch_item;
use local_coursectrl\local\persistent\snapshot;

/**
 * Manages batch history retrieval and adapter-level state restoration.
 */
class rollback_manager {
    /** @var registry */
    private registry $registry;

    /**
     * Constructor.
     *
     * @param registry|null $registry Optional custom registry for DI.
     */
    public function __construct(?registry $registry = null) {
        $this->registry = $registry ?? new registry();
    }

    /**
     * Return all batch records for a course, newest first.
     *
     * Each record is a plain array enriched with:
     *   - has_snapshots (bool): whether this batch has any snapshot rows.
     *   - can_rollback (bool): batch is in status 'executed' AND has_snapshots.
     *   - itemcount (int): number of batch_item rows.
     *   - timecreated_formatted (string): localised creation time.
     *   - status_label (string): localised status string key.
     *
     * @param int $courseid Target course id.
     * @return array[] Batch records, newest first.
     */
    public function get_course_batches(int $courseid): array {
        global $DB;

        $batchrecords = $DB->get_records(
            'local_coursectrl_batch',
            ['courseid' => $courseid],
            'timecreated DESC'
        );

        if (empty($batchrecords)) {
            return [];
        }

        $batchids = array_keys($batchrecords);
        [$insql, $inparams] = $DB->get_in_or_equal($batchids, SQL_PARAMS_NAMED);

        // Count items per batch.
        $itemcounts = $DB->get_records_sql(
            "SELECT batchid, COUNT(id) AS cnt
               FROM {local_coursectrl_batch_item}
              WHERE batchid $insql
           GROUP BY batchid",
            $inparams
        );

        // Find batches that have at least one snapshot.
        $snapshotbatchids = $DB->get_fieldset_sql(
            "SELECT DISTINCT batchid
               FROM {local_coursectrl_snapshot}
              WHERE batchid $insql",
            $inparams
        );
        $hassnapshots = array_fill_keys($snapshotbatchids, true);

        $dateformat = get_string('strftimedaydatetime', 'core_langconfig');
        $result = [];
        foreach ($batchrecords as $rec) {
            $batchid = (int) $rec->id;
            $status = (string) $rec->status;
            $snaps = isset($hassnapshots[$batchid]);
            $result[] = [
                'id' => $batchid,
                'courseid' => (int) $rec->courseid,
                'userid' => (int) $rec->userid,
                'action' => (string) $rec->action,
                'status' => $status,
                'timecreated' => (int) $rec->timecreated,
                'timecreated_formatted' => userdate((int) $rec->timecreated, $dateformat),
                'itemcount' => (int) ($itemcounts[$batchid]->cnt ?? 0),
                'has_snapshots' => $snaps,
                'can_rollback' => $snaps && $status === batch::STATUS_EXECUTED,
            ];
        }
        return $result;
    }

    /**
     * Roll back all adapter-level changes recorded for a batch.
     *
     * For each snapshot belonging to $batchid, the saved state is decoded
     * and passed to the appropriate adapter's restore_state() method.
     * Everything runs inside a DB transaction; on failure the transaction
     * is rolled back and the batch status is unchanged.
     *
     * @param int $batchid Batch id to roll back.
     * @param int $userid  User performing the rollback (for audit).
     * @return array{
     *     success: bool,
     *     error: string,
     *     restored: int,
     *     failed: int,
     *     items: array
     * }
     */
    public function rollback_batch(int $batchid, int $userid): array {
        global $DB;

        // Load and validate the batch.
        $batchrecord = $DB->get_record('local_coursectrl_batch', ['id' => $batchid]);
        if (!$batchrecord) {
            return $this->error_result('batch_not_found');
        }
        if ($batchrecord->status !== batch::STATUS_EXECUTED) {
            return $this->error_result('batch_not_rollbackable');
        }

        $snapshots = $DB->get_records(
            'local_coursectrl_snapshot',
            ['batchid' => $batchid]
        );
        if (empty($snapshots)) {
            return $this->error_result('no_snapshots');
        }

        $items = [];
        $restored = 0;
        $failed = 0;

        $transaction = $DB->start_delegated_transaction();
        try {
            foreach ($snapshots as $snap) {
                $component = (string) $snap->component;
                $entityid = (int) $snap->entityid;
                $state = json_decode($snap->statejson, true);

                if (!is_array($state)) {
                    $items[] = [
                        'entityid' => $entityid,
                        'component' => $component,
                        'status' => 'error',
                        'message' => 'invalid_snapshot_json',
                    ];
                    $failed++;
                    continue;
                }

                $adapter = $this->registry->get_for_component($component);
                if ($adapter === null) {
                    $items[] = [
                        'entityid' => $entityid,
                        'component' => $component,
                        'status' => 'error',
                        'message' => 'no_adapter',
                    ];
                    $failed++;
                    continue;
                }

                try {
                    $adapterresult = $adapter->restore_state($state);
                    $items[] = [
                        'entityid' => $entityid,
                        'component' => $component,
                        'status' => 'restored',
                        'message' => $adapterresult['message'] ?? '',
                    ];
                    $restored++;
                } catch (\Throwable $e) {
                    $items[] = [
                        'entityid' => $entityid,
                        'component' => $component,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ];
                    $failed++;
                }
            }

            // Only mark rolled back if all items succeeded.
            if ($failed === 0) {
                $DB->set_field(
                    'local_coursectrl_batch',
                    'status',
                    batch::STATUS_ROLLED_BACK,
                    ['id' => $batchid]
                );
            }

            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            return $this->error_result($e->getMessage());
        }

        return [
            'success' => $failed === 0,
            'error' => '',
            'restored' => $restored,
            'failed' => $failed,
            'items' => $items,
        ];
    }

    /**
     * Build a standardised error result.
     *
     * @param string $message Error message or key.
     * @return array
     */
    private function error_result(string $message): array {
        return [
            'success' => false,
            'error' => $message,
            'restored' => 0,
            'failed' => 0,
            'items' => [],
        ];
    }
}
