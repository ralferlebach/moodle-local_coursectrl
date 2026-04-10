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
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

use plugin_renderer_base;

/**
 * Renders Course Control Hub pages from renderable templates.
 */
class renderer extends plugin_renderer_base {
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
}
