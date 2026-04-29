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

use local_coursectrl\local\navigation\navigation_builder;

$courseid     = required_param('courseid', PARAM_INT);
$showpastparam = optional_param('showpast', null, PARAM_INT);
$onlywithdeps = optional_param('onlywithdeps', 0, PARAM_INT);
$components   = optional_param_array('components', [], PARAM_COMPONENT);
$tab          = optional_param('tab', 'timeline', PARAM_ALPHA);
$deltadays    = optional_param('delta_days', 0, PARAM_INT);
$deltahours   = optional_param('delta_hours', 0, PARAM_INT);
$fromshift    = optional_param('from_shift', 0, PARAM_INT);
$shiftbatchid = optional_param('batchid', 0, PARAM_INT);
$focuscmid    = optional_param('focus', 0, PARAM_INT);

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

// Showpast: if submitted via form, persist the new value; otherwise read preference.
if ($showpastparam !== null) {
    set_user_preference('local_coursectrl_showpast', (int) $showpastparam);
    $showpast = (bool) $showpastparam;
} else {
    $showpast = (bool) get_user_preferences('local_coursectrl_showpast', 1);
}

$service = new \local_coursectrl\local\inventory\inventory_service();
$snapshot = $service->build_for_course($courseid);

// Auto-enable showpast when the URL targets a past-dated activity and
// the user has not explicitly toggled the filter in this request.
// This ensures the focused entry is visible when arriving from the calendar.
$focusdaykey = '';
if ($focuscmid > 0 && $showpastparam === null) {
    $collector = new \local_coursectrl\local\analysis\date_collector();
    $allentries = $collector->collect($snapshot->cms);
    $now = time();
    foreach ($allentries as $entry) {
        if ((int) $entry['cmid'] !== $focuscmid) {
            continue;
        }
        if (empty($focusdaykey)) {
            $focusdaykey = date('Y-m-d', $entry['timestamp']);
        }
        if ((int) $entry['timestamp'] < $now) {
            // At least one entry for this CM is in the past — override showpast
            // for this request only; the user preference is left unchanged.
            $showpast = true;
            break;
        }
    }
}

$filters = [
    'showpast' => (bool) $showpast,
    'onlywithdeps' => (bool) $onlywithdeps,
    'components' => $components,
    'showcalendar' => $showcalendar,
    'immediateapply' => $immediateapply,
    'tab' => $tab,
    'textreview_delta_days' => $deltadays,
    'textreview_delta_hours' => $deltahours,
    'from_shift' => (bool) $fromshift,
    'shift_batchid' => $shiftbatchid,
    'focusdaykey' => $focusdaykey,
];

$renderable = new \local_coursectrl\output\timeline_page($snapshot, $filters);

/** @var \local_coursectrl\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_coursectrl');

$navbar = navigation_builder::make($courseid, navigation_builder::KEY_TIMELINE);

echo $OUTPUT->header();

echo $OUTPUT->render($navbar);
echo $renderer->render_timeline_page($renderable);
echo $OUTPUT->footer();
