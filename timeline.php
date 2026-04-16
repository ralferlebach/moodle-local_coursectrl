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
 * Chronological timeline manager page for the Course Control Hub.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid     = required_param('courseid', PARAM_INT);
$showpast     = optional_param('showpast', 1, PARAM_INT);
$onlywithdeps = optional_param('onlywithdeps', 0, PARAM_INT);
$components   = optional_param_array('components', [], PARAM_COMPONENT);

$course = get_course($courseid);
$context = context_course::instance($courseid);
require_login($course);
require_capability('local/coursectrl:view', $context);

$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursectrl/timeline.php', ['courseid' => $courseid]));
$PAGE->set_title(
    format_string($course->fullname) . ' - ' .
    get_string('pluginname', 'local_coursectrl') . ' - ' .
    get_string('timeline_title', 'local_coursectrl')
);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

$PAGE->navbar->add(
    get_string('pluginname', 'local_coursectrl'),
    new moodle_url('/local/coursectrl/index.php', ['courseid' => $courseid])
);
$PAGE->navbar->add(get_string('timeline_title', 'local_coursectrl'));

$showcalendar   = (bool) get_user_preferences('local_coursectrl_showcalendar', 1);
$immediateapply = (bool) get_user_preferences('local_coursectrl_immediateapply', 0);

$service = new \local_coursectrl\local\inventory\inventory_service();
$snapshot = $service->build_for_course($courseid);

$filters = [
    'showpast' => (bool) $showpast,
    'onlywithdeps' => (bool) $onlywithdeps,
    'components' => $components,
    'showcalendar' => $showcalendar,
    'immediateapply' => $immediateapply,
];

$renderable = new \local_coursectrl\output\timeline_page($snapshot, $filters);

/** @var \local_coursectrl\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_coursectrl');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('timeline_title', 'local_coursectrl'), 2);
echo $renderer->render_timeline_page($renderable);
echo $OUTPUT->footer();
