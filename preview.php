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
 * Bulk-action preview page for the Course Control Hub.
 *
 * Receives the action, payload and cmid selection from manage.php via
 * POST, calls preview_manager::build() server-side, and renders a
 * confirmation table with old/new values, skipped items and errors.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_sesskey();

$courseid   = required_param('courseid', PARAM_INT);
$action     = required_param('action', PARAM_ALPHANUMEXT);
$deltadays  = optional_param('delta_days', 0, PARAM_INT);
$deltahours = optional_param('delta_hours', 0, PARAM_INT);
$cmids      = optional_param_array('cmids', [], PARAM_INT);

$course = get_course($courseid);
$context = context_course::instance($courseid);
require_login($course);
require_capability('local/coursectrl:view', $context);

$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursectrl/preview.php', ['courseid' => $courseid]));
$PAGE->set_title(
    format_string($course->fullname) . ' - ' .
    get_string('pluginname', 'local_coursectrl') . ' - ' .
    get_string('preview_title', 'local_coursectrl')
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
$PAGE->navbar->add(get_string('preview_title', 'local_coursectrl'));

// Build payload from form parameters.
$payload = [];
if ($action === 'shift_dates') {
    $deltaseconds = ($deltadays * 86400) + ($deltahours * 3600);
    $payload['delta'] = $deltaseconds;
}

// Ensure cmids is a flat array of integers.
$cmids = array_values(array_map('intval', $cmids));

$manager = new \local_coursectrl\manager\preview_manager();
$result = $manager->build($courseid, $action, $payload, $cmids);

$renderable = new \local_coursectrl\output\preview_page(
    $courseid,
    $action,
    $payload,
    $cmids,
    $result
);

/** @var \local_coursectrl\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_coursectrl');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('preview_title', 'local_coursectrl'), 2);
echo $renderer->render_preview_page($renderable);
echo $OUTPUT->footer();
