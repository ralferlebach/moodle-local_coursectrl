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
 * Roll back a previously executed batch.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_sesskey();

$courseid = required_param('courseid', PARAM_INT);
$batchid = required_param('batchid', PARAM_INT);

$course = get_course($courseid);
$context = context_course::instance($courseid);
require_login($course);
require_capability('local/coursectrl:rollback', $context);

$manager = new \local_coursectrl\manager\rollback_manager();
$result = $manager->rollback_batch($batchid, (int) $USER->id);

$redirecturl = new moodle_url('/local/coursectrl/history.php', ['courseid' => $courseid]);
if (!empty($result['success'])) {
    redirect(
        $redirecturl,
        get_string('rollback_success', 'local_coursectrl'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$message = get_string('rollback_failed', 'local_coursectrl');
if (!empty($result['error'])) {
    $message .= ': ' . $result['error'];
}
redirect($redirecturl, $message, null, \core\output\notification::NOTIFY_ERROR);
