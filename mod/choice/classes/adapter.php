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
 * Course Control Hub adapter for mod_choice.
 *
 * @package    coursectrlmod_choice
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace coursectrlmod_choice;

use local_coursectrl\local\contract\abstract_activity_adapter;
use local_coursectrl\local\contract\shift_dates_executor;

/**
 * Activity adapter wrapping mod_choice.
 */
class adapter extends abstract_activity_adapter {
    use shift_dates_executor;

    /**
     * Returns the frankenstyle component name of the wrapped module.
     *
     * @return string
     */
    public static function component(): string {
        return 'mod_choice';
    }

    /**
     * Whether mod_choice is installed and usable on this site.
     *
     * @return bool
     */
    public function is_available(): bool {
        $modules = \core_component::get_plugin_list('mod');
        return is_array($modules) && array_key_exists('choice', $modules);
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
     * Field descriptors for bulk-editable choice fields.
     *
     * @return array
     */
    public function get_supported_fields(): array {
        return field_map::get_date_fields();
    }

    /**
     * Enumerate all choice instances in a course.
     *
     * @param int   $courseid Target course id.
     * @param array $filters  Reserved for future use.
     * @return array Keyed by cmid.
     */
    public function get_instances_for_course(int $courseid, array $filters = []): array {
        $result = [];
        $cms = get_coursemodules_in_course('choice', $courseid);
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
     * Return a normalised description of a single choice course module.
     *
     * @param int $cmid Course module id.
     * @return array
     */
    public function describe_instance(int $cmid): array {
        global $DB;
        $cm = get_coursemodule_from_id('choice', $cmid, 0, false, MUST_EXIST);
        $choice = $DB->get_record(
            'choice',
            ['id' => $cm->instance],
            $this->get_record_select_fields(),
            MUST_EXIST
        );
        return [
            'cmid'       => (int)$cmid,
            'component'  => 'mod_choice',
            'instanceid' => (int)$cm->instance,
            'name'       => (string)$choice->name,
            'visible'    => (bool)$cm->visible,
            'dates'      => $this->read_dates_from_record($choice),
        ];
    }

    /**
     * Run consistency checks on choice instances.
     *
     * Detects timeopen after timeclose.
     *
     * @param int[] $cmids   Course module ids to check.
     * @param array $profile Optional check profile (unused).
     * @return array Check result items.
     */
    public function run_checks(array $cmids, array $profile = []): array {
        $results = [];
        $records = $this->load_check_records($cmids, 'choice', 'id, name, timeopen, timeclose');
        foreach ($records as $cmid => $choice) {
            $open = (int)$choice->timeopen;
            $close = (int)$choice->timeclose;
            if ($open > 0 && $close > 0 && $open > $close) {
                $results[] = [
                    'cmid'     => $cmid,
                    'name'     => $choice->name,
                    'severity' => 'error',
                    'code'     => 'choice_open_after_close',
                    'message'  => get_string('check_choice_open_after_close', 'local_coursectrl'),
                ];
            }
        }
        return $results;
    }

    /**
     * Returns the database table name for the trait.
     *
     * @return string
     */
    protected function get_table_name(): string {
        return 'choice';
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
        return 'id, name, timeopen, timeclose';
    }

    /**
     * Maps a {choice} record to its date fields.
     *
     * @param \stdClass $record Raw {choice} record.
     * @return array<string, int>
     */
    protected function read_dates_from_record(\stdClass $record): array {
        return [
            'timeopen'  => (int)$record->timeopen,
            'timeclose' => (int)$record->timeclose,
        ];
    }
}
