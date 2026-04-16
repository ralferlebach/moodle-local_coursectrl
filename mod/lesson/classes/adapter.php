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
 * Course Control Hub adapter for mod_lesson.
 *
 * @package    coursectrlmod_lesson
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace coursectrlmod_lesson;

use local_coursectrl\local\contract\abstract_activity_adapter;
use local_coursectrl\local\contract\shift_dates_executor;

/**
 * Activity adapter wrapping mod_lesson.
 */
class adapter extends abstract_activity_adapter {
    use shift_dates_executor;

    /**
     * Returns the frankenstyle component name of the wrapped module.
     *
     * @return string
     */
    public static function component(): string {
        return 'mod_lesson';
    }

    /**
     * Whether mod_lesson is installed and usable on this site.
     *
     * @return bool
     */
    public function is_available(): bool {
        $modules = \core_component::get_plugin_list('mod');
        return is_array($modules) && array_key_exists('lesson', $modules);
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
     * Field descriptors for bulk-editable lesson date fields.
     *
     * @return array
     */
    public function get_supported_fields(): array {
        return field_map::get_date_fields();
    }

    /**
     * Enumerate all lesson instances in a course.
     *
     * @param int   $courseid target course id.
     * @param array $filters  reserved for future use, currently ignored.
     * @return array keyed by cmid.
     */
    public function get_instances_for_course(int $courseid, array $filters = []): array {
        $result = [];
        $cms = get_coursemodules_in_course('lesson', $courseid);
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
     * Return a normalised description of a single lesson course module.
     *
     * @param int $cmid course module id.
     * @return array
     */
    public function describe_instance(int $cmid): array {
        global $DB;
        $cm = get_coursemodule_from_id('lesson', $cmid, 0, false, MUST_EXIST);
        $lessonrecord = $DB->get_record(
            'lesson',
            ['id' => $cm->instance],
            'id, name, available, deadline',
            MUST_EXIST
        );
        return [
            'cmid'       => (int)$cmid,
            'component'  => 'mod_lesson',
            'instanceid' => (int)$cm->instance,
            'name'       => (string)$lessonrecord->name,
            'visible'    => (bool)$cm->visible,
            'dates'      => $this->read_dates_from_record($lessonrecord),
        ];
    }

    /**
     * Refresh lesson calendar events for the affected course modules.
     *
     * @param int[] $cmids course module ids.
     * @return void
     */
    public function refresh_calendar_for_cmids(array $cmids): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/lesson/lib.php');
        $courseids = [];
        foreach ($cmids as $cmid) {
            try {
                $cm = get_coursemodule_from_id('lesson', (int)$cmid, 0, false, MUST_EXIST);
                $courseids[(int)$cm->course] = true;
            } catch (\Throwable $e) {
                continue;
            }
        }
        foreach (array_keys($courseids) as $courseid) {
            lesson_refresh_events($courseid);
        }
    }

    /**
     * Returns the database table name for the trait.
     *
     * @return string
     */
    protected function get_table_name(): string {
        return 'lesson';
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
        return 'id, name, available, deadline';
    }

    /**
     * Maps a {lesson} record to its date fields.
     *
     * @param \stdClass $record raw {lesson} record.
     * @return array<string, int>
     */
    protected function read_dates_from_record(\stdClass $record): array {
        return [
            'available' => (int)$record->available,
            'deadline' => (int)$record->deadline,
        ];
    }
}
