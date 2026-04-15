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

/**
 * Unit tests for manage_page::export_for_template().
 *
 * @covers \local_coursectrl\output\manage_page
 */
final class manage_page_test extends \advanced_testcase {
    /**
     * Build a small in-memory snapshot with mixed supported/unsupported CMs.
     *
     * @return inventory_snapshot
     */
    private function build_snapshot(): inventory_snapshot {
        $course = new course_item(
            1,
            'Test Course',
            'TEST',
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
            102 => new cm_item(102, 1, 11, 'quiz', 1, 'Quiz 1', true, null, 1),
            103 => new cm_item(103, 1, 11, 'forum', 1, 'Discussion', false, null, 0),
        ];
        return new inventory_snapshot($course, $sections, $cms, []);
    }

    /**
     * Context must include courseid, sesskey and navigation URLs.
     */
    public function test_export_includes_scaffold_fields(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new manage_page($this->build_snapshot(), ['mod_assign', 'mod_quiz']);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertSame(1, $data['courseid']);
        $this->assertNotEmpty($data['sesskey']);
        $this->assertStringContainsString('preview.php', $data['previewurl']);
        $this->assertStringContainsString('index.php', $data['dashboardurl']);
    }

    /**
     * Actions array must contain at least shift_dates.
     */
    public function test_export_includes_actions(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new manage_page($this->build_snapshot(), ['mod_assign']);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertNotEmpty($data['actions']);
        $actionvalues = array_column($data['actions'], 'value');
        $this->assertContains('shift_dates', $actionvalues);
    }

    /**
     * CMs with a registered adapter must be marked as supported.
     */
    public function test_export_marks_supported_cms(): void {
        $this->resetAfterTest();
        global $PAGE;

        $supported = ['mod_assign', 'mod_quiz'];
        $page = new manage_page($this->build_snapshot(), $supported);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertSame(2, $data['supportedcount']);

        // Week 1 section should have both supported and unsupported CMs.
        $week1 = $data['sections'][1];
        $this->assertTrue($week1['hassupported']);

        $cmsbyname = [];
        foreach ($week1['cms'] as $cm) {
            $cmsbyname[$cm['name']] = $cm;
        }

        $this->assertTrue($cmsbyname['Homework 1']['supported']);
        $this->assertTrue($cmsbyname['Quiz 1']['supported']);
        $this->assertFalse($cmsbyname['Discussion']['supported']);
    }

    /**
     * General section with only unsupported CMs must have hassupported=false.
     */
    public function test_export_section_without_supported_cms(): void {
        $this->resetAfterTest();
        global $PAGE;

        // Only assign supported, but label is in General section.
        $page = new manage_page($this->build_snapshot(), ['mod_assign']);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $general = $data['sections'][0];
        $this->assertFalse($general['hassupported']);
    }

    /**
     * Empty snapshot must report hassections=false.
     */
    public function test_export_handles_empty_snapshot(): void {
        $this->resetAfterTest();
        global $PAGE;

        $course = new course_item(2, 'Empty', 'EMPTY', '', 1, 0, null, true);
        $snapshot = new inventory_snapshot($course, [], [], []);

        $page = new manage_page($snapshot, []);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertFalse($data['hassections']);
        $this->assertSame(0, $data['supportedcount']);
    }

    /**
     * Hidden CMs must preserve their visible=false flag.
     */
    public function test_export_preserves_visibility_flag(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new manage_page($this->build_snapshot(), ['mod_forum']);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $week1 = $data['sections'][1];
        $forum = null;
        foreach ($week1['cms'] as $cm) {
            if ($cm['name'] === 'Discussion') {
                $forum = $cm;
                break;
            }
        }
        $this->assertNotNull($forum);
        $this->assertFalse($forum['visible']);
    }
}
