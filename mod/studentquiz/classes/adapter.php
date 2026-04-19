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
 * Course Control Hub adapter for mod_studentquiz (third-party plugin).
 *
 * @package    coursectrlmod_studentquiz
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace coursectrlmod_studentquiz;

use local_coursectrl\local\contract\abstract_activity_adapter;
use local_coursectrl\local\contract\check_helper;
use local_coursectrl\local\contract\shift_dates_executor;

/**
 * Activity adapter wrapping mod_studentquiz.
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
        return 'mod_studentquiz';
    }

    /**
     * Whether mod_studentquiz is installed and usable on this site.
     *
     * @return bool
     */
    public function is_available(): bool {
        $modules = \core_component::get_plugin_list('mod');
        return is_array($modules) && array_key_exists('studentquiz', $modules);
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
     * Field descriptors for bulk-editable studentquiz date fields.
     *
     * @return array
     */
    public function get_supported_fields(): array {
        return field_map::get_date_fields();
    }

    /**
     * Enumerate all studentquiz instances in a course.
     *
     * @param int   $courseid target course id.
     * @param array $filters  reserved for future use, currently ignored.
     * @return array keyed by cmid.
     */
    public function get_instances_for_course(int $courseid, array $filters = []): array {
        $result = [];
        $cms = get_coursemodules_in_course('studentquiz', $courseid);
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
     * Return a normalised description of a single studentquiz course module.
     *
     * @param int $cmid course module id.
     * @return array
     */
    public function describe_instance(int $cmid): array {
        global $DB;
        $cm = get_coursemodule_from_id('studentquiz', $cmid, 0, false, MUST_EXIST);
        $studentquizrecord = $DB->get_record(
            'studentquiz',
            ['id' => $cm->instance],
            'id, name, opensubmissionfrom, closesubmissionfrom, openansweringfrom, closeansweringfrom',
            MUST_EXIST
        );
        return [
            'cmid'       => (int)$cmid,
            'component'  => 'mod_studentquiz',
            'instanceid' => (int)$cm->instance,
            'name'       => (string)$studentquizrecord->name,
            'visible'    => (bool)$cm->visible,
            'dates'      => $this->read_dates_from_record($studentquizrecord),
        ];
    }

    /**
     * Returns the database table name for the trait.
     *
     * @return string
     */
    protected function get_table_name(): string {
        return 'studentquiz';
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
        return 'id, name, opensubmissionfrom, closesubmissionfrom, openansweringfrom, closeansweringfrom';
    }

    /**
     * Maps a {studentquiz} record to its date fields.
     *
     * @param \stdClass $record raw {studentquiz} record.
     * @return array<string, int>
     */
    protected function read_dates_from_record(\stdClass $record): array {
        return [
            'opensubmissionfrom' => (int)$record->opensubmissionfrom,
            'closesubmissionfrom' => (int)$record->closesubmissionfrom,
            'openansweringfrom' => (int)$record->openansweringfrom,
            'closeansweringfrom' => (int)$record->closeansweringfrom,
        ];
    }

    /**
     * Run consistency checks on studentquiz instances.
     *
     * Checks R3 (process logic) rules as defined in docs/rules.md.
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
                $cm = get_coursemodule_from_id('studentquiz', $cmid, 0, false, MUST_EXIST);
                $rec = $DB->get_record(
                    'studentquiz',
                    ['id' => $cm->instance],
                    'id, name, opensubmissionfrom, closesubmissionfrom, openansweringfrom, closeansweringfrom',
                    MUST_EXIST
                );
            } catch (\Throwable $e) {
                continue;
            }
            $opsubm = (int)$rec->opensubmissionfrom;
            $clsubm = (int)$rec->closesubmissionfrom;
            $opans = (int)$rec->openansweringfrom;
            $clans = (int)$rec->closeansweringfrom;
            $name = $rec->name;
            // R3: submission phase ordering.
            if ($opsubm > 0 && $clsubm > 0 && $opsubm > $clsubm) {
                $results[] = $this->check_result(
                    $cmid,
                    $name,
                    'error',
                    'studentquiz_submissionopen_after_close',
                    get_string('check_studentquiz_submissionopen_after_close', 'local_coursectrl')
                );
            }
            // R3: answering phase ordering.
            if ($opans > 0 && $clans > 0 && $opans > $clans) {
                $results[] = $this->check_result(
                    $cmid,
                    $name,
                    'error',
                    'studentquiz_answeringopen_after_close',
                    get_string('check_studentquiz_answeringopen_after_close', 'local_coursectrl')
                );
            }
            // R3: submission must close before answering opens.
            if ($clsubm > 0 && $opans > 0 && $clsubm > $opans) {
                $results[] = $this->check_result(
                    $cmid,
                    $name,
                    'error',
                    'studentquiz_submissionclose_after_answeringopen',
                    get_string('check_studentquiz_submissionclose_after_answeringopen', 'local_coursectrl')
                );
            }
        }
        return $results;
    }
}
