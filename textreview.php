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
 * Text-datetime review page for the Course Control Hub.
 *
 * On GET: scans the course for date/time references in free-text fields
 * and renders a review table with confidence badges.
 *
 * On POST (action=apply): applies the confirmed delta shift to selected
 * text hits and re-scans to show updated results.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid   = required_param('courseid', PARAM_INT);
$action     = optional_param('action', '', PARAM_ALPHA);

$course = get_course($courseid);
$context = context_course::instance($courseid);
require_login($course);
require_capability('local/coursectrl:view', $context);

$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursectrl/textreview.php', ['courseid' => $courseid]));
$PAGE->set_title(
    format_string($course->fullname) . ' - ' .
    get_string('pluginname', 'local_coursectrl') . ' - ' .
    get_string('textreview_title', 'local_coursectrl')
);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

$PAGE->navbar->add(
    get_string('pluginname', 'local_coursectrl'),
    new moodle_url('/local/coursectrl/index.php', ['courseid' => $courseid])
);
$PAGE->navbar->add(get_string('textreview_title', 'local_coursectrl'));

$manager = new \local_coursectrl\manager\textreview_manager();
$applymessage = null;

// Handle POST: apply changes.
if ($action === 'apply' && confirm_sesskey()) {
    require_capability('local/coursectrl:bulkaction', $context);
    $hitids     = optional_param_array('hitids', [], PARAM_INT);
    $deltadays  = optional_param('delta_days', 0, PARAM_INT);
    $deltahours = optional_param('delta_hours', 0, PARAM_INT);
    $deltaseconds = ($deltadays * 86400) + ($deltahours * 3600);

    if (!empty($hitids) && $deltaseconds !== 0) {
        $result = $manager->apply_changes($courseid, $hitids, $deltaseconds);
        $applymessage = get_string(
            'textreview_applied_result',
            'local_coursectrl',
            (object) [
                'applied' => $result['applied'],
                'skipped' => $result['skipped'],
                'errors' => count($result['errors']),
            ]
        );
    }
}

// Always (re-)scan.
$summary = $manager->scan_course($courseid);
$hits = $manager->get_hits($courseid);

$renderable = new \local_coursectrl\output\textreview_page($courseid, $hits, $summary);

/** @var \local_coursectrl\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_coursectrl');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('textreview_title', 'local_coursectrl'), 2);

if ($applymessage !== null) {
    echo $OUTPUT->notification($applymessage, \core\output\notification::NOTIFY_SUCCESS);
}

echo $renderer->render_textreview_page($renderable);
echo $OUTPUT->footer();
