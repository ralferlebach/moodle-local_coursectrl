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
 * Entry point for local_coursectrl.
 * Will be replaced with the full dashboard in Phase 2.
 *
 * @package    local_coursectrl
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$courseid = optional_param('courseid', 0, PARAM_INT);

if ($courseid) {
    $course  = get_course($courseid);
    $context = context_course::instance($courseid);
    require_capability('local/coursectrl:view', $context);

    $PAGE->set_course($course);
    $PAGE->set_context($context);
} else {
    $context = context_system::instance();
    $PAGE->set_context($context);
}

$PAGE->set_url(new moodle_url('/local/coursectrl/index.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('pluginname', 'local_coursectrl'));
$PAGE->set_heading(get_string('pluginname', 'local_coursectrl'));

echo $OUTPUT->header();
echo $OUTPUT->notification(
    get_string('stub_placeholder', 'local_coursectrl'),
    \core\output\notification::NOTIFY_INFO
);
echo $OUTPUT->footer();
