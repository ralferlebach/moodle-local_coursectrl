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
 * Course Control Hub adapter for mod_quiz.
 *
 * Patch-026: adds refresh_calendar_for_cmids() override that delegates
 * to quiz_refresh_events().
 *
 * @package    coursectrlmod_quiz
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace coursectrlmod_quiz;

use local_coursectrl\local\contract\abstract_activity_adapter;
use local_coursectrl\local\contract\check_helper;
use local_coursectrl\local\contract\shift_dates_executor;

/**
 * Activity adapter wrapping mod_quiz.
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
        return 'mod_quiz';
    }

    /**
     * Whether mod_quiz is installed and usable on this site.
     *
     * @return bool
     */
    public function is_available(): bool {
        $modules = \core_component::get_plugin_list('mod');
        return is_array($modules) && array_key_exists('quiz', $modules);
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
     * Field descriptors for bulk-editable quiz fields.
     *
     * @return array
     */
    public function get_supported_fields(): array {
        return field_map::get_date_fields();
    }

    /**
     * Enumerate all quiz instances in a course.
     *
     * @param int   $courseid target course id.
     * @param array $filters  reserved for future use, currently ignored.
     * @return array keyed by cmid.
     */
    public function get_instances_for_course(int $courseid, array $filters = []): array {
        $result = [];
        $cms = get_coursemodules_in_course('quiz', $courseid);
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
     * Return a normalised description of a single quiz course module.
     *
     * @param int $cmid course module id.
     * @return array
     */
    public function describe_instance(int $cmid): array {
        global $DB;
        $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
        $quiz = $DB->get_record(
            'quiz',
            ['id' => $cm->instance],
            $this->get_record_select_fields(),
            MUST_EXIST
        );
        return [
            'cmid'       => (int)$cmid,
            'component'  => 'mod_quiz',
            'instanceid' => (int)$cm->instance,
            'name'       => (string)$quiz->name,
            'visible'    => (bool)$cm->visible,
            'dates'      => $this->read_dates_from_record($quiz),
        ];
    }

    /**
     * Refresh quiz calendar events for the affected course modules.
     *
     * Delegates to quiz_refresh_events() once per course.
     *
     * @param int[] $cmids course module ids.
     * @return void
     */
    public function refresh_calendar_for_cmids(array $cmids): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        $courseids = [];
        foreach ($cmids as $cmid) {
            try {
                $cm = get_coursemodule_from_id('quiz', (int)$cmid, 0, false, MUST_EXIST);
                $courseids[(int)$cm->course] = true;
            } catch (\Throwable $e) {
                continue;
            }
        }
        foreach (array_keys($courseids) as $courseid) {
            quiz_refresh_events($courseid);
        }
    }

    /**
     * The primary deadline field for mod_quiz is the close time.
     *
     * completionexpected is only shifted when timeclose is actually shifted.
     *
     * @return string
     */
    public function get_completion_anchor_field(): string {
        return 'timeclose';
    }

    /**
     * Returns the database table name for the trait.
     *
     * @return string
     */
    protected function get_table_name(): string {
        return 'quiz';
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
     * Maps a {quiz} record to the two quiz date fields.
     *
     * @param \stdClass $record raw {quiz} record.
     * @return array<string, int>
     */
    protected function read_dates_from_record(\stdClass $record): array {
        return [
            'timeopen'  => (int)$record->timeopen,
            'timeclose' => (int)$record->timeclose,
        ];
    }

    /**
     * Run consistency checks on quiz instances.
     *
     * Checks R3 (process logic) and R7 (missing counterpart fields) rules
     * as defined in docs/rules.md.
     *
     * @param int[] $cmids   Course module ids to check.
     * @param array $profile Optional check profile (unused).
     * @return array Check result items.
     */
    public function run_checks(array $cmids, array $profile = []): array {
        global $DB;
        $results = [];
        $plugin = 'coursectrlmod_quiz';
        $r7defaults = ['timeopen_without_timeclose' => 'notice'];
        foreach ($cmids as $rawcmid) {
            $cmid = (int)$rawcmid;
            try {
                $cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
                $quiz = $DB->get_record(
                    'quiz',
                    ['id' => $cm->instance],
                    'id, name, timeopen, timeclose, timelimit',
                    MUST_EXIST
                );
            } catch (\Throwable $e) {
                continue;
            }
            $open = (int)$quiz->timeopen;
            $close = (int)$quiz->timeclose;
            $limit = (int)$quiz->timelimit;
            $name = $quiz->name;
            // R3: open must not be after close.
            if ($open > 0 && $close > 0 && $open > $close) {
                $results[] = $this->check_result(
                    $cmid,
                    $name,
                    'error',
                    'quiz_open_after_close',
                    get_string('check_quiz_open_after_close', 'local_coursectrl')
                );
            }
            // R3: timelimit must not exceed the open window.
            if ($limit > 0 && $open > 0 && $close > 0 && $limit > ($close - $open)) {
                $results[] = $this->check_result(
                    $cmid,
                    $name,
                    'error',
                    'quiz_timelimit_exceeds_window',
                    get_string('check_quiz_timelimit_exceeds_window', 'local_coursectrl')
                );
            }
            // R7: timeopen without timeclose.
            $sev = $this->r7_severity($plugin, 'timeopen_without_timeclose', $r7defaults);
            if ($sev && $open > 0 && $close === 0) {
                $results[] = $this->check_result(
                    $cmid,
                    $name,
                    $sev,
                    'quiz_timeopen_without_timeclose',
                    get_string('check_quiz_timeopen_without_timeclose', 'local_coursectrl')
                );
            }
        }
        return $results;
    }
}
