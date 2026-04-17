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
 * Shift / unset action handler for the timeline page.
 *
 * Receives a POST from the shift or delete dialog and dispatches
 * through batch_manager::execute() with the appropriate action.
 * When the user preference 'local_coursectrl_immediateapply' is set,
 * successful executions redirect directly back to the timeline.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_sesskey();

$courseid   = required_param('courseid', PARAM_INT);
$actiontype = required_param('action_type', PARAM_ALPHANUMEXT);
$cmidsraw   = required_param('cmids', PARAM_RAW);
$followdeps = optional_param('followdeps', 0, PARAM_INT);
$deltadays  = optional_param('delta_days', 0, PARAM_INT);
$deltahours = optional_param('delta_hours', 0, PARAM_INT);
$fieldsraw  = optional_param('fields', '', PARAM_RAW);
$shiftfieldsraw = optional_param('shift_fields', '', PARAM_RAW);
$scantext   = optional_param('scan_text', 0, PARAM_INT);
$formatjson = optional_param('format', '', PARAM_ALPHA) === 'json';

$course = get_course($courseid);
$context = context_course::instance($courseid);
require_login($course);
require_capability('local/coursectrl:bulkaction', $context);

$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursectrl/shift.php', ['courseid' => $courseid]));
$PAGE->set_title(
    format_string($course->fullname) . ' - ' .
    get_string('pluginname', 'local_coursectrl') . ' - ' .
    get_string('result_title', 'local_coursectrl')
);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

$PAGE->navbar->add(
    get_string('pluginname', 'local_coursectrl'),
    new moodle_url('/local/coursectrl/index.php', ['courseid' => $courseid])
);
$PAGE->navbar->add(
    get_string('timeline_title', 'local_coursectrl'),
    new moodle_url('/local/coursectrl/timeline.php', ['courseid' => $courseid])
);
$PAGE->navbar->add(get_string('result_title', 'local_coursectrl'));

$cmids = array_values(array_filter(array_map('intval', explode(',', $cmidsraw))));

$timelineurl = new moodle_url('/local/coursectrl/timeline.php', ['courseid' => $courseid]);

// Expand with dependents if followdeps is set.
if ($followdeps && !empty($cmids)) {
    $service = new \local_coursectrl\local\inventory\inventory_service();
    $snapshot = $service->build_for_course($courseid);
    $depindex = new \local_coursectrl\local\analysis\dependency_index($snapshot->cms);

    $expanded = array_fill_keys($cmids, true);
    $queue = $cmids;
    while (!empty($queue)) {
        $current = array_shift($queue);
        foreach ($depindex->get_dependents($current) as $dep) {
            if (!isset($expanded[$dep])) {
                $expanded[$dep] = true;
                $queue[] = $dep;
            }
        }
    }
    $cmids = array_keys($expanded);
}

// Build the payload for the selected action.
$payload = [];
if ($actiontype === 'shift_dates') {
    $payload['delta'] = ($deltadays * 86400) + ($deltahours * 3600);
    // Optional field restriction: only shift the specified fields.
    $shiftfields = array_values(
        array_filter(array_map('trim', explode(',', $shiftfieldsraw)))
    );
    if (!empty($shiftfields)) {
        $payload['fields'] = $shiftfields;
    }
    $hasvaliddelta = $payload['delta'] !== 0;
    $nothingtodo = empty($cmids) || !$hasvaliddelta;
} else if ($actiontype === 'unset_dates') {
    $fields = array_filter(array_map('trim', explode(',', $fieldsraw)));
    $payload['fields'] = array_values($fields);
    $nothingtodo = empty($cmids) || empty($payload['fields']);
} else {
    throw new moodle_exception('invalidaction', 'local_coursectrl');
}

if ($nothingtodo) {
    if ($formatjson) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'nothing_to_do']);
        exit;
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('result_title', 'local_coursectrl'), 2);
    echo $OUTPUT->notification(
        get_string('shift_no_change', 'local_coursectrl'),
        \core\output\notification::NOTIFY_WARNING
    );
    echo $OUTPUT->continue_button($timelineurl);
    echo $OUTPUT->footer();
    exit;
}

// E7: Pre-filter cmids against the adapter registry.
// CMs without a registered adapter (e.g. url, resource, label, completionexpected slots)
// cannot be shifted and should not produce misleading "skipped" log entries.
$registry = new \local_coursectrl\manager\registry();
$adaptercmids = array_values(array_filter($cmids, function(int $cid) use ($registry): bool {
    return $registry->get_for_cmid($cid) !== null;
}));

if (empty($adaptercmids)) {
    if ($formatjson) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'batchid' => 0,
            'summary' => [
                'total' => count($cmids),
                'success' => 0,
                'noop' => count($cmids),
                'skipped' => 0,
                'error' => 0,
            ],
            'conflicts' => [],
        ]);
        exit;
    }
    redirect(
        $timelineurl,
        get_string('shift_no_change', 'local_coursectrl'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}
$cmids = $adaptercmids;

$manager = new \local_coursectrl\manager\batch_manager();
$batchid = $manager->execute($courseid, $actiontype, $payload, $cmids, (int) $USER->id);

$batch = new \local_coursectrl\local\persistent\batch($batchid);
$items = \local_coursectrl\local\persistent\batch_item::get_records(['batchid' => $batchid]);

$summary = ['total' => count($items), 'success' => 0, 'noop' => 0, 'skipped' => 0, 'error' => 0];
foreach ($items as $item) {
    $status = $item->get('status');
    $resultraw = $item->get('resultjson');
    $result = $resultraw ? json_decode($resultraw, true) : [];
    // Distinguish noop (stored as STATUS_SUCCESS but changed=[]) from real changes.
    $changed = $result['changed'] ?? null;
    if ($status === \local_coursectrl\local\persistent\batch_item::STATUS_SUCCESS) {
        if (is_array($changed) && count($changed) === 0) {
            $summary['noop']++;
        } else {
            $summary['success']++;
        }
    } else if ($status === \local_coursectrl\local\persistent\batch_item::STATUS_SKIPPED) {
        $summary['skipped']++;
    } else if ($status === \local_coursectrl\local\persistent\batch_item::STATUS_ERROR) {
        $summary['error']++;
    }
}

// JSON response path (AJAX calls from timeline.js).
// Returns shift result only — the caller handles scanning and text review.
if ($formatjson) {
    // Run a post-shift temporal conflict check so the caller can surface
    // any ordering violations that the shift may have introduced.
    $shiftconflicts = [];
    if ($summary['success'] > 0) {
        $snapshot = (new \local_coursectrl\local\inventory\inventory_service())->build_for_course($courseid);
        $datecollector = new \local_coursectrl\local\analysis\date_collector();
        $datesbycm = $datecollector->collect_grouped_by_cm($snapshot->cms);
        $detector = new \local_coursectrl\local\analysis\temporal_conflict_detector();
        $allconflicts = $detector->detect($snapshot->cms, $datesbycm);
        foreach ($cmids as $checkcmid) {
            if (!empty($allconflicts[(int)$checkcmid])) {
                foreach ($allconflicts[(int)$checkcmid] as $conflict) {
                    $shiftconflicts[] = [
                        'cmid' => (int)$checkcmid,
                        'field_early' => $conflict['field_early'],
                        'field_late' => $conflict['field_late'],
                    ];
                }
            }
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => $summary['error'] === 0,
        'batchid' => $batchid,
        'summary' => $summary,
        'conflicts' => $shiftconflicts,
    ]);
    exit;
}

// Immediate-apply flow: if the user's preference is set and there were no
// errors, redirect silently back to the timeline with a success message.
$immediateapply = (bool) get_user_preferences('local_coursectrl_immediateapply', 0);

// If scan_text is requested (and shift succeeded), run text analysis and
// redirect to the Textprüfung tab so the user can review text date references.
if (
    $scantext
    && $actiontype === 'shift_dates'
    && $summary['error'] === 0
    && $batch->get('status') === 'executed'
) {
    $textmanager = new \local_coursectrl\manager\textreview_manager();
    $textmanager->scan_course($courseid);

    // Build list of collision warnings from error/skipped batch items.
    $collisionnotices = [];
    foreach ($items as $item) {
        if ($item->get('status') === \local_coursectrl\local\persistent\batch_item::STATUS_ERROR) {
            $resultraw = $item->get('resultjson');
            $result = $resultraw ? json_decode($resultraw, true) : [];
            $collisionnotices[] = $result['message'] ?? get_string('shift_collision_generic', 'local_coursectrl');
        }
    }
    // Store collision notices in session so timeline_page can read them.
    if (!empty($collisionnotices)) {
        $_SESSION['coursectrl_collisions_' . $batchid] = json_encode($collisionnotices);
    }

    redirect(new \moodle_url('/local/coursectrl/timeline.php', [
        'courseid'   => $courseid,
        'tab'        => 'textreview',
        'delta_days' => $deltadays,
        'delta_hours' => $deltahours,
        'batchid'    => $batchid,
        'from_shift' => 1,
    ]));
}

if ($immediateapply && $summary['error'] === 0 && $batch->get('status') === 'executed') {
    redirect(
        $timelineurl,
        get_string('result_success', 'local_coursectrl'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$renderable = new \local_coursectrl\output\result_page(
    $courseid,
    $batchid,
    $batch->get('status'),
    $summary,
    $actiontype
);

/** @var \local_coursectrl\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_coursectrl');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('result_title', 'local_coursectrl'), 2);
echo $renderer->render_result_page($renderable);
echo $OUTPUT->footer();
