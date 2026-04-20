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
 * Course Control Hub adapter for mod_workshop.
 *
 * @package    coursectrlmod_workshop
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace coursectrlmod_workshop;

use local_coursectrl\local\contract\abstract_activity_adapter;
use local_coursectrl\local\contract\check_helper;
use local_coursectrl\local\contract\shift_dates_executor;

/**
 * Activity adapter wrapping mod_workshop.
 */
class adapter extends abstract_activity_adapter {
    use shift_dates_executor;
    use check_helper;

    /**
     * Returns the frankenstyle component name of the wrapped module.
     *
     * @return string
     */
    public static function component(): string {
        return 'mod_workshop';
    }

    /**
     * Whether mod_workshop is installed and usable on this site.
     *
     * @return bool
     */
    public function is_available(): bool {
        $modules = \core_component::get_plugin_list('mod');
        return is_array($modules) && array_key_exists('workshop', $modules);
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
     * Field descriptors for bulk-editable workshop date fields.
     *
     * @return array
     */
    public function get_supported_fields(): array {
        return field_map::get_date_fields();
    }

    /**
     * Enumerate all workshop instances in a course.
     *
     * @param int   $courseid target course id.
     * @param array $filters  reserved for future use, currently ignored.
     * @return array keyed by cmid.
     */
    public function get_instances_for_course(int $courseid, array $filters = []): array {
        $result = [];
        $cms = get_coursemodules_in_course('workshop', $courseid);
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
     * Return a normalised description of a single workshop course module.
     *
     * @param int $cmid course module id.
     * @return array
     */
    public function describe_instance(int $cmid): array {
        global $DB;
        $cm = get_coursemodule_from_id('workshop', $cmid, 0, false, MUST_EXIST);
        $workshoprecord = $DB->get_record(
            'workshop',
            ['id' => $cm->instance],
            'id, name, submissionstart, submissionend, assessmentstart, assessmentend',
            MUST_EXIST
        );
        return [
            'cmid'       => (int)$cmid,
            'component'  => 'mod_workshop',
            'instanceid' => (int)$cm->instance,
            'name'       => (string)$workshoprecord->name,
            'visible'    => (bool)$cm->visible,
            'dates'      => $this->read_dates_from_record($workshoprecord),
        ];
    }

    /**
     * Refresh workshop calendar events for the affected course modules.
     *
     * @param int[] $cmids course module ids.
     * @return void
     */
    public function refresh_calendar_for_cmids(array $cmids): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/workshop/lib.php');
        $courseids = [];
        foreach ($cmids as $cmid) {
            try {
                $cm = get_coursemodule_from_id('workshop', (int)$cmid, 0, false, MUST_EXIST);
                $courseids[(int)$cm->course] = true;
            } catch (\Throwable $e) {
                continue;
            }
        }
        foreach (array_keys($courseids) as $courseid) {
            workshop_refresh_events($courseid);
        }
    }

    /**
     * Returns the database table name for the trait.
     *
     * @return string
     */
    protected function get_table_name(): string {
        return 'workshop';
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
        return 'id, name, submissionstart, submissionend, assessmentstart, assessmentend';
    }

    /**
     * Maps a {workshop} record to its date fields.
     *
     * @param \stdClass $record raw {workshop} record.
     * @return array<string, int>
     */
    protected function read_dates_from_record(\stdClass $record): array {
        return [
            'submissionstart' => (int)$record->submissionstart,
            'submissionend' => (int)$record->submissionend,
            'assessmentstart' => (int)$record->assessmentstart,
            'assessmentend' => (int)$record->assessmentend,
        ];
    }

    /**
     * Run consistency checks on workshop instances.
     *
     * Checks R3 (process logic) and R7 (missing counterpart fields) rules
     * as defined in docs/rules.md.
     *
     * @param int[] $cmids   Course module ids to check.
     * @param array $profile Optional check profile (unused).
     * @return array Check result items.
     */
    public function run_checks(array $cmids, array $profile = []): array {
        $results = [];
        $plugin = 'coursectrlmod_workshop';
        $r7defaults = [
            'assessmentstart_without_assessmentend' => 'warning',
            'assessmentend_without_assessmentstart' => 'notice',
            'assessment_without_submissionend'      => 'warning',
        ];
        $records = $this->load_check_records(
            $cmids,
            'workshop',
            'id,
            name,
            submissionstart,
            submissionend,
            assessmentstart,
            assessmentend'
        );

        foreach ($records as $cmid => $rec) {
            $substart = (int)$rec->submissionstart;
            $subend = (int)$rec->submissionend;
            $assstart = (int)$rec->assessmentstart;
            $assend = (int)$rec->assessmentend;
            $name = $rec->name;
            // R3: submission phase ordering.
            if ($substart > 0 && $subend > 0 && $substart > $subend) {
                $results[] = $this->check_result(
                    $cmid,
                    $name,
                    'error',
                    'workshop_submissionstart_after_submissionend',
                    get_string('check_workshop_submissionstart_after_submissionend', 'local_coursectrl')
                );
            }
            // R3: assessment phase ordering.
            if ($assstart > 0 && $assend > 0 && $assstart > $assend) {
                $results[] = $this->check_result(
                    $cmid,
                    $name,
                    'error',
                    'workshop_assessmentstart_after_assessmentend',
                    get_string('check_workshop_assessmentstart_after_assessmentend', 'local_coursectrl')
                );
            }
            // R3: assessment must not start before submission ends.
            if ($subend > 0 && $assstart > 0 && $subend > $assstart) {
                $results[] = $this->check_result(
                    $cmid,
                    $name,
                    'error',
                    'workshop_submissionend_after_assessmentstart',
                    get_string('check_workshop_submissionend_after_assessmentstart', 'local_coursectrl')
                );
            }
            // R7: assessmentstart without assessmentend.
            $sev = $this->r7_severity($plugin, 'assessmentstart_without_assessmentend', $r7defaults);
            if ($sev && $assstart > 0 && $assend === 0) {
                $results[] = $this->check_result(
                    $cmid,
                    $name,
                    $sev,
                    'workshop_assessmentstart_without_assessmentend',
                    get_string('check_workshop_assessmentstart_without_assessmentend', 'local_coursectrl')
                );
            }
            // R7: assessmentend without assessmentstart.
            $sev = $this->r7_severity($plugin, 'assessmentend_without_assessmentstart', $r7defaults);
            if ($sev && $assend > 0 && $assstart === 0) {
                $results[] = $this->check_result(
                    $cmid,
                    $name,
                    $sev,
                    'workshop_assessmentend_without_assessmentstart',
                    get_string('check_workshop_assessmentend_without_assessmentstart', 'local_coursectrl')
                );
            }
            // R7: assessment phase defined but submissionend not set.
            $sev = $this->r7_severity($plugin, 'assessment_without_submissionend', $r7defaults);
            if ($sev && ($assstart > 0 || $assend > 0) && $subend === 0) {
                $results[] = $this->check_result(
                    $cmid,
                    $name,
                    $sev,
                    'workshop_assessment_without_submissionend',
                    get_string('check_workshop_assessment_without_submissionend', 'local_coursectrl')
                );
            }
        }
        return $results;
    }
}
