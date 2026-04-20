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
 * Course Control Hub adapter for mod_forum.
 *
 * @package    coursectrlmod_forum
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace coursectrlmod_forum;

use local_coursectrl\local\contract\abstract_activity_adapter;
use local_coursectrl\local\contract\check_helper;
use local_coursectrl\local\contract\shift_dates_executor;

/**
 * Activity adapter wrapping mod_forum.
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
        return 'mod_forum';
    }

    /**
     * Whether mod_forum is installed and usable on this site.
     *
     * @return bool
     */
    public function is_available(): bool {
        $modules = \core_component::get_plugin_list('mod');
        return is_array($modules) && array_key_exists('forum', $modules);
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
     * Field descriptors for bulk-editable forum date fields.
     *
     * @return array
     */
    public function get_supported_fields(): array {
        return field_map::get_date_fields();
    }

    /**
     * Enumerate all forum instances in a course.
     *
     * @param int   $courseid target course id.
     * @param array $filters  reserved for future use, currently ignored.
     * @return array keyed by cmid.
     */
    public function get_instances_for_course(int $courseid, array $filters = []): array {
        $result = [];
        $cms = get_coursemodules_in_course('forum', $courseid);
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
     * Return a normalised description of a single forum course module.
     *
     * @param int $cmid course module id.
     * @return array
     */
    public function describe_instance(int $cmid): array {
        global $DB;
        $cm = get_coursemodule_from_id('forum', $cmid, 0, false, MUST_EXIST);
        $forumrecord = $DB->get_record(
            'forum',
            ['id' => $cm->instance],
            'id, name, cutoffdate, duedate, assesstimestart, assesstimefinish',
            MUST_EXIST
        );
        return [
            'cmid'       => (int)$cmid,
            'component'  => 'mod_forum',
            'instanceid' => (int)$cm->instance,
            'name'       => (string)$forumrecord->name,
            'visible'    => (bool)$cm->visible,
            'dates'      => $this->read_dates_from_record($forumrecord),
        ];
    }

    /**
     * Returns the database table name for the trait.
     *
     * @return string
     */
    protected function get_table_name(): string {
        return 'forum';
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
        return 'id, name, cutoffdate, duedate, assesstimestart, assesstimefinish';
    }

    /**
     * Maps a {forum} record to its date fields.
     *
     * @param \stdClass $record raw {forum} record.
     * @return array<string, int>
     */
    protected function read_dates_from_record(\stdClass $record): array {
        return [
            'cutoffdate'       => (int)$record->cutoffdate,
            'duedate'           => (int)$record->duedate,
            'assesstimestart'  => (int)$record->assesstimestart,
            'assesstimefinish' => (int)$record->assesstimefinish,
        ];
    }

    /**
     * Run consistency checks on forum instances.
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
        $plugin = 'coursectrlmod_forum';
        $r7defaults = [
            'duedate_without_cutoffdate' => 'warning',
            'cutoffdate_without_duedate' => 'notice',
        ];
        $records = $this->load_check_records($cmids, 'forum', 'id, name, duedate, cutoffdate, assesstimestart, assesstimefinish');
        foreach ($records as $cmid => $rec) {
            $due = (int)$rec->duedate;
            $cutoff = (int)$rec->cutoffdate;
            $assstart = (int)$rec->assesstimestart;
            $assfinish = (int)$rec->assesstimefinish;
            $name = $rec->name;
            // R3: assesstimestart must not be after assesstimefinish.
            if ($assstart > 0 && $assfinish > 0 && $assstart > $assfinish) {
                $results[] = $this->check_result(
                    $cmid,
                    $name,
                    'error',
                    'forum_assesstimestart_after_assesstimefinish',
                    get_string('check_forum_assesstimestart_after_assesstimefinish', 'local_coursectrl')
                );
            }
            // R3: when ratings time restriction is active, both fields are mandatory.
            if ($assstart > 0 && $assfinish === 0) {
                $results[] = $this->check_result(
                    $cmid,
                    $name,
                    'error',
                    'forum_assesstimestart_without_assesstimefinish',
                    get_string('check_forum_assesstimestart_without_assesstimefinish', 'local_coursectrl')
                );
            }
            if ($assfinish > 0 && $assstart === 0) {
                $results[] = $this->check_result(
                    $cmid,
                    $name,
                    'error',
                    'forum_assesstimefinish_without_assesstimestart',
                    get_string('check_forum_assesstimefinish_without_assesstimestart', 'local_coursectrl')
                );
            }
            // R3: assess window must not extend beyond duedate.
            if ($assfinish > 0 && $due > 0 && $assfinish > $due) {
                $results[] = $this->check_result(
                    $cmid,
                    $name,
                    'warning',
                    'forum_assesstimefinish_after_duedate',
                    get_string('check_forum_assesstimefinish_after_duedate', 'local_coursectrl')
                );
            }
            // R7: duedate without cutoffdate.
            $sev = $this->r7_severity($plugin, 'duedate_without_cutoffdate', $r7defaults);
            if ($sev && $due > 0 && $cutoff === 0) {
                $results[] = $this->check_result(
                    $cmid,
                    $name,
                    $sev,
                    'forum_duedate_without_cutoffdate',
                    get_string('check_forum_duedate_without_cutoffdate', 'local_coursectrl')
                );
            }
            // R7: cutoffdate without duedate.
            $sev = $this->r7_severity($plugin, 'cutoffdate_without_duedate', $r7defaults);
            if ($sev && $cutoff > 0 && $due === 0) {
                $results[] = $this->check_result(
                    $cmid,
                    $name,
                    $sev,
                    'forum_cutoffdate_without_duedate',
                    get_string('check_forum_cutoffdate_without_duedate', 'local_coursectrl')
                );
            }
        }
        return $results;
    }
}
