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
 * Course Control Hub adapter for mod_assign.
 *
 * Patch-026: adds refresh_calendar_for_cmids() override that delegates
 * to assign_refresh_events() so the bulk engine keeps mod_assign calendar
 * entries in sync after a successful execute_action call.
 *
 * @package    coursectrlmod_assign
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace coursectrlmod_assign;

use local_coursectrl\local\contract\abstract_activity_adapter;
use local_coursectrl\local\contract\shift_dates_executor;

/**
 * Activity adapter wrapping mod_assign.
 */
class adapter extends abstract_activity_adapter {
    use shift_dates_executor;

    /**
     * Returns the frankenstyle component name of the wrapped module.
     *
     * @return string
     */
    public static function component(): string {
        return 'mod_assign';
    }

    /**
     * Whether mod_assign is installed and usable on this site.
     *
     * @return bool
     */
    public function is_available(): bool {
        $modules = \core_component::get_plugin_list('mod');
        return is_array($modules) && array_key_exists('assign', $modules);
    }

    /**
     * Actions this adapter handles.
     *
     * @return string[]
     */
    public function get_supported_actions(): array {
        return ['shift_dates', 'unset_dates'];
    }

    /**
     * Field descriptors for bulk-editable assign fields.
     *
     * @return array
     */
    public function get_supported_fields(): array {
        return field_map::get_date_fields();
    }

    /**
     * Enumerate all assign instances in a course.
     *
     * @param int   $courseid target course id.
     * @param array $filters  reserved for future use, currently ignored.
     * @return array keyed by cmid.
     */
    public function get_instances_for_course(int $courseid, array $filters = []): array {
        $result = [];
        $cms = get_coursemodules_in_course('assign', $courseid);
        if (!is_array($cms)) {
            return $result;
        }
        foreach ($cms as $cm) {
            $result[(int)$cm->id] = [
                'cmid'       => (int)$cm->id,
                'instanceid' => (int)$cm->instance,
                'name'       => (string)$cm->name,
                'visible'    => (bool)$cm->visible,
                'sectionid'  => (int)$cm->section,
            ];
        }
        return $result;
    }

    /**
     * Return a normalised description of a single assign course module.
     *
     * @param int $cmid course module id.
     * @return array
     */
    public function describe_instance(int $cmid): array {
        global $DB;
        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
        $assign = $DB->get_record(
            'assign',
            ['id' => $cm->instance],
            $this->get_record_select_fields(),
            MUST_EXIST
        );
        return [
            'cmid'       => (int)$cmid,
            'component'  => 'mod_assign',
            'instanceid' => (int)$cm->instance,
            'name'       => (string)$assign->name,
            'visible'    => (bool)$cm->visible,
            'dates'      => $this->read_dates_from_record($assign),
        ];
    }

    /**
     * Refresh assign calendar events for the affected course modules.
     *
     * Delegates to assign_refresh_events() once per course (assign_refresh_events
     * is course-scoped, not cmid-scoped). Idempotent and safe to call repeatedly.
     *
     * @param int[] $cmids course module ids.
     * @return void
     */
    public function refresh_calendar_for_cmids(array $cmids): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/lib.php');
        $courseids = $this->collect_courseids_for_cmids($cmids, 'assign');
        foreach ($courseids as $courseid) {
            assign_refresh_events($courseid);
        }
    }

    /**
     * The primary deadline field for mod_assign is the due date.
     *
     * completionexpected is only shifted when duedate is actually shifted,
     * not when only the opening or cut-off date moves.
     *
     * @return string
     */
    public function get_completion_anchor_field(): string {
        return 'duedate';
    }

    /**
     * Returns the database table name for the trait.
     *
     * @return string
     */
    protected function get_table_name(): string {
        return 'assign';
    }

    /**
     * Returns the field_map class name for the trait.
     *
     * @return string
     */
    protected function get_field_map_class(): string {
        return field_map::class;
    }

    /**
     * Returns the SQL SELECT clause for describe_instance.
     *
     * @return string
     */
    protected function get_record_select_fields(): string {
        return 'id, name, duedate, allowsubmissionsfromdate, cutoffdate, gradingduedate';
    }

    /**
     * Maps a {assign} record to the four assign date fields.
     *
     * @param \stdClass $record raw {assign} record.
     * @return array<string, int>
     */
    protected function read_dates_from_record(\stdClass $record): array {
        return [
            'duedate'                  => (int)$record->duedate,
            'allowsubmissionsfromdate' => (int)$record->allowsubmissionsfromdate,
            'cutoffdate'               => (int)$record->cutoffdate,
            'gradingduedate'           => (int)$record->gradingduedate,
        ];
    }

    /**
     * Run consistency checks on assign instances.
     *
     * Detects invalid date orderings, e.g. allowsubmissionsfromdate after
     * duedate, which Moodle's own form rejects but bulk shifts can produce.
     *
     * @param int[] $cmids   Course module ids to check.
     * @param array $profile Optional check profile (unused).
     * @return array Check result items.
     */
    public function run_checks(array $cmids, array $profile = []): array {
        global $DB;
        $results = [];
        foreach ($cmids as $rawcmid) {
            $cmid = (int)$rawcmid;
            try {
                $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
                $assign = $DB->get_record(
                    'assign',
                    ['id' => $cm->instance],
                    'id, name, duedate, allowsubmissionsfromdate, cutoffdate',
                    MUST_EXIST
                );
            } catch (\Throwable $e) {
                continue;
            }

            $due = (int)$assign->duedate;
            $fromdate = (int)$assign->allowsubmissionsfromdate;
            $cutoff = (int)$assign->cutoffdate;

            // Opening date must not be after due date.
            if ($fromdate > 0 && $due > 0 && $fromdate > $due) {
                $results[] = [
                    'cmid' => $cmid,
                    'name' => $assign->name,
                    'severity' => 'error',
                    'code' => 'assign_from_after_due',
                    'message' => get_string(
                        'check_assign_from_after_due',
                        'local_coursectrl'
                    ),
                ];
            }

            // Opening date should not be after completionexpected.
            try {
                $cmobj = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
                $compexp = (int)$cmobj->completionexpected;
            } catch (\Throwable $e) {
                $compexp = 0;
            }
            if ($fromdate > 0 && $compexp > 0 && $fromdate > $compexp) {
                $results[] = [
                    'cmid' => $cmid,
                    'name' => $assign->name,
                    'severity' => 'warning',
                    'code' => 'assign_from_after_completionexpected',
                    'message' => get_string(
                        'check_assign_from_after_completionexpected',
                        'local_coursectrl'
                    ),
                ];
            }

            // Cut-off date should not be before due date.
            if ($cutoff > 0 && $due > 0 && $cutoff < $due) {
                $results[] = [
                    'cmid' => $cmid,
                    'name' => $assign->name,
                    'severity' => 'warning',
                    'code' => 'assign_cutoff_before_due',
                    'message' => get_string(
                        'check_assign_cutoff_before_due',
                        'local_coursectrl'
                    ),
                ];
            }

            // completionexpected should not be before allowsubmissionsfromdate.
            // The CM-level completionexpected field is in course_modules, not {assign}.
            $cmrec = $DB->get_record(
                'course_modules',
                ['id' => $cmid],
                'completionexpected',
                IGNORE_MISSING
            );
            $compexp = $cmrec ? (int)$cmrec->completionexpected : 0;
            if ($compexp > 0 && $fromdate > 0 && $compexp < $fromdate) {
                $results[] = [
                    'cmid' => $cmid,
                    'name' => $assign->name,
                    'severity' => 'warning',
                    'code' => 'assign_completionexpected_before_from',
                    'message' => get_string(
                        'check_assign_completionexpected_before_from',
                        'local_coursectrl'
                    ),
                ];
            }
        }
        return $results;
    }

    /**
     * Resolve the distinct course ids that contain the given cmids of the
     * specified module type.
     *
     * @param int[]  $cmids   course module ids.
     * @param string $modname module name (e.g. 'assign').
     * @return int[]
     */
    private function collect_courseids_for_cmids(array $cmids, string $modname): array {
        $result = [];
        foreach ($cmids as $cmid) {
            try {
                $cm = get_coursemodule_from_id($modname, (int)$cmid, 0, false, MUST_EXIST);
                $result[(int)$cm->course] = true;
            } catch (\Throwable $e) {
                continue;
            }
        }
        return array_keys($result);
    }
}
