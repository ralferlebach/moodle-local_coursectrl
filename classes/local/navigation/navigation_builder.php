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
 * Renders the plugin navigation as Moodle's native \core\output\select_menu,
 * identical to the Participants page (user/index.php) tertiary navigation.
 * The dropdown becomes both the navigation control and the page title.
 *
 * Call in each entry point after $OUTPUT->header():
 *
 *   echo navigation_builder::render($courseid, navigation_builder::KEY_TIMELINE, $OUTPUT);
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\navigation;

/**
 * Renders plugin navigation as a native Moodle select_menu dropdown.
 */
class navigation_builder {
    /** @var string Key for the dashboard page. */
    public const KEY_DASHBOARD   = 'coursectrl_dashboard';
    /** @var string Key for the "Einstellen" group header. */
    public const KEY_GROUP_SETUP = 'coursectrl_group_setup';
    /** @var string Key for the timeline page. */
    public const KEY_TIMELINE    = 'coursectrl_timeline';
    /** @var string Key for the dependency graph page. */
    public const KEY_GRAPH       = 'coursectrl_graph';
    /** @var string Key for the manage / activity-list page. */
    public const KEY_MANAGE      = 'coursectrl_manage';
    /** @var string Key for the "Prüfen" group header. */
    public const KEY_GROUP_CHECK = 'coursectrl_group_check';
    /** @var string Key for the simulation / plausibility-check page. */
    public const KEY_SIMULATION  = 'coursectrl_simulation';
    /** @var string Key for the history / logs page. */
    public const KEY_HISTORY     = 'coursectrl_history';

    /**
     * Render the select_menu navigation and return the HTML.
     *
     * The returned HTML is the core/select_menu component — visually identical
     * to the Participants page tertiary navigation.
     *
     * @param int              $courseid  Course id.
     * @param string           $activekey One of the KEY_* constants.
     * @param \renderer_base   $output    Page renderer.
     * @return string Rendered HTML.
     */
    public static function render(int $courseid, string $activekey, \renderer_base $output): string {
        $select = static::build_select($courseid, $activekey);
        $data = $select->export_for_template($output);
        return $output->render_from_template('core/select_menu', $data);
    }

    /**
     * Build the \core\output\select_menu object.
     *
     * @param int    $courseid  Course id.
     * @param string $activekey Active page key.
     * @return \core\output\select_menu
     */
    private static function build_select(int $courseid, string $activekey): \core\output\select_menu {
        $url = function (string $script) use ($courseid): string {
            return (new \moodle_url('/local/coursectrl/' . $script, ['courseid' => $courseid]))->out(false);
        };

        $opt = function (string $strkey, string $script) use ($url): \core\output\select_menu_option {
            return new \core\output\select_menu_option(
                get_string($strkey, 'local_coursectrl'),
                $url($script)
            );
        };

        $activeurl = $url(static::key_to_script($activekey));

        // Dashboard stands alone.
        $options = [
            $opt('nav_dashboard', 'index.php'),
        ];

        // Einstellen group — with optgroups if available (Moodle 4.3+).
        $setupopts = [
            $opt('nav_timeline',   'timeline.php'),
            $opt('nav_graph',      'graph.php'),
            $opt('nav_manage',     'manage.php'),
        ];
        $checkopts = [
            $opt('nav_simulation', 'simulation.php'),
            $opt('nav_history',    'history.php'),
        ];

        if (class_exists('\core\output\select_menu_optgroup')) {
            $options[] = new \core\output\select_menu_optgroup(
                get_string('nav_group_setup', 'local_coursectrl'),
                $setupopts
            );
            $options[] = new \core\output\select_menu_optgroup(
                get_string('nav_group_check', 'local_coursectrl'),
                $checkopts
            );
        } else {
            foreach (array_merge($setupopts, $checkopts) as $opt_item) {
                $options[] = $opt_item;
            }
        }

        $select = new \core\output\select_menu('coursectrl_nav', $options, $activeurl);
        // Visually-hidden label for accessibility — same pattern as Participants page.
        $select->set_label(
            get_string(static::key_to_strkey($activekey), 'local_coursectrl'),
            ['class' => 'visually-hidden']
        );
        return $select;
    }

    /**
     * Map a nav key to the PHP script filename.
     *
     * @param string $key Nav key constant value.
     * @return string
     */
    public static function key_to_script(string $key): string {
        $map = [
            self::KEY_DASHBOARD  => 'index.php',
            self::KEY_TIMELINE   => 'timeline.php',
            self::KEY_GRAPH      => 'graph.php',
            self::KEY_MANAGE     => 'manage.php',
            self::KEY_SIMULATION => 'simulation.php',
            self::KEY_HISTORY    => 'history.php',
        ];
        return $map[$key] ?? 'index.php';
    }

    /**
     * Map a nav key to the lang string key for that page.
     *
     * @param string $key Nav key constant value.
     * @return string Lang string key.
     */
    public static function key_to_strkey(string $key): string {
        $map = [
            self::KEY_DASHBOARD  => 'nav_dashboard',
            self::KEY_TIMELINE   => 'nav_timeline',
            self::KEY_GRAPH      => 'nav_graph',
            self::KEY_MANAGE     => 'nav_manage',
            self::KEY_SIMULATION => 'nav_simulation',
            self::KEY_HISTORY    => 'nav_history',
        ];
        return $map[$key] ?? 'nav_dashboard';
    }
}
