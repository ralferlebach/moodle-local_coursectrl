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
 * Checks entry point for local_coursectrl.
 *
 * Renders the consistency, risk and simulation checks page.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
use local_coursectrl\local\simulation\simulation_state_normalizer;
use local_coursectrl\output\checks_page;

$courseid = required_param('courseid', PARAM_INT);
$tab      = optional_param('tab', 'consistency', PARAM_ALPHANUMEXT);
$run      = optional_param('run', 0, PARAM_INT);

$resolved = \local_coursectrl\local\page\course_context_resolver::resolve($courseid);
if (!$resolved) {
    \local_coursectrl\local\page\course_context_resolver::render_invalid_course_page($PAGE, $OUTPUT);
}
$course  = $resolved['course'];
$context = $resolved['context'];
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

    // Extended state: separate completion/passed checkboxes + grade percentages.
    $simcomplete  = optional_param_array('sim_complete', [], PARAM_INT);
    $simpassed    = optional_param_array('sim_passed', [], PARAM_INT);
    $simgradesraw = optional_param_array('sim_grade', [], PARAM_TEXT);

    // Build per-CM metadata for the normalizer (gradepass, completionpassgrade).
    $cmmeta = [];
    $graderows = $DB->get_records_sql(
        "SELECT gi.id, cm.id AS cmid, gi.grademax, gi.gradepass, cm.completionpassgrade,
                cm.completion
           FROM {grade_items} gi
           JOIN {modules} m ON m.name = gi.itemmodule
           JOIN {course_modules} cm ON cm.module = m.id
                                    AND cm.instance = gi.iteminstance
                                    AND cm.course = gi.courseid
          WHERE gi.courseid = :courseid AND gi.itemtype = 'mod'",
        ['courseid' => (int) $courseid]
    );
    foreach ($graderows as $gr) {
        $grademax   = (float) ($gr->grademax ?? 100.0);
        $gradepass  = (float) ($gr->gradepass ?? 0.0);
        $reqpass    = !empty($gr->completionpassgrade);
        $haspass    = $gradepass > 0.0 && $grademax > 0.0;
        $cmmeta[(int) $gr->cmid] = [
            'completion_requires_pass' => $reqpass && $haspass,
            'gradepass_pct'            => ($grademax > 0.0 && $gradepass > 0.0)
                ? round($gradepass / $grademax * 100.0, 1) : 0.0,
            'completion_enabled'       => (int) $gr->completion > 0,
            'has_pass_grade'           => $haspass,
        ];
    }

    // Validate grade inputs (PARAM_TEXT → numeric check).
    $simgradesclean = [];
    foreach ($simgradesraw as $cmidstr => $rawval) {
        $cmidcheck = (int) $cmidstr;
        if ($cmidcheck <= 0) {
            continue; // Ignore invalid cmid keys.
        }
        $rawval = trim((string) $rawval);
        if ($rawval === '' || !is_numeric($rawval)) {
            continue;
        }
        $simgradesclean[$cmidcheck] = max(0.0, min(100.0, (float) $rawval));
    }

    // Get valid cmids for this course (whitelist for the normalizer).
    $validcmids = array_keys($DB->get_records('course_modules', ['course' => (int) $courseid], '', 'id'));

    // Server-side normalisation: apply the same rules as JS syncSimulationRow().
    $normalised = simulation_state_normalizer::normalise(
        $simcomplete,
        $simpassed,
        $simgradesclean,
        $cmmeta,
        $validcmids
    );
    $completionsparam = $normalised['completions'];
    $simgrades        = $normalised['grades'];

    $simstate = new learner_state($simts, $completionsparam, $groupids, $groupingids, $simgrades);
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
$PAGE->set_pagetype('course-view-' . $course->format);

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
