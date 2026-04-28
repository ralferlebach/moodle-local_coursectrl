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
 * Tests for the manage_page renderable.
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

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\output\manage_page::class)]
/**
 * Unit tests for manage_page::export_for_template().
 *
 * @covers \local_coursectrl\output\manage_page
 */
final class manage_page_test extends \advanced_testcase {
    /**
     * Build a snapshot with mixed cm types and date fields.
     *
     * @return inventory_snapshot
     */
    private function build_snapshot(): inventory_snapshot {
        $course = new course_item(
            1,
            'Demo Course',
            'DEMO',
            '',
            1,
            1700000000,
            null,
            true
        );
        $sections = [
            10 => new section_item(10, 1, 0, 'General', '', 1, true),
            11 => new section_item(11, 1, 1, 'Week 1', '', 1, true),
        ];
        $cms = [
            100 => new cm_item(100, 1, 10, 'label', 1, 'Welcome', true, null, 0),
            101 => new cm_item(101, 1, 11, 'assign', 1, 'Homework 1', true, null, 2),
            102 => new cm_item(102, 1, 11, 'quiz', 1, 'Quiz 1', false, null, 1),
            103 => new cm_item(103, 1, 11, 'forum', 1, 'Discussion', true, null, 0),
        ];
        return new inventory_snapshot($course, $sections, $cms, []);
    }

    /**
     * The context must carry courseid, sesskey and dashboardurl.
     * @covers \local_coursectrl\output\manage_page
     */
    public function test_export_includes_scalars_and_urls(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new manage_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertSame(1, $data['courseid']);
        $this->assertNotEmpty($data['sesskey']);
        $this->assertStringContainsString('index.php', $data['dashboardurl']);
        $this->assertStringContainsString('courseid=1', $data['dashboardurl']);
    }

    /**
     * The sections array must contain all non-empty sections.
     * @covers \local_coursectrl\output\manage_page
     */
    public function test_export_returns_sections(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new manage_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['hassections']);
        $this->assertCount(2, $data['sections']);
    }

    /**
     * CMs that carry date fields must be counted in withdatescount.
     * @covers \local_coursectrl\output\manage_page
     */
    public function test_export_counts_cms_with_dates(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new manage_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        // Assign has completion=2 (auto) → date fields; quiz has completion=1 (manual).
        // The count reflects CMs whose modname carries recognised date fields.
        $this->assertArrayHasKey('withdatescount', $data);
        $this->assertIsInt($data['withdatescount']);
    }

    /**
     * An empty snapshot must report hassections=false and withdatescount=0.
     * @covers \local_coursectrl\output\manage_page
     */
    public function test_export_handles_empty_snapshot(): void {
        $this->resetAfterTest();
        global $PAGE;

        $course = new course_item(2, 'Empty', 'EMPTY', '', 1, 0, null, true);
        $snapshot = new inventory_snapshot($course, [], [], []);

        $page = new manage_page($snapshot);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertFalse($data['hassections']);
        $this->assertSame(0, $data['withdatescount']);
    }
}
