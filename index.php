<?php

use core\output\notification;
use local_coursectrl\local\inventory\inventory_service;
use local_coursectrl\output\dashboard_page;
use local_coursectrl\output\renderer;

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
 * Course Control Hub dashboard entry point.
 *
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once __DIR__.'/../../config.php';

require_login();

$courseid = optional_param('courseid', 0, PARAM_INT);

if (!$courseid) {
    $context = context_system::instance();
    $PAGE->set_context($context);
    $PAGE->set_url(new moodle_url('/local/coursectrl/index.php'));
    $PAGE->set_title(get_string('pluginname', 'local_coursectrl'));
    $PAGE->set_heading(get_string('pluginname', 'local_coursectrl'));

    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        get_string('error_no_course', 'local_coursectrl'),
        notification::NOTIFY_WARNING
    );
    echo $OUTPUT->footer();

    exit;
}

$course = get_course($courseid);
$context = context_course::instance($courseid);
require_capability('local/coursectrl:view', $context);

$PAGE->set_course($course);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursectrl/index.php', ['courseid' => $courseid]));
$PAGE->set_title(format_string($course->fullname).' - '.get_string('pluginname', 'local_coursectrl'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

$service = new inventory_service();
$snapshot = $service->build_for_course((int) $courseid);

$renderable = new dashboard_page($snapshot);

/** @var renderer $renderer */
$renderer = $PAGE->get_renderer('local_coursectrl');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('nav_dashboard', 'local_coursectrl'), 2);
echo $renderer->render_dashboard_page($renderable);
echo $OUTPUT->footer();
