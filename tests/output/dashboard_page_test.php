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
 * @copyright  2026 Ralf Erlebach
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
 * @covers \local_coursectrl\output\dashboard_page
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

    /**
     * The manage URL must link to manage.php with the correct courseid.
     */
    public function test_export_includes_manage_url(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new dashboard_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertArrayHasKey('manageurl', $data);
        $this->assertStringContainsString('manage.php', $data['manageurl']);
        $this->assertStringContainsString('courseid=1', $data['manageurl']);
    }

    /**
     * A CM whose availability JSON references a non-existent cmid must expose
     * a dangling_dep warning and be counted in warningcount.
     */
    public function test_dangling_dep_warning_surfaced_on_dashboard(): void {
        $this->resetAfterTest();
        global $PAGE;

        $avail = json_encode([
            'op' => '&',
            'c' => [['type' => 'completion', 'cm' => 9999, 'e' => 1]],
            'show' => false,
        ]);
        $course = new course_item(1, 'Demo Course', 'DEMO', '', 1, 1700000000, null, true);
        $sections = [10 => new section_item(10, 1, 0, 'General', '', 1, true)];
        $cms = [
            200 => new cm_item(200, 1, 10, 'assign', 200, 'Restricted', true, $avail, 2),
        ];
        $snapshot = new inventory_snapshot($course, $sections, $cms, []);

        $page = new dashboard_page($snapshot);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertGreaterThan(0, $data['warningcount']);
        $this->assertTrue($data['haswarnings']);

        $cm200 = $data['sections'][0]['cms'][0];
        $this->assertTrue($cm200['haswarnings']);
        $types = array_column($cm200['warnings'], 'type');
        $this->assertContains('dangling_dep', $types);
    }

    /**
     * A CM that depends on an activity with completion tracking disabled must
     * expose an impossible_dep warning.
     */
    public function test_impossible_dep_warning_surfaced_on_dashboard(): void {
        $this->resetAfterTest();
        global $PAGE;

        $avail = json_encode([
            'op' => '&',
            'c' => [['type' => 'completion', 'cm' => 300, 'e' => 1]],
            'show' => false,
        ]);
        $course = new course_item(1, 'Demo Course', 'DEMO', '', 1, 1700000000, null, true);
        $sections = [10 => new section_item(10, 1, 0, 'General', '', 1, true)];
        $cms = [
            // Provider CM: completion tracking disabled.
            300 => new cm_item(300, 1, 10, 'label', 300, 'Intro', true, null, 0),
            // Depending CM: requires completion of cmid 300.
            301 => new cm_item(301, 1, 10, 'assign', 301, 'Task', true, $avail, 2),
        ];
        $snapshot = new inventory_snapshot($course, $sections, $cms, []);

        $page = new dashboard_page($snapshot);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        // CM 301 should have the impossible_dep warning.
        $allcms = $data['sections'][0]['cms'];
        $cm301data = null;
        foreach ($allcms as $cmdata) {
            if ($cmdata['cmid'] === 301) {
                $cm301data = $cmdata;
                break;
            }
        }
        $this->assertNotNull($cm301data, 'cmid 301 must be present in template data');
        $this->assertTrue($cm301data['haswarnings']);
        $types = array_column($cm301data['warnings'], 'type');
        $this->assertContains('impossible_dep', $types);
    }

    /**
     * warningcount reflects the number of CMs that have at least one warning,
     * not the total number of individual warning entries.
     */
    public function test_warning_count_is_per_cm(): void {
        $this->resetAfterTest();
        global $PAGE;

        // CM 400 has a dangling dep AND (once adapters load) would show
        // temporal conflicts too – but in this unit test the registry returns
        // no real adapter dates so only the dangling_dep is raised.
        $avail = json_encode([
            'op' => '&',
            'c' => [['type' => 'completion', 'cm' => 9999, 'e' => 1]],
            'show' => false,
        ]);
        $course = new course_item(1, 'Demo', 'D', '', 1, 0, null, true);
        $sections = [10 => new section_item(10, 1, 0, 'S', '', 1, true)];
        $cms = [
            400 => new cm_item(400, 1, 10, 'assign', 400, 'A', true, $avail, 2),
            401 => new cm_item(401, 1, 10, 'assign', 401, 'B', true, null, 2),
        ];
        $snapshot = new inventory_snapshot($course, $sections, $cms, []);

        $page = new dashboard_page($snapshot);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        // Only cm 400 has a warning; warningcount must be 1, not > 1.
        $this->assertSame(1, $data['warningcount']);
    }
}
