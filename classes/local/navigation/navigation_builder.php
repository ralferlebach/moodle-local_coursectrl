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
 * Navigation builder for local_coursectrl.
 *
 * Provides navigation key constants and a factory method for creating
 * navigation_bar renderables. Does NOT touch $PAGE->secondarynav — the
 * navigation is rendered as a select_menu dropdown within the page
 * content area (same visual pattern as the Participants page).
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\navigation;

use local_coursectrl\output\navigation_bar;

/**
 * Navigation key constants and factory for navigation_bar renderables.
 */
class navigation_builder {
    /** @var string Navigation key for the dashboard page. */
    public const KEY_DASHBOARD = 'coursectrl_dashboard';
    /** @var string Navigation key for the "Einstellen" group container. */
    public const KEY_GROUP_SETUP = 'coursectrl_group_setup';
    /** @var string Navigation key for the timeline page. */
    public const KEY_TIMELINE = 'coursectrl_timeline';
    /** @var string Navigation key for the dependency graph page. */
    public const KEY_GRAPH = 'coursectrl_graph';
    /** @var string Navigation key for the manage / bulk-edit page. */
    public const KEY_MANAGE = 'coursectrl_manage';
    /** @var string Navigation key for the "Prüfen" group container. */
    public const KEY_GROUP_CHECK = 'coursectrl_group_check';
    /** @var string Navigation key for the simulation page. */
    public const KEY_SIMULATION = 'coursectrl_simulation';
    /** @var string Navigation key for the history / logs page. */
    public const KEY_HISTORY = 'coursectrl_history';

    /**
     * Create a navigation_bar renderable for the given page.
     *
     * Usage in entry points (after require_capability, before $OUTPUT->header):
     *
     *   $navbar = navigation_builder::make($courseid, navigation_builder::KEY_TIMELINE);
     *   // Then, after $OUTPUT->header():
     *   echo $OUTPUT->render($navbar);
     *
     * @param int    $courseid  The course id.
     * @param string $activekey One of the KEY_* constants.
     * @return navigation_bar
     */
    public static function make(int $courseid, string $activekey): navigation_bar {
        return new navigation_bar($courseid, $activekey);
    }
}
