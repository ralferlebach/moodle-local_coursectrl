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
 * Learner simulation page for the Course Control Hub.
 *
 * Accepts the simulation scenario via GET parameters:
 *   simdate       — Y-m-d  simulated date (default: today)
 *   simtime       — H:i    simulated time (default: 00:00)
 *   completions[] — cmid=state  array of assumed completion states
 *   groupids      — comma-separated group ids
 *   groupingids   — comma-separated grouping ids
 *   run           — 1 to execute the simulation
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_coursectrl\local\navigation\navigation_builder;


use local_coursectrl\local\simulation\learner_state;
use local_coursectrl\output\simulation_page;

$courseid = required_param('courseid', PARAM_INT);
$run = optional_param('run', 0, PARAM_INT);

$course = get_course($courseid);
$context = context_course::instance($courseid);
require_login($course);
require_capability('local/coursectrl:simulate', $context);

$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursectrl/simulation.php', ['courseid' => $courseid]));
$PAGE->set_title(
    format_string($course->fullname) . ' - ' .
    get_string('pluginname', 'local_coursectrl') . ' - ' .
    get_string('sim_title', 'local_coursectrl')
);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

$PAGE->navbar->add(
    get_string('pluginname', 'local_coursectrl'),
    new moodle_url('/local/coursectrl/index.php', ['courseid' => $courseid])
);
$PAGE->navbar->add(get_string('sim_title', 'local_coursectrl'));

$service = new \local_coursectrl\local\inventory\inventory_service();
$snapshot = $service->build_for_course($courseid);

// Parse learner state from GET when form submitted.
$state = null;
if ($run) {
    $simdate = optional_param('simdate', date('Y-m-d'), PARAM_TEXT);
    $simtime = optional_param('simtime', '00:00', PARAM_TEXT);
    $datetimestr = trim($simdate) . ' ' . trim($simtime) . ':00';
    $simts = strtotime($datetimestr);
    if ($simts === false || $simts <= 0) {
        $simts = time();
    }

    $completionsparam = optional_param_array('completions', [], PARAM_INT);
    $groupidsstr = optional_param('groupids', '', PARAM_TEXT);
    $groupingidsstr = optional_param('groupingids', '', PARAM_TEXT);

    $groupids = array_filter(
        array_map('intval', explode(',', $groupidsstr)),
        fn($id) => $id > 0
    );
    $groupingids = array_filter(
        array_map('intval', explode(',', $groupingidsstr)),
        fn($id) => $id > 0
    );

    $state = new learner_state(
        $simts,
        $completionsparam,
        array_values($groupids),
        array_values($groupingids)
    );
}

$renderable = new simulation_page($snapshot, $state);

/** @var \local_coursectrl\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_coursectrl');

echo $OUTPUT->header();

$navbar = navigation_builder::make($courseid, navigation_builder::KEY_SIMULATION);

echo $OUTPUT->render($navbar);

echo $renderer->render_simulation_page($renderable);
echo $OUTPUT->footer();
