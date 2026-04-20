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
 * Scheduled task: purge batch history records beyond the configured retention limits.
 *
 * Two complementary limits are applied in sequence during each run:
 *
 *   1. Age limit (history_maxdays, default 365):
 *      Any batch row whose timecreated is older than this many days is deleted,
 *      together with all its batch_item and snapshot child rows.
 *
 *   2. Per-course count limit (history_maxcount, default 100):
 *      After age purging, each course that still has more than history_maxcount
 *      batch rows retains only the most recent ones; the oldest excess rows are
 *      deleted along with their children.
 *
 * Child rows in local_coursectrl_batch_item and local_coursectrl_snapshot are
 * always deleted before their parent batch row so that referential integrity is
 * maintained regardless of the database engine's FK enforcement.
 *
 * Both limits can be disabled individually by setting the value to 0.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\task;

/**
 * Purges batch history records that exceed the configured retention limits.
 */
class purge_old_batches extends \core\task\scheduled_task {
    /**
     * Human-readable task name shown in the Moodle admin task list.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_purge_old_batches', 'local_coursectrl');
    }

    /**
     * Execute the purge task.
     *
     * Reads the two retention settings, applies the age limit globally,
     * then applies the per-course count limit. Emits mtrace() lines so
     * that cron output and admin/cli/scheduled_task.php show progress.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $maxdays = (int) (get_config('local_coursectrl', 'history_maxdays') ?: 365);
        $maxcount = (int) (get_config('local_coursectrl', 'history_maxcount') ?: 100);

        $deleted = 0;

        if ($maxdays > 0) {
            $deleted += $this->purge_by_age($DB, $maxdays);
        }

        if ($maxcount > 0) {
            $deleted += $this->purge_by_count($DB, $maxcount);
        }

        mtrace("local_coursectrl purge_old_batches: {$deleted} batch record(s) removed.");
    }

    /**
     * Delete all batches older than the given number of days.
     *
     * @param \moodle_database $db       Active DB connection.
     * @param int              $maxdays  Maximum age in days.
     * @return int Number of batch rows deleted.
     */
    private function purge_by_age(\moodle_database $db, int $maxdays): int {
        $cutoff = time() - ($maxdays * DAYSECS);
        $batchids = $db->get_fieldset_select(
            'local_coursectrl_batch',
            'id',
            'timecreated < :cutoff',
            ['cutoff' => $cutoff]
        );
        if (empty($batchids)) {
            return 0;
        }
        $this->delete_batches($db, $batchids);
        return count($batchids);
    }

    /**
     * For each course, delete batches beyond the per-course count limit.
     *
     * Keeps the most recent maxcount batches per course; deletes the rest.
     *
     * @param \moodle_database $db        Active DB connection.
     * @param int              $maxcount  Maximum number of batches to keep per course.
     * @return int Number of batch rows deleted.
     */
    private function purge_by_count(\moodle_database $db, int $maxcount): int {
        $courseids = $db->get_fieldset_sql(
            'SELECT DISTINCT courseid FROM {local_coursectrl_batch}',
            []
        );
        $deleted = 0;
        foreach ($courseids as $courseid) {
            $total = $db->count_records('local_coursectrl_batch', ['courseid' => $courseid]);
            if ($total <= $maxcount) {
                continue;
            }
            // Fetch the ids of all batches for this course, oldest first.
            $allids = $db->get_fieldset_select(
                'local_coursectrl_batch',
                'id',
                'courseid = :courseid',
                ['courseid' => $courseid],
                'timecreated ASC, id ASC'
            );
            // Remove the newest maxcount from the list; delete the rest.
            $excess = array_slice($allids, 0, count($allids) - $maxcount);
            if (empty($excess)) {
                continue;
            }
            $this->delete_batches($db, $excess);
            $deleted += count($excess);
        }
        return $deleted;
    }

    /**
     * Delete child rows then parent batch rows for the given batch ids.
     *
     * Children (batch_item, snapshot) are deleted first to maintain
     * referential integrity regardless of FK enforcement in the DB engine.
     *
     * @param \moodle_database $db       Active DB connection.
     * @param int[]            $batchids Batch ids to remove.
     * @return void
     */
    private function delete_batches(\moodle_database $db, array $batchids): void {
        [$insql, $inparams] = $db->get_in_or_equal($batchids, SQL_PARAMS_NAMED);
        $db->delete_records_select('local_coursectrl_snapshot', "batchid {$insql}", $inparams);
        $db->delete_records_select('local_coursectrl_batch_item', "batchid {$insql}", $inparams);
        $db->delete_records_select('local_coursectrl_batch', "id {$insql}", $inparams);
    }
}
