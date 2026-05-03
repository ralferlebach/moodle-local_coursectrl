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
 * Two complementary limits are applied in sequence:
 *   1. Age limit (history_maxdays): deletes batches older than N days.
 *   2. Per-course count limit (history_maxcount): keeps only the most recent N per course.
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
     * Human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_purge_old_batches', 'local_coursectrl');
    }

    /**
     * Execute the purge task.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $maxdays  = (int)(get_config('local_coursectrl', 'history_maxdays') ?: 365);
        $maxcount = (int)(get_config('local_coursectrl', 'history_maxcount') ?: 100);
        $deleted  = 0;

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
     * @param \moodle_database $db      Active DB connection.
     * @param int              $maxdays Maximum age in days.
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
     * Uses a single subquery to find excess rows across all courses at once,
     * avoiding the N+1 per-course query pattern.
     *
     * Window functions (ROW_NUMBER) are available in MariaDB 10.2+, MySQL 8+ and
     * PostgreSQL. Falls back to the legacy per-course loop on older engines.
     *
     * @param \moodle_database $db       Active DB connection.
     * @param int              $maxcount Maximum number of batches to keep per course.
     * @return int Number of batch rows deleted.
     */
    private function purge_by_count(\moodle_database $db, int $maxcount): int {
        // Attempt single-pass bulk identification via ROW_NUMBER window function.
        // If the DB engine does not support it, fall back to the per-course loop.
        try {
            $sql = "SELECT id
                      FROM (
                        SELECT id,
                               ROW_NUMBER() OVER (
                                   PARTITION BY courseid
                                   ORDER BY timecreated DESC, id DESC
                               ) AS rn
                          FROM {local_coursectrl_batch}
                      ) ranked
                     WHERE rn > :maxcount";
            $excess = $db->get_fieldset_sql($sql, ['maxcount' => $maxcount]);
        } catch (\Throwable $e) {
            // Window functions unsupported — use the safe per-course fallback.
            debugging(
                'local_coursectrl purge_by_count: ROW_NUMBER unsupported, using fallback: ' .
                $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return $this->purge_by_count_legacy($db, $maxcount);
        }
        if (empty($excess)) {
            return 0;
        }
        $this->delete_batches($db, $excess);
        return count($excess);
    }

    /**
     * Legacy per-course fallback for engines without ROW_NUMBER support.
     *
     * @param \moodle_database $db       Active DB connection.
     * @param int              $maxcount Maximum number of batches to keep per course.
     * @return int Number of batch rows deleted.
     */
    private function purge_by_count_legacy(\moodle_database $db, int $maxcount): int {
        $courseids = $db->get_fieldset_sql(
            'SELECT DISTINCT courseid FROM {local_coursectrl_batch}',
            []
        );
        $deleted = 0;
        foreach ($courseids as $courseid) {
            $allids = $db->get_fieldset_select(
                'local_coursectrl_batch',
                'id',
                'courseid = :courseid',
                ['courseid' => $courseid],
                'timecreated ASC, id ASC'
            );
            $excess = array_slice($allids, 0, max(0, count($allids) - $maxcount));
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
