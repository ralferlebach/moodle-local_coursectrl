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
 * Risk assessment page for the Course Control Hub.
 *
 * Shows scored, grouped dead-end and consistency findings for a course.
 * Pass ?run=1 to trigger a fresh assessment run.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_coursectrl\local\navigation\navigation_builder;
use local_coursectrl\output\risk_page;

$courseid = required_param('courseid', PARAM_INT);
$run = optional_param('run', 0, PARAM_INT);

$course = get_course($courseid);
$context = context_course::instance($courseid);
require_login($course);
require_capability('local/coursectrl:view', $context);

$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursectrl/risks.php', ['courseid' => $courseid]));
$PAGE->set_title(
    format_string($course->fullname) . ' - ' .
    get_string('pluginname', 'local_coursectrl') . ' - ' .
    get_string('risks_title', 'local_coursectrl')
);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

$PAGE->navbar->add(
    get_string('pluginname', 'local_coursectrl'),
    new moodle_url('/local/coursectrl/index.php', ['courseid' => $courseid])
);
$PAGE->navbar->add(get_string('risks_title', 'local_coursectrl'));

$navbar = navigation_builder::make($courseid, navigation_builder::KEY_RISKS);

$renderable = new risk_page($course, (bool)$run);

$renderer = $PAGE->get_renderer('local_coursectrl');

echo $OUTPUT->header();
echo $OUTPUT->render($navbar);
echo $renderer->render_risk_page($renderable);
echo $OUTPUT->footer();
