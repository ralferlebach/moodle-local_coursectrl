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
 * Navigation bar renderable for local_coursectrl.
 *
 * Builds the data array expected by core/select_menu.mustache manually —
 * without relying on \core\output\select_menu_option or select_menu_optgroup
 * classes (which were added in a later Moodle version and are absent in 4.5).
 *
 * The exported data structure matches \core\output\select_menu::export_for_template
 * so that {{> core/select_menu}} renders the identical HTML to the
 * Participants page (user/index.php) tertiary navigation selector.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

use renderable;
use renderer_base;
use templatable;

/**
 * Navigation bar for local_coursectrl pages.
 */
class navigation_bar implements renderable, templatable {
    /** @var int The course id. */
    private int $courseid;

    /** @var string Active nav key — one of navigation_builder::KEY_* values. */
    private string $activekey;

    /**
     * Constructor.
     *
     * @param int    $courseid  The course id.
     * @param string $activekey Active navigation key constant value.
     */
    public function __construct(int $courseid, string $activekey) {
        $this->courseid  = $courseid;
        $this->activekey = $activekey;
    }

    /**
     * Export data for core/select_menu.mustache.
     *
     * Builds options and optgroups as plain arrays so no Moodle-version-
     * specific output classes are required.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $active = $this->activekey;
        $uniqid = \html_writer::random_id('coursectrl-nav');

        $activelabel = $this->label_for($active);
        $activeurl   = $this->u($this->script_for($active));

        $optcounter = 1;

        $options = [];

        // Dashboard — standalone option.
        $options[] = $this->make_option(
            $uniqid,
            $optcounter++,
            get_string('nav_dashboard', 'local_coursectrl'),
            $this->u('index.php'),
            $active === 'coursectrl_dashboard'
        );

        // Einstellen group.
        $gid1 = $uniqid . '-grp-setup';
        $options[] = $this->make_group(
            $gid1,
            get_string('nav_group_setup', 'local_coursectrl'),
            [
                $this->make_option($uniqid, $optcounter++,
                    get_string('nav_timeline', 'local_coursectrl'),
                    $this->u('timeline.php'), $active === 'coursectrl_timeline'),
                $this->make_option($uniqid, $optcounter++,
                    get_string('nav_graph', 'local_coursectrl'),
                    $this->u('graph.php'), $active === 'coursectrl_graph'),
                $this->make_option($uniqid, $optcounter++,
                    get_string('nav_manage', 'local_coursectrl'),
                    $this->u('manage.php'), $active === 'coursectrl_manage'),
            ]
        );

        // Prüfen group.
        $gid2 = $uniqid . '-grp-check';
        $options[] = $this->make_group(
            $gid2,
            get_string('nav_group_check', 'local_coursectrl'),
            [
                $this->make_option($uniqid, $optcounter++,
                    get_string('nav_simulation', 'local_coursectrl'),
                    $this->u('simulation.php'), $active === 'coursectrl_simulation'),
                $this->make_option($uniqid, $optcounter++,
                    get_string('nav_history', 'local_coursectrl'),
                    $this->u('history.php'), $active === 'coursectrl_history'),
            ]
        );

        return [
            'id'           => $uniqid,
            'name'         => 'local_coursectrl_nav',
            'label'        => $activelabel,
            'labelid'      => $uniqid . '-label',
            'listboxid'    => $uniqid . '-listbox',
            'value'        => $activeurl,
            'hasoptions'   => true,
            'options'      => $options,
        ];
    }

    /**
     * Build a single option array for core/select_menu.mustache.
     *
     * @param string $uniqid   ID prefix.
     * @param int    $counter  Sequential counter for option id.
     * @param string $label    Display label.
     * @param string $value    Option value (URL).
     * @param bool   $selected Whether this option is active.
     * @return array
     */
    private function make_option(
        string $uniqid,
        int $counter,
        string $label,
        string $value,
        bool $selected
    ): array {
        return [
            'optionid' => $uniqid . '-option-' . $counter,
            'label'    => $label,
            'value'    => $value,
            'selected' => $selected,
            'isgroup'  => false,
        ];
    }

    /**
     * Build an optgroup array for core/select_menu.mustache.
     *
     * @param string  $groupid ID for the group label element.
     * @param string  $label   Group header label (non-clickable).
     * @param array[] $options Child option arrays.
     * @return array
     */
    private function make_group(string $groupid, string $label, array $options): array {
        return [
            'groupid'  => $groupid,
            'label'    => $label,
            'options'  => $options,
            'isgroup'  => true,
        ];
    }

    /**
     * Return the display label for a navigation key.
     *
     * @param string $key Navigation key constant value.
     * @return string
     */
    private function label_for(string $key): string {
        $map = [
            'coursectrl_dashboard'   => 'nav_dashboard',
            'coursectrl_timeline'    => 'nav_timeline',
            'coursectrl_graph'       => 'nav_graph',
            'coursectrl_manage'      => 'nav_manage',
            'coursectrl_simulation'  => 'nav_simulation',
            'coursectrl_history'     => 'nav_history',
        ];
        return get_string($map[$key] ?? 'nav_dashboard', 'local_coursectrl');
    }

    /**
     * Return the script filename for a navigation key.
     *
     * @param string $key Navigation key constant value.
     * @return string
     */
    private function script_for(string $key): string {
        $map = [
            'coursectrl_dashboard'   => 'index.php',
            'coursectrl_timeline'    => 'timeline.php',
            'coursectrl_graph'       => 'graph.php',
            'coursectrl_manage'      => 'manage.php',
            'coursectrl_simulation'  => 'simulation.php',
            'coursectrl_history'     => 'history.php',
        ];
        return $map[$key] ?? 'index.php';
    }

    /**
     * Build an absolute URL for a plugin script.
     *
     * @param string $script Script filename (e.g. 'timeline.php').
     * @return string
     */
    private function u(string $script): string {
        return (new \moodle_url(
            '/local/coursectrl/' . $script,
            ['courseid' => $this->courseid]
        ))->out(false);
    }
}
