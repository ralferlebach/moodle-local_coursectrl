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
 * Tests for the result_page renderable.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\output\result_page::class)]
/**
 * Unit tests for result_page::export_for_template().
 *
 * @covers \local_coursectrl\output\result_page
 */
final class result_page_test extends \advanced_testcase {
    /**
     * issuccess must be true when status is 'executed'.
     * @covers \local_coursectrl\output\result_page
     */
    public function test_export_success_status(): void {
        $this->resetAfterTest();
        global $PAGE;

        $summary = ['total' => 3, 'success' => 2, 'skipped' => 1, 'error' => 0];
        $page = new result_page(1, 42, 'executed', $summary, 'shift_dates');
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['issuccess']);
        $this->assertSame(42, $data['batchid']);
        $this->assertSame('executed', $data['status']);
    }

    /**
     * issuccess must be false when status is 'failed'.
     * @covers \local_coursectrl\output\result_page
     */
    public function test_export_failed_status(): void {
        $this->resetAfterTest();
        global $PAGE;

        $summary = ['total' => 3, 'success' => 0, 'skipped' => 0, 'error' => 3];
        $page = new result_page(1, 99, 'failed', $summary, 'shift_dates');
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertFalse($data['issuccess']);
        $this->assertSame(99, $data['batchid']);
    }

    /**
     * Summary counts must be passed through as integers.
     * @covers \local_coursectrl\output\result_page
     */
    public function test_export_includes_summary_counts(): void {
        $this->resetAfterTest();
        global $PAGE;

        $summary = ['total' => 5, 'success' => 3, 'skipped' => 1, 'error' => 1];
        $page = new result_page(1, 1, 'executed', $summary, 'shift_dates');
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertSame(5, $data['summary_total']);
        $this->assertSame(3, $data['summary_success']);
        $this->assertSame(1, $data['summary_skipped']);
        $this->assertSame(1, $data['summary_error']);
    }

    /**
     * Navigation URLs must point to the correct course.
     * @covers \local_coursectrl\output\result_page
     */
    public function test_export_includes_navigation_urls(): void {
        $this->resetAfterTest();
        global $PAGE;

        $summary = ['total' => 1, 'success' => 1, 'skipped' => 0, 'error' => 0];
        $page = new result_page(7, 1, 'executed', $summary, 'shift_dates');
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertStringContainsString('courseid=7', $data['dashboardurl']);
        $this->assertStringContainsString('index.php', $data['dashboardurl']);
        $this->assertStringContainsString('courseid=7', $data['manageurl']);
        $this->assertStringContainsString('manage.php', $data['manageurl']);
    }
}
