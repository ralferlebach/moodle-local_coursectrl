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
 * Tests for the dashboard_page renderable.
 *
 * @package    local_coursectrl
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

use local_coursectrl\local\entity\cm_item;
use local_coursectrl\local\entity\course_item;
use local_coursectrl\local\entity\section_item;
use local_coursectrl\local\inventory\inventory_snapshot;

/**
 * Unit tests for dashboard_page::export_for_template().
 *
 * @coversDefaultClass \local_coursectrl\output\dashboard_page
 */
final class dashboard_page_test extends \advanced_testcase {
    /**
     * Build a small in-memory snapshot for tests.
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
            11 => new section_item(11, 1, 1, null, 'Week 1', 1, true),
        ];
        $cms = [
            100 => new cm_item(100, 1, 10, 'label', 1, 'Welcome', true, null, 0),
            101 => new cm_item(101, 1, 11, 'assign', 1, 'Homework 1', true, '{"op":"&","c":[]}', 2),
            102 => new cm_item(102, 1, 11, 'quiz', 1, 'Quiz 1', false, null, 1),
        ];
        return new inventory_snapshot($course, $sections, $cms, []);
    }

    /**
     * The exported template context must carry course-level scalar fields.
     */
    public function test_export_includes_course_scalars(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new dashboard_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertSame(1, $data['courseid']);
        $this->assertSame('Demo Course', $data['coursefullname']);
        $this->assertSame('DEMO', $data['courseshortname']);
        $this->assertSame(1700000000, $data['coursestartdate']);
        $this->assertNull($data['courseenddate']);
        $this->assertFalse($data['hasenddate']);
        $this->assertTrue($data['coursevisible']);
    }

    /**
     * Stat counters must reflect the snapshot collection sizes.
     */
    public function test_export_includes_counts(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new dashboard_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertSame(2, $data['sectioncount']);
        $this->assertSame(3, $data['cmcount']);
        $this->assertSame(0, $data['textcount']);
        $this->assertTrue($data['hassections']);
    }

    /**
     * Course modules must be grouped under the section they belong to.
     */
    public function test_export_groups_cms_under_sections(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new dashboard_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertCount(2, $data['sections']);

        $general = $data['sections'][0];
        $this->assertSame(10, $general['id']);
        $this->assertSame('General', $general['name']);
        $this->assertTrue($general['hasname']);
        $this->assertSame(1, $general['cmcount']);
        $this->assertCount(1, $general['cms']);
        $this->assertSame('Welcome', $general['cms'][0]['name']);
        $this->assertSame('mod_label', $general['cms'][0]['component']);

        $week1 = $data['sections'][1];
        $this->assertSame(11, $week1['id']);
        $this->assertFalse($week1['hasname']);
        $this->assertSame(2, $week1['cmcount']);
        $this->assertCount(2, $week1['cms']);
    }

    /**
     * Per-cm flags (visible, completion, availability) must be exposed.
     */
    public function test_export_exposes_cm_flags(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new dashboard_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $week1 = $data['sections'][1];
        $assign = $week1['cms'][0];
        $quiz = $week1['cms'][1];

        $this->assertTrue($assign['visible']);
        $this->assertTrue($assign['hascompletion']);
        $this->assertTrue($assign['hasavailability']);

        $this->assertFalse($quiz['visible']);
        $this->assertTrue($quiz['hascompletion']);
        $this->assertFalse($quiz['hasavailability']);
    }

    /**
     * An empty snapshot must report hassections=false so the empty-state
     * branch in the template fires.
     */
    public function test_export_handles_empty_snapshot(): void {
        $this->resetAfterTest();
        global $PAGE;

        $course = new course_item(2, 'Empty', 'EMPTY', '', 1, 0, null, true);
        $snapshot = new inventory_snapshot($course, [], [], []);

        $page = new dashboard_page($snapshot);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertFalse($data['hassections']);
        $this->assertSame(0, $data['sectioncount']);
        $this->assertSame(0, $data['cmcount']);
    }
}
