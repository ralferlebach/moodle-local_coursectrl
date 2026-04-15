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
$payloadjson = required_param('payloadjson', PARAM_RAW);
$cmidsjson   = required_param('cmidsjson', PARAM_RAW);

$course = get_course($courseid);
$context = context_course::instance($courseid);
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
    get_string('nav_bulk', 'local_coursectrl'),
    new moodle_url('/local/coursectrl/manage.php', ['courseid' => $courseid])
);
$PAGE->navbar->add(get_string('result_title', 'local_coursectrl'));

$payload = json_decode($payloadjson, true);
if (!is_array($payload)) {
    $payload = [];
}
$cmids = json_decode($cmidsjson, true);
if (!is_array($cmids)) {
    $cmids = [];
}
$cmids = array_values(array_map('intval', $cmids));

$manager = new \local_coursectrl\manager\batch_manager();
$batchid = $manager->execute($courseid, $action, $payload, $cmids, (int) $USER->id);

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
