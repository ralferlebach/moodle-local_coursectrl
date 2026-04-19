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
 * One-click fix action endpoint for the checks page.
 *
 * Accepts a POST request with:
 *   courseid  int     Course id.
 *   action    string  Fix action code (see switch below).
 *   cmid      int     Target course module id.
 *   sesskey   string  Session key (CSRF protection).
 *
 * Supported actions:
 *   unhide_cm — Makes a hidden CM visible via set_coursemodule_visible().
 *               Requires local/coursectrl:bulkaction capability.
 *
 * On success: redirects back to checks.php with a success notice.
 * On failure: redirects back with an error notice.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$action = required_param('action', PARAM_ALPHANUMEXT);
$cmid = required_param('cmid', PARAM_INT);

$course = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_sesskey();
require_capability('local/coursectrl:bulkaction', $context);

$returntab = optional_param('tab', 'consistency', PARAM_ALPHANUMEXT);
$returnurl = new moodle_url(
    '/local/coursectrl/checks.php',
    ['courseid' => $courseid, 'tab' => $returntab]
);

$success = false;
$errormsg = '';

switch ($action) {
    case 'unhide_cm':
        $cm = get_coursemodule_from_id('', $cmid, $courseid, false, IGNORE_MISSING);
        if ($cm === false) {
            $errormsg = get_string('fix_error_cmid_not_found', 'local_coursectrl');
        } else {
            set_coursemodule_visible($cmid, 1);
            rebuild_course_cache($courseid, true);
            $success = true;
        }
        break;

    default:
        $errormsg = get_string('fix_error_unknown_action', 'local_coursectrl');
        break;
}

if ($success) {
    redirect(
        $returnurl,
        get_string('fix_success_unhide_cm', 'local_coursectrl'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
} else {
    redirect(
        $returnurl,
        $errormsg ?: get_string('fix_error_generic', 'local_coursectrl'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}
