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
 * Logs & Historie — entry point for local_coursectrl.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_coursectrl\local\navigation\navigation_builder;
use local_coursectrl\output\history_page;

$courseid = required_param('courseid', PARAM_INT);
$page     = optional_param('page', 0, PARAM_INT);

$course  = get_course($courseid);
$context = context_course::instance($courseid);
require_login($course);
require_capability('local/coursectrl:view', $context);

$PAGE->set_url(new moodle_url('/local/coursectrl/history.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('nav_history', 'local_coursectrl'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

$PAGE->navbar->add(
    get_string('pluginname', 'local_coursectrl'),
    new moodle_url('/local/coursectrl/index.php', ['courseid' => $courseid])
);
$PAGE->navbar->add(get_string('nav_history', 'local_coursectrl'));

$navbar = navigation_builder::make($courseid, navigation_builder::KEY_HISTORY);

$renderable = new history_page($courseid);

/** @var \local_coursectrl\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_coursectrl');

echo $OUTPUT->header();

echo $OUTPUT->render($navbar);
echo $renderer->render($renderable);
echo $OUTPUT->footer();
