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
 * Dependency graph and Gantt view for the Course Control Hub.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_coursectrl\local\navigation\navigation_builder;

$courseid       = required_param('courseid', PARAM_INT);
$hideindependents = optional_param('hideindependents', 1, PARAM_INT);
$groupids       = optional_param_array('groupids', [], PARAM_INT);
$filterbygroup  = optional_param('filterbygroup', 0, PARAM_INT);
$blockedids     = optional_param_array('blockedids', [], PARAM_INT);
$nextstepids    = optional_param_array('nextstepids', [], PARAM_INT);

$course = get_course($courseid);
$context = context_course::instance($courseid);
require_login($course);
require_capability('local/coursectrl:view', $context);

$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursectrl/dependencies.php', ['courseid' => $courseid]));
$PAGE->set_title(
    format_string($course->fullname) . ' - ' .
    get_string('pluginname', 'local_coursectrl') . ' - ' .
    get_string('dependencies_title', 'local_coursectrl')
);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');
$PAGE->set_pagetype('course-view-' . $course->format);

$PAGE->navbar->add(
    get_string('pluginname', 'local_coursectrl'),
    new moodle_url('/local/coursectrl/index.php', ['courseid' => $courseid])
);
$PAGE->navbar->add(get_string('dependencies_title', 'local_coursectrl'));

$service = new \local_coursectrl\local\inventory\inventory_service();
$snapshot = $service->build_for_course($courseid);

$renderable = new \local_coursectrl\output\graph_page($snapshot, [
    'hideindependents' => (bool) $hideindependents,
    'groupids'         => array_filter(array_map('intval', $groupids)),
    'filterbygroup'    => (bool) $filterbygroup,
    'blockedids'       => array_filter(array_map('intval', $blockedids)),
    'nextstepids'      => array_filter(array_map('intval', $nextstepids)),
]);

/** @var \local_coursectrl\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_coursectrl');

echo $OUTPUT->header();

$navbar = navigation_builder::make($courseid, navigation_builder::KEY_GRAPH);

echo $OUTPUT->render($navbar);

echo $renderer->render_graph_page($renderable);
echo $OUTPUT->footer();
