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
 * Checks page for the Course Control Hub.
 *
 * Unified page with three tabs:
 *   consistency — Plausibility and collision checks (transient)
 *   risks       — Structural risk assessment (on demand)
 *   simulation  — Learner simulation (visibility/accessibility from learner perspective)
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_coursectrl\local\navigation\navigation_builder;
use local_coursectrl\local\simulation\learner_state;
use local_coursectrl\output\checks_page;

$courseid = required_param('courseid', PARAM_INT);
$tab      = optional_param('tab', 'consistency', PARAM_ALPHANUMEXT);
$run      = optional_param('run', 0, PARAM_INT);

$course  = get_course($courseid);
$context = context_course::instance($courseid);
require_login($course);
require_capability('local/coursectrl:view', $context);
if ($tab === 'simulation') {
    require_capability('local/coursectrl:simulate', $context);
}

// Parse simulation form parameters when the simulation tab form is submitted.
$simstate = null;
if ($tab === 'simulation' && $run) {
    $simdate = optional_param('simdate', date('Y-m-d'), PARAM_TEXT);
    $simtime = optional_param('simtime', '00:00', PARAM_TEXT);
    $simts   = strtotime(trim($simdate) . ' ' . trim($simtime) . ':00');
    if ($simts === false || $simts <= 0) {
        $simts = time();
    }
    $completionsparam = optional_param_array('completions', [], PARAM_INT);
    $groupids = array_values(array_filter(
        array_map('intval', optional_param_array('groupids', [], PARAM_INT)),
        fn ($id) => $id > 0
    ));
    $groupingids = array_values(array_filter(
        array_map('intval', optional_param_array('groupingids', [], PARAM_INT)),
        fn ($id) => $id > 0
    ));
    $simstate = new learner_state($simts, $completionsparam, $groupids, $groupingids);
}

$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url(
    new moodle_url('/local/coursectrl/checks.php', ['courseid' => $courseid, 'tab' => $tab])
);
$PAGE->set_title(
    format_string($course->fullname) . ' — ' .
    get_string('pluginname', 'local_coursectrl') . ' — ' .
    get_string('checks_title', 'local_coursectrl')
);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

$PAGE->navbar->add(
    get_string('pluginname', 'local_coursectrl'),
    new moodle_url('/local/coursectrl/index.php', ['courseid' => $courseid])
);
$PAGE->navbar->add(get_string('checks_title', 'local_coursectrl'));

$navbar     = navigation_builder::make($courseid, navigation_builder::KEY_CHECKS);
$renderable = new checks_page($course, $tab, (bool)$run, $simstate);
$renderer   = $PAGE->get_renderer('local_coursectrl');

echo $OUTPUT->header();
echo $OUTPUT->render($navbar);
echo $renderer->render_checks_page($renderable);
echo $OUTPUT->footer();
