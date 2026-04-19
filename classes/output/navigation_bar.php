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
 * Exports a clean, self-contained data structure for navigation_bar.mustache.
 * Does NOT delegate to core/select_menu because that template's variable
 * contract differs across Moodle 4.x / 5.x versions.
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
     * Export for navigation_bar.mustache.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $active  = $this->activekey;
        $uid     = \html_writer::random_id('cctrl');

        $activelabel = $this->label_for($active);
        $activeurl   = $this->u($this->script_for($active));

        $n = 0;

        $groups = [
            [
                'isgroup'    => false,
                'grouplabel' => '',
                'groupid'    => '',
                'options'    => [],
                'standalone' => [
                    [
                        'oid'      => $uid . '-o' . $n++,
                        'label'    => get_string('nav_dashboard', 'local_coursectrl'),
                        'value'    => $this->u('index.php'),
                        'selected' => $active === 'coursectrl_dashboard',
                        'visible'  => true,
                    ],
                ],
                'hasstandalone' => true,
            ],
            [
                'isgroup'    => true,
                'grouplabel' => get_string('nav_group_setup', 'local_coursectrl'),
                'groupid'    => $uid . '-g1',
                'hasstandalone' => false,
                'standalone' => [],
                'options'    => [
                    [
                        'oid'      => $uid . '-o' . $n++,
                        'label'    => get_string('nav_timeline', 'local_coursectrl'),
                        'value'    => $this->u('timeline.php'),
                        'selected' => $active === 'coursectrl_timeline',
                        'visible'  => true,
                    ],
                    [
                        'oid'      => $uid . '-o' . $n++,
                        'label'    => get_string('nav_dependencies', 'local_coursectrl'),
                        'value'    => $this->u('dependencies.php'),
                        'selected' => $active === 'coursectrl_graph',
                        'visible'  => true,
                    ],
                    [
                        'oid'      => $uid . '-o' . $n++,
                        'label'    => get_string('nav_manage', 'local_coursectrl'),
                        'value'    => $this->u('manage.php'),
                        'selected' => $active === 'coursectrl_manage',
                        'visible'  => $this->can('local/coursectrl:bulkaction'),
                    ],
                ],
            ],
            [
                'isgroup'    => true,
                'grouplabel' => get_string('nav_group_check', 'local_coursectrl'),
                'groupid'    => $uid . '-g2',
                'hasstandalone' => false,
                'standalone' => [],
                'options'    => [
                    [
                        'oid'      => $uid . '-o' . $n++,
                        'label'    => get_string('nav_checks', 'local_coursectrl'),
                        'value'    => $this->u('checks.php'),
                        'selected' => $active === 'coursectrl_checks',
                        'visible'  => true,
                    ],
                    [
                        'oid'      => $uid . '-o' . $n++,
                        'label'    => get_string('nav_history', 'local_coursectrl'),
                        'value'    => $this->u('history.php'),
                        'selected' => $active === 'coursectrl_history',
                        'visible'  => true,
                    ],
                ],
            ],
        ];

        foreach ($groups as &$group) {
            if (!empty($group['hasstandalone'])) {
                $group['standalone'] = array_values(array_filter(
                    $group['standalone'],
                    fn(array $item): bool => !array_key_exists('visible', $item) || !empty($item['visible'])
                ));
                $group['hasstandalone'] = !empty($group['standalone']);
            }
            if (!empty($group['isgroup'])) {
                $group['options'] = array_values(array_filter(
                    $group['options'],
                    fn(array $item): bool => !array_key_exists('visible', $item) || !empty($item['visible'])
                ));
                if (empty($group['options'])) {
                    $group['isgroup'] = false;
                }
            }
        }
        unset($group);
        $groups = array_values(array_filter(
            $groups,
            fn(array $group): bool => !empty($group['hasstandalone']) || !empty($group['isgroup'])
        ));

        return [
            'uid'         => $uid,
            'labelid'     => $uid . '-lbl',
            'listboxid'   => $uid . '-list',
            'inputid'     => $uid . '-inp',
            'activelabel' => $activelabel,
            'activevalue' => $activeurl,
            'groups'      => $groups,
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
            'coursectrl_graph'       => 'nav_dependencies',
            'coursectrl_manage'      => 'nav_manage',
            'coursectrl_simulation'  => 'nav_simulation',
            'coursectrl_checks'      => 'nav_checks',
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
            'coursectrl_graph'       => 'dependencies.php',
            'coursectrl_manage'      => 'manage.php',
            'coursectrl_simulation'  => 'checks.php',
            'coursectrl_checks'      => 'checks.php',
            'coursectrl_history'     => 'history.php',
        ];
        return $map[$key] ?? 'index.php';
    }

    /**
     * Build an absolute URL for a plugin script.
     *
     * @param string $script Script filename.
     * @return string
     */

    /**
     * Check whether the current user can access a navigation target.
     *
     * @param string $capability Capability name.
     * @return bool
     */
    private function can(string $capability): bool {
        return has_capability($capability, \context_course::instance($this->courseid));
    }

    /**
     * Build the absolute URL for a plugin entry-point script.
     *
     * @param string $script Script filename relative to the plugin root (e.g. 'index.php').
     * @return string Absolute URL string.
     */
    private function u(string $script): string {
        return (new \moodle_url(
            '/local/coursectrl/' . $script,
            ['courseid' => $this->courseid]
        ))->out(false);
    }
}
