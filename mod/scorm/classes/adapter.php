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
 * Course Control Hub adapter for mod_scorm.
 *
 * @package    coursectrlmod_scorm
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace coursectrlmod_scorm;

use local_coursectrl\local\contract\abstract_activity_adapter;
use local_coursectrl\local\contract\check_helper;
use local_coursectrl\local\contract\shift_dates_executor;

/**
 * Activity adapter wrapping mod_scorm.
 */
class adapter extends abstract_activity_adapter {
    use check_helper;
    use shift_dates_executor;

    /**
     * Returns the frankenstyle component name of the wrapped module.
     *
     * @return string
     */
    public static function component(): string {
        return 'mod_scorm';
    }

    /**
     * Whether mod_scorm is installed and usable on this site.
     *
     * @return bool
     */
    public function is_available(): bool {
        $modules = \core_component::get_plugin_list('mod');
        return is_array($modules) && array_key_exists('scorm', $modules);
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
     * Field descriptors for bulk-editable scorm fields.
     *
     * @return array
     */
    public function get_supported_fields(): array {
        return field_map::get_date_fields();
    }

    /**
     * Enumerate all scorm instances in a course.
     *
     * @param int   $courseid Target course id.
     * @param array $filters  Reserved for future use.
     * @return array Keyed by cmid.
     */
    public function get_instances_for_course(int $courseid, array $filters = []): array {
        $result = [];
        $cms = get_coursemodules_in_course('scorm', $courseid);
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
     * Return a normalised description of a single scorm course module.
     *
     * @param int $cmid Course module id.
     * @return array
     */
    public function describe_instance(int $cmid): array {
        global $DB;
        $cm = get_coursemodule_from_id('scorm', $cmid, 0, false, MUST_EXIST);
        $scorm = $DB->get_record(
            'scorm',
            ['id' => $cm->instance],
            $this->get_record_select_fields(),
            MUST_EXIST
        );
        return [
            'cmid'       => (int)$cmid,
            'component'  => 'mod_scorm',
            'instanceid' => (int)$cm->instance,
            'name'       => (string)$scorm->name,
            'visible'    => (bool)$cm->visible,
            'dates'      => $this->read_dates_from_record($scorm),
        ];
    }

    /**
     * Run consistency checks on scorm instances.
     *
     * Detects timeopen after timeclose.
     *
     * @param int[] $cmids   Course module ids to check.
     * @param array $profile Optional check profile (unused).
     * @return array Check result items.
     */
    public function run_checks(array $cmids, array $profile = []): array {
        $results = [];
        $records = $this->load_check_records($cmids, 'scorm', 'id, name, timeopen, timeclose');
        foreach ($records as $cmid => $scorm) {
            $open = (int)$scorm->timeopen;
            $close = (int)$scorm->timeclose;
            if ($open > 0 && $close > 0 && $open > $close) {
                $results[] = [
                    'cmid'     => $cmid,
                    'name'     => $scorm->name,
                    'severity' => 'error',
                    'code'     => 'scorm_open_after_close',
                    'message'  => get_string('check_scorm_open_after_close', 'local_coursectrl'),
                ];
            }
            // R7: timeopen set without timeclose.
            $plugin = 'coursectrlmod_scorm';
            $r7defaults = ['timeopen_without_timeclose' => 'notice'];
            $sev = $this->r7_severity($plugin, 'timeopen_without_timeclose', $r7defaults);
            if ($sev && $open > 0 && $close === 0) {
                $results[] = $this->check_result(
                    $cmid,
                    $scorm->name,
                    $sev,
                    'scorm_timeopen_without_timeclose',
                    get_string('check_scorm_timeopen_without_timeclose', 'local_coursectrl')
                );
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
        return 'scorm';
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
     * Maps a {scorm} record to its date fields.
     *
     * @param \stdClass $record Raw {scorm} record.
     * @return array<string, int>
     */
    protected function read_dates_from_record(\stdClass $record): array {
        return [
            'timeopen'  => (int)$record->timeopen,
            'timeclose' => (int)$record->timeclose,
        ];
    }
}
