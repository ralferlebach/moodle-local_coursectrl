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
 * Central course-context resolver for local_coursectrl entry points.
 *
 * Resolves a raw course id into a course record and context without
 * triggering a dml_missing_record_exception. Entry points call this
 * helper so that invalid or non-existent course ids always produce a
 * controlled response instead of a server-error stacktrace.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\page;

/**
 * Resolves a course id to a (course, context) pair without throwing on invalid ids.
 */
class course_context_resolver {
    /**
     * Attempt to resolve a course id into a course record and context.
     *
     * Returns null when the id is zero, negative, or does not exist in
     * the database. No exception is thrown in those cases.
     *
     * @param int $courseid Raw course id from the request (may be 0 or unknown).
     * @return array{course: \stdClass, context: \context_course}|null
     *         Associative array with keys 'course' and 'context', or null on failure.
     */
    public static function resolve(int $courseid): ?array {
        global $DB;
        if ($courseid <= 0) {
            return null;
        }
        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            return null;
        }
        return [
            'course'  => $course,
            'context' => \context_course::instance($courseid),
        ];
    }

    /**
     * Render an invalid-course warning page and exit.
     *
     * Outputs a complete Moodle HTML page with a warning notification and
     * then calls exit. Use this in HTML-view entry points.
     *
     * @param \moodle_page   $PAGE   The global PAGE object.
     * @param \core_renderer $OUTPUT The global OUTPUT object.
     */
    public static function render_invalid_course_page(
        \moodle_page $PAGE,
        \core_renderer $OUTPUT
    ): never {
        require_login();
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url(new \moodle_url('/local/coursectrl/index.php'));
        $PAGE->set_title(get_string('pluginname', 'local_coursectrl'));
        $PAGE->set_heading(get_string('pluginname', 'local_coursectrl'));
        $PAGE->set_pagelayout('incourse');
        echo $OUTPUT->header();
        echo $OUTPUT->notification(
            get_string('error_no_course', 'local_coursectrl'),
            \core\output\notification::NOTIFY_WARNING
        );
        echo $OUTPUT->footer();
        exit;
    }
}
