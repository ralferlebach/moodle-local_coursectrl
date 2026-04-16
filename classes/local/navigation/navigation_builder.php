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
 * Navigation builder for local_coursectrl pages.
 *
 * Adds the plugin's tertiary navigation tree to $PAGE->secondarynav so that
 * Moodle's Boost theme renders it as the familiar tab/selector bar. Two
 * non-link group containers ("Einstellen", "Prüfen") appear as unclickable
 * section labels, mirroring the "Gruppen"/"Rechte" pattern on the
 * Participants page.
 *
 * Usage in every plugin entry point:
 *   navigation_builder::setup($PAGE, $courseid, 'timeline');
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\navigation;

/**
 * Sets up the secondary navigation tree for local_coursectrl pages.
 */
class navigation_builder {
    /**
     * Node key constants for all menu items.
     */
    public const KEY_DASHBOARD      = 'coursectrl_dashboard';
    public const KEY_GROUP_SETUP    = 'coursectrl_group_setup';
    public const KEY_TIMELINE       = 'coursectrl_timeline';
    public const KEY_GRAPH          = 'coursectrl_graph';
    public const KEY_MANAGE         = 'coursectrl_manage';
    public const KEY_GROUP_CHECK    = 'coursectrl_group_check';
    public const KEY_SIMULATION     = 'coursectrl_simulation';
    public const KEY_HISTORY        = 'coursectrl_history';

    /**
     * Set up $PAGE->secondarynav with all plugin navigation nodes.
     *
     * @param \moodle_page $page     The current page object.
     * @param int          $courseid Course id.
     * @param string       $activekey One of the KEY_* constants identifying
     *                                the currently active page.
     */
    public static function setup(\moodle_page $page, int $courseid, string $activekey): void {
        $nav = $page->secondarynav;
        $params = ['courseid' => $courseid];

        // Dashboard.
        $nav->add(
            get_string('nav_dashboard', 'local_coursectrl'),
            new \moodle_url('/local/coursectrl/index.php', $params),
            \navigation_node::TYPE_SETTING,
            null,
            self::KEY_DASHBOARD
        );

        // Group container: Einstellen (non-link, TYPE_CONTAINER).
        $setup = $nav->add(
            get_string('nav_group_setup', 'local_coursectrl'),
            null,
            \navigation_node::TYPE_CONTAINER,
            null,
            self::KEY_GROUP_SETUP
        );
        $setup->add(
            get_string('nav_timeline', 'local_coursectrl'),
            new \moodle_url('/local/coursectrl/timeline.php', $params),
            \navigation_node::TYPE_SETTING,
            null,
            self::KEY_TIMELINE
        );
        $setup->add(
            get_string('nav_graph', 'local_coursectrl'),
            new \moodle_url('/local/coursectrl/graph.php', $params),
            \navigation_node::TYPE_SETTING,
            null,
            self::KEY_GRAPH
        );
        $setup->add(
            get_string('nav_manage', 'local_coursectrl'),
            new \moodle_url('/local/coursectrl/manage.php', $params),
            \navigation_node::TYPE_SETTING,
            null,
            self::KEY_MANAGE
        );

        // Group container: Prüfen (non-link, TYPE_CONTAINER).
        $check = $nav->add(
            get_string('nav_group_check', 'local_coursectrl'),
            null,
            \navigation_node::TYPE_CONTAINER,
            null,
            self::KEY_GROUP_CHECK
        );
        $check->add(
            get_string('nav_simulation', 'local_coursectrl'),
            new \moodle_url('/local/coursectrl/simulation.php', $params),
            \navigation_node::TYPE_SETTING,
            null,
            self::KEY_SIMULATION
        );
        $check->add(
            get_string('nav_history', 'local_coursectrl'),
            new \moodle_url('/local/coursectrl/history.php', $params),
            \navigation_node::TYPE_SETTING,
            null,
            self::KEY_HISTORY
        );

        // Mark the active node.
        $active = $nav->find($activekey, \navigation_node::TYPE_SETTING);
        if ($active) {
            $active->make_active();
        }
    }
}
