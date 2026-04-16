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
 * Tests for the graph_page renderable.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

use local_coursectrl\local\entity\cm_item;
use local_coursectrl\local\entity\course_item;
use local_coursectrl\local\entity\section_item;
use local_coursectrl\local\inventory\inventory_snapshot;

/**
 * Unit tests for graph_page::export_for_template().
 *
 * @covers \local_coursectrl\output\graph_page
 */
final class graph_page_test extends \advanced_testcase {
    /**
     * Build a minimal course snapshot.
     *
     * @param cm_item[] $cms Optional CMs to include (keyed by cmid).
     * @return inventory_snapshot
     */
    private function build_snapshot(array $cms = []): inventory_snapshot {
        $course = new course_item(1, 'Test Course', 'TC', '', 1, 1748736000, null, true);
        $sections = [10 => new section_item(10, 1, 0, 'General', '', 1, true)];
        return new inventory_snapshot($course, $sections, $cms, []);
    }

    /**
     * export_for_template returns required scalar keys.
     */
    public function test_export_includes_required_scalars(): void {
        $this->resetAfterTest();
        global $PAGE;
        $page = new graph_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));
        $this->assertArrayHasKey('courseid', $data);
        $this->assertArrayHasKey('graphjson', $data);
        $this->assertArrayHasKey('ganttjson', $data);
        $this->assertArrayHasKey('hasgraph', $data);
        $this->assertArrayHasKey('hasgantt', $data);
        $this->assertArrayHasKey('dashboardurl', $data);
        $this->assertArrayHasKey('timelineurl', $data);
        $this->assertArrayHasKey('manageurl', $data);
    }

    /**
     * graphjson is valid JSON with the expected structure.
     */
    public function test_graphjson_is_valid_json(): void {
        $this->resetAfterTest();
        global $PAGE;
        $cms = [
            100 => new cm_item(100, 1, 10, 'assign', 100, 'HW', true, null, 2),
        ];
        $page = new graph_page($this->build_snapshot($cms));
        $data = $page->export_for_template($PAGE->get_renderer('core'));
        $decoded = json_decode($data['graphjson'], true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('nodes', $decoded);
        $this->assertArrayHasKey('edges', $decoded);
        $this->assertArrayHasKey('hasdata', $decoded);
        $this->assertTrue($decoded['hasdata']);
    }

    /**
     * ganttjson is valid JSON.
     */
    public function test_ganttjson_is_valid_json(): void {
        $this->resetAfterTest();
        global $PAGE;
        $page = new graph_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));
        $decoded = json_decode($data['ganttjson'], true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('hasdata', $decoded);
        $this->assertArrayHasKey('rows', $decoded);
    }

    /**
     * hasgraph=false when course has no CMs.
     */
    public function test_empty_course_hasgraph_false(): void {
        $this->resetAfterTest();
        global $PAGE;
        $page = new graph_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));
        $this->assertFalse($data['hasgraph']);
    }

    /**
     * dashboardurl links to index.php with correct courseid.
     */
    public function test_dashboardurl_correct(): void {
        $this->resetAfterTest();
        global $PAGE;
        $page = new graph_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));
        $this->assertStringContainsString('index.php', $data['dashboardurl']);
        $this->assertStringContainsString('courseid=1', $data['dashboardurl']);
    }
}
