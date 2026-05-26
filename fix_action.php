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
 * Fix-action handler for local_coursectrl.
 *
 * Receives AJAX requests to apply one-click fixes (e.g. unhide_cm)
 * identified by the checks page and dispatches them via fix_manager.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * One-click fix action endpoint for the checks page.
 *
 * Accepts a POST request with:
 *   courseid  int     Course id.
 *   action    string  Fix action code (see switch below).
 *   cmid      int     Single target course module id (legacy; optional if cmids provided).
 *   cmids[]   int[]   Multiple target CM ids (takes precedence over cmid when present).
 *   sesskey   string  Session key (CSRF protection).
 *
 * Supported actions:
 *   unhide_cm — Makes one or more hidden CMs visible via set_coursemodule_visible().
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
// Accept either cmids[] (array) or a single cmid for backwards compatibility.
$cmids = optional_param_array('cmids', [], PARAM_INT);
if (empty($cmids)) {
    $singlecmid = optional_param('cmid', 0, PARAM_INT);
    if ($singlecmid > 0) {
        $cmids = [$singlecmid];
    }
}
// Filter out zero/invalid values.
$cmids = array_values(array_filter($cmids, fn ($id) => $id > 0));

$resolved = \local_coursectrl\local\page\course_context_resolver::resolve($courseid);
if (!$resolved) {
    throw new \moodle_exception('error_no_course', 'local_coursectrl');
}
$course  = $resolved['course'];
$context = $resolved['context'];

require_login($course);
require_sesskey();
require_capability('local/coursectrl:bulkaction', $context);

$returntab = optional_param('tab', 'consistency', PARAM_ALPHANUMEXT);
$returnurl = new moodle_url(
    '/local/coursectrl/checks.php',
    ['courseid' => $courseid, 'tab' => $returntab]
);
// After a successful fix, trigger an automatic fresh deep analysis run
// So the user immediately sees the updated check results.
$returnurlwithrun = new moodle_url(
    '/local/coursectrl/checks.php',
    ['courseid' => $courseid, 'tab' => $returntab, 'run' => 1]
);

$success = false;
$errormsg = '';

switch ($action) {
    case 'unhide_cm':
        if (empty($cmids)) {
            $errormsg = get_string('fix_error_cmid_not_found', 'local_coursectrl');
            break;
        }
        $anyok = false;
        foreach ($cmids as $targetcmid) {
            $cm = get_coursemodule_from_id('', $targetcmid, $courseid, false, IGNORE_MISSING);
            if ($cm === false) {
                continue;
            }
            set_coursemodule_visible($targetcmid, 1);
            $anyok = true;
        }
        if ($anyok) {
            rebuild_course_cache($courseid, true);
            $success = true;
        } else {
            $errormsg = get_string('fix_error_cmid_not_found', 'local_coursectrl');
        }
        break;

    default:
        $errormsg = get_string('fix_error_unknown_action', 'local_coursectrl');
        break;
}

if ($success) {
    redirect(
        $returnurlwithrun,
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
