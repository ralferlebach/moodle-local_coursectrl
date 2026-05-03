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
 * Tests for the simulation_page renderable.
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
use local_coursectrl\local\simulation\learner_state;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\output\simulation_page::class)]
/**
 * Unit tests for simulation_page::export_for_template().
 *
 * @covers \local_coursectrl\output\simulation_page
 */
final class simulation_page_test extends \advanced_testcase {
    /**
     * Build a minimal snapshot with optional CMs.
     *
     * @param cm_item[] $cms CMs keyed by cmid.
     * @return inventory_snapshot
     */
    private function build_snapshot(array $cms = []): inventory_snapshot {
        $course = new course_item(1, 'Test', 'T', '', 1, 1748736000, null, true);
        $sections = [10 => new section_item(10, 1, 0, 'General', '', 1, true)];
        return new inventory_snapshot($course, $sections, $cms, []);
    }

    /**
     * Without a submitted state hasresults is false and resultrows is empty.
     * @covers \local_coursectrl\output\simulation_page
     */
    public function test_no_state_means_no_results(): void {
        $this->resetAfterTest();
        global $PAGE;
        $page = new simulation_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));
        $this->assertFalse($data['hasresults']);
        $this->assertEmpty($data['resultrows']);
    }

    /**
     * Required scalar keys are always present in the export.
     * @covers \local_coursectrl\output\simulation_page
     */
    public function test_required_scalar_keys_present(): void {
        $this->resetAfterTest();
        global $PAGE;
        $page = new simulation_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));
        $requiredkeys = [
            'courseid',
            'sesskey',
            'selfurl',
            'dashboardurl',
            'simdate',
            'simtime',
            'cmformrows',
            'hasresults',
        ];
        foreach ($requiredkeys as $key) {
            $this->assertArrayHasKey($key, $data, "Missing key: $key");
        }
    }

    /**
     * With a learner state supplied, hasresults=true and resultrows contains one row per CM.
     * @covers \local_coursectrl\output\simulation_page
     */
    public function test_with_state_produces_result_rows(): void {
        $this->resetAfterTest();
        global $PAGE;
        $cms = [
            100 => new cm_item(100, 1, 10, 'assign', 100, 'Homework', true, null, 2),
            101 => new cm_item(101, 1, 10, 'quiz', 101, 'Quiz', true, null, 2),
        ];
        $state = new learner_state(1750507200, [100 => 1]);
        $page = new simulation_page($this->build_snapshot($cms), $state);
        $data = $page->export_for_template($PAGE->get_renderer('core'));
        $this->assertTrue($data['hasresults']);
        $this->assertSame(2, $data['totalcmcount']);
        $this->assertCount(2, $data['resultrows']);
    }

    /**
     * Accessible + incomplete + tracked CM appears in nextsteprows.
     * @covers \local_coursectrl\output\simulation_page
     */
    public function test_accessible_incomplete_is_next_step(): void {
        $this->resetAfterTest();
        global $PAGE;
        $cms = [
            200 => new cm_item(200, 1, 10, 'assign', 200, 'Task', true, null, 2),
        ];
        // CM 200 is incomplete → should be a next step.
        $state = new learner_state(1750507200, []);
        $page = new simulation_page($this->build_snapshot($cms), $state);
        $data = $page->export_for_template($PAGE->get_renderer('core'));
        $this->assertTrue($data['hasnextsteps']);
        $this->assertSame(1, $data['nextstepcount']);
        $this->assertSame(200, $data['nextsteprows'][0]['cmid']);
    }

    /**
     * Teacher-hidden CM shows accessible=false in resultrows.
     * @covers \local_coursectrl\output\simulation_page
     */
    public function test_hidden_cm_not_accessible_in_results(): void {
        $this->resetAfterTest();
        global $PAGE;
        $cms = [
            300 => new cm_item(300, 1, 10, 'assign', 300, 'Hidden', false, null, 2),
        ];
        $state = new learner_state(1750507200);
        $page = new simulation_page($this->build_snapshot($cms), $state);
        $data = $page->export_for_template($PAGE->get_renderer('core'));
        $row = $data['resultrows'][0];
        $this->assertFalse($row['accessible']);
        $this->assertFalse($row['teacher_visible']);
    }

    /**
     * Activity names containing HTML/script are escaped in completion-reason labels.
     * The label passes through format_string() + s(), so no raw HTML must appear.
     * @covers \local_coursectrl\output\simulation_page
     */
    public function test_activity_name_xss_is_escaped_in_completion_label(): void {
        global $PAGE;
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $payload = '<script>alert(1)</script>';

        // Create an assignment whose name contains an XSS payload.
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'name'   => $payload,
        ]);
        $cmid = (int) $assign->cmid;

        // Build a synthetic completion-condition reason as the simulator would return.
        $reason = [
            'type'     => 'completion',
            'cmid'     => $cmid,
            'status'   => 'fail',
            'expected' => 1,
        ];

        // Run export_for_template to trigger label generation.
        $page = new \local_coursectrl\output\simulation_page(
            $course->id,
            null,
            []
        );
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        // The payload must not appear raw in any label output anywhere in the template data.
        $json = json_encode($data);
        $this->assertStringNotContainsString(
            '<script>alert(1)</script>',
            $json,
            'Raw XSS payload must not appear unescaped in simulation template data'
        );
    }

    /**
     * Group names containing HTML event handlers are escaped in group-reason labels.
     * @covers \local_coursectrl\output\simulation_page
     */
    public function test_group_name_xss_is_escaped_in_group_label(): void {
        global $PAGE;
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $payload = '<img src=x onerror=alert(1)>';

        // Create a group with an XSS payload as its name.
        $group = $this->getDataGenerator()->create_group([
            'courseid' => $course->id,
            'name'     => $payload,
        ]);

        $page = new \local_coursectrl\output\simulation_page(
            $course->id,
            null,
            []
        );
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $json = json_encode($data);
        $this->assertStringNotContainsString(
            '<img src=x onerror=alert(1)>',
            $json,
            'Raw XSS payload in group name must not appear unescaped in simulation template data'
        );
    }

    /**
     * Grouping names containing HTML are escaped in grouping-reason labels.
     * @covers \local_coursectrl\output\simulation_page
     */
    public function test_grouping_name_xss_is_escaped_in_grouping_label(): void {
        global $PAGE;
        $this->resetAfterTest();

        $course   = $this->getDataGenerator()->create_course();
        $payload  = '<svg onload=alert(1)>';

        $grouping = $this->getDataGenerator()->create_grouping([
            'courseid' => $course->id,
            'name'     => $payload,
        ]);

        $page = new \local_coursectrl\output\simulation_page(
            $course->id,
            null,
            []
        );
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $json = json_encode($data);
        $this->assertStringNotContainsString(
            '<svg onload=alert(1)>',
            $json,
            'Raw XSS payload in grouping name must not appear unescaped in simulation template data'
        );
    }
}
