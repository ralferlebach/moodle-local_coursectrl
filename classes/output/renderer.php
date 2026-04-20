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
 * Plugin renderer for local_coursectrl.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

use plugin_renderer_base;

/**
 * Renders Course Control Hub pages from renderable templates.
 */
class renderer extends plugin_renderer_base {
    /**
     * Render the navigation bar select_menu.
     *
     * @param navigation_bar $nav The navigation bar renderable.
     * @return string HTML.
     */
    public function render_navigation_bar(navigation_bar $nav): string {
        $data = $nav->export_for_template($this);
        return $this->render_from_template('local_coursectrl/navigation_bar', $data);
    }

    /**
     * Render the history/logs page.
     *
     * @param history_page $page The history renderable.
     * @return string HTML.
     */
    public function render_history_page(history_page $page): string {
        $data = $page->export_for_template($this);
        return $this->render_from_template('local_coursectrl/history', $data);
    }

    /**
     * Render the course dashboard page.
     *
     * @param dashboard_page $page The dashboard renderable.
     * @return string HTML.
     */
    public function render_dashboard_page(dashboard_page $page): string {
        $data = $page->export_for_template($this);
        return $this->render_from_template('local_coursectrl/dashboard', $data);
    }

    /**
     * Render the bulk-action management page.
     *
     * @param manage_page $page The manage renderable.
     * @return string HTML.
     */
    public function render_manage_page(manage_page $page): string {
        $data = $page->export_for_template($this);
        return $this->render_from_template('local_coursectrl/manage', $data);
    }

    /**
     * Render the bulk-action preview page.
     *
     * @param preview_page $page The preview renderable.
     * @return string HTML.
     */
    public function render_preview_page(preview_page $page): string {
        $data = $page->export_for_template($this);
        return $this->render_from_template('local_coursectrl/preview', $data);
    }

    /**
     * Render the bulk-action execution result page.
     *
     * @param result_page $page The result renderable.
     * @return string HTML.
     */
    public function render_result_page(result_page $page): string {
        $data = $page->export_for_template($this);
        return $this->render_from_template('local_coursectrl/result', $data);
    }


    /**
     * Render the chronological timeline page.
     *
     * @param timeline_page $page The timeline renderable.
     * @return string HTML.
     */
    public function render_timeline_page(timeline_page $page): string {
        $data = $page->export_for_template($this);
        return $this->render_from_template('local_coursectrl/timeline', $data);
    }

    /**
     * Render the dependency graph and Gantt view.
     *
     * @param graph_page $page The graph renderable.
     * @return string HTML.
     */
    public function render_graph_page(graph_page $page): string {
        $data = $page->export_for_template($this);
        return $this->render_from_template('local_coursectrl/graph', $data);
    }

    /**
     * Render the learner simulation page.
     *
     * @param simulation_page $page The simulation renderable.
     * @return string HTML.
     */
    public function render_simulation_page(simulation_page $page): string {
        $data = $page->export_for_template($this);
        return $this->render_from_template('local_coursectrl/simulation', $data);
    }

    /**
     * Render the checks page (consistency and risk assessment tabs).
     *
     * @param checks_page $page The checks renderable.
     * @return string HTML.
     */
    public function render_checks_page(checks_page $page): string {
        $data = $page->export_for_template($this);
        return $this->render_from_template('local_coursectrl/checks', $data);
    }
}
