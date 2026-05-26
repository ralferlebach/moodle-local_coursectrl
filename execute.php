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
 * Bulk-action execution handler for the Course Control Hub.
 *
 * Receives the confirmed action from preview.php via POST, calls
 * batch_manager::execute(), and renders a result summary page.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_sesskey();

$courseid    = required_param('courseid', PARAM_INT);
$action      = required_param('action', PARAM_ALPHANUMEXT);
$payloadjson = required_param('payloadjson', PARAM_TEXT);
$cmidsjson   = required_param('cmidsjson', PARAM_TEXT);

$resolved = \local_coursectrl\local\page\course_context_resolver::resolve($courseid);
if (!$resolved) {
    throw new \moodle_exception('error_no_course', 'local_coursectrl');
}
$course  = $resolved['course'];
$context = $resolved['context'];
require_login($course);
require_capability('local/coursectrl:bulkaction', $context);

$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursectrl/execute.php', ['courseid' => $courseid]));
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
    get_string('nav_manage', 'local_coursectrl'),
    new moodle_url('/local/coursectrl/manage.php', ['courseid' => $courseid])
);
$PAGE->navbar->add(get_string('result_title', 'local_coursectrl'));

if (strlen($payloadjson) > 65536) {
    throw new \invalid_parameter_exception('payloadjson too long.');
}
try {
    $payload = json_decode($payloadjson, true, 8, JSON_THROW_ON_ERROR);
} catch (\JsonException $e) {
    throw new \invalid_parameter_exception('payloadjson is not valid JSON.');
}
if (!is_array($payload)) {
    throw new \invalid_parameter_exception('payloadjson must be a JSON object.');
}

if (strlen($cmidsjson) > 16384) {
    throw new \invalid_parameter_exception('cmidsjson too long.');
}
try {
    $cmidsarr = json_decode($cmidsjson, true, 4, JSON_THROW_ON_ERROR);
} catch (\JsonException $e) {
    throw new \invalid_parameter_exception('cmidsjson is not valid JSON.');
}
if (!is_array($cmidsarr)) {
    throw new \invalid_parameter_exception('cmidsjson must be a JSON array.');
}
$cmids = array_values(array_filter(
    array_map('intval', $cmidsarr),
    fn($id) => $id > 0
));

$manager = new \local_coursectrl\manager\batch_manager();
$batchid = $manager->execute($courseid, $action, $payload, $cmids, (int) $USER->id);

// Invalidate coursemodinfo cache and stale text-hit data so subsequent
// page loads reflect the changes made by this bulk action.
rebuild_course_cache($courseid);
(new \local_coursectrl\manager\textreview_manager())->purge_hits($courseid);

$batch = new \local_coursectrl\local\persistent\batch($batchid);
$items = \local_coursectrl\local\persistent\batch_item::get_records(['batchid' => $batchid]);

$summary = [
    'total'   => count($items),
    'success' => 0,
    'skipped' => 0,
    'error'   => 0,
];
foreach ($items as $item) {
    $status = $item->get('status');
    if ($status === \local_coursectrl\local\persistent\batch_item::STATUS_SUCCESS) {
        $summary['success']++;
    } else if ($status === \local_coursectrl\local\persistent\batch_item::STATUS_SKIPPED) {
        $summary['skipped']++;
    } else if ($status === \local_coursectrl\local\persistent\batch_item::STATUS_ERROR) {
        $summary['error']++;
    }
}

$renderable = new \local_coursectrl\output\result_page(
    $courseid,
    $batchid,
    $batch->get('status'),
    $summary,
    $action
);

/** @var \local_coursectrl\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_coursectrl');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('result_title', 'local_coursectrl'), 2);
echo $renderer->render_result_page($renderable);
echo $OUTPUT->footer();
