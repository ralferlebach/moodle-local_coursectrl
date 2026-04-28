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
 * Plugin-wide hook callbacks for local_coursectrl.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Return the list of user preferences that local_coursectrl manages.
 *
 * @return array Preference definitions keyed by preference name.
 */
function local_coursectrl_user_preferences(): array {
    return [
        'local_coursectrl_showcalendar' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => 1,
            'choices' => [0, 1],
        ],
        'local_coursectrl_immediateapply' => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => 0,
            'choices' => [0, 1],
        ],
    ];
}

/**
 * Callback fired when any of the calendar-provider settings is saved.
 *
 * Wired up in settings.php via admin_setting::set_updatedcallback() on each
 * calendar setting. When an admin saves a calendar setting, this:
 *
 *   1. Purges the local_coursectrl/caldata cache so orphan keys from the
 *      previous country/region/language config no longer pollute it.
 *   2. Runs a synchronous warm so the dashboard reflects the new
 *      configuration on the very next request, not after the next cron tick.
 *   3. Queues the adhoc fallback in case the synchronous run failed.
 *
 * @return void
 */
function local_coursectrl_calendar_settings_changed(): void {
    try {
        $cache = \cache::make('local_coursectrl', 'caldata');
        $cache->purge();
    } catch (\Throwable $e) {
        debugging(
            'local_coursectrl caldata purge failed: ' . $e->getMessage(),
            DEBUG_DEVELOPER
        );
    }
    try {
        \local_coursectrl\task\warm_calendar_cache::do_warm();
    } catch (\Throwable $e) {
        debugging(
            'local_coursectrl synchronous warm after settings save failed: ' . $e->getMessage(),
            DEBUG_DEVELOPER
        );
    }
    \local_coursectrl\task\warm_calendar_cache_adhoc::queue();
}

/**
 * Extend the course navigation to add a "Course Control Hub" entry in the
 * course "More" menu. This legacy callback is supported in all Moodle 4.x/5.x
 * versions and is the primary mechanism for adding plugin entries to the course
 * navigation bar.
 *
 * @param navigation_node $coursenode The course navigation node.
 * @param stdClass        $course     The course object.
 * @param context_course  $context    The course context.
 */
function local_coursectrl_extend_navigation_course(
    navigation_node $coursenode,
    stdClass $course,
    context_course $context
): void {
    if (!has_capability('local/coursectrl:view', $context)) {
        return;
    }
    $url = new moodle_url(
        '/local/coursectrl/index.php',
        ['courseid' => $course->id]
    );
    $coursenode->add(
        get_string('pluginname', 'local_coursectrl'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_coursectrl',
        new pix_icon('i/settings', '')
    );
}
