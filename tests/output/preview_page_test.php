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
 * Tests for the preview_page renderable.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

use local_coursectrl\local\dto\preview_change;

/**
 * Unit tests for preview_page::export_for_template().
 *
 * @covers \local_coursectrl\output\preview_page
 */
final class preview_page_test extends \advanced_testcase {
    /**
     * Build a standard preview result for tests.
     *
     * @return array preview_manager-style result.
     */
    private function build_result(): array {
        $changes = [
            101 => new preview_change(101, 'mod_assign', 'Homework 1', [
                'duedate' => ['old' => 1700000000, 'new' => 1700086400, 'shifted' => true],
                'cutoffdate' => ['old' => 0, 'new' => 0, 'shifted' => false, 'reason' => 'unset'],
            ]),
            102 => new preview_change(102, 'mod_quiz', 'Quiz 1', [
                'timeopen' => ['old' => 1700000000, 'new' => 1700086400, 'shifted' => true],
                'timeclose' => ['old' => 1700100000, 'new' => 1700186400, 'shifted' => true],
            ]),
        ];
        return [
            'action' => 'shift_dates',
            'payload' => ['delta' => 86400],
            'changes' => $changes,
            'skipped' => [
                ['cmid' => 200, 'reason' => 'no_adapter'],
            ],
            'errors' => [],
            'summary' => [
                'total' => 3,
                'changes' => 2,
                'skipped' => 1,
                'errors' => 0,
            ],
        ];
    }

    /**
     * Summary counts must be passed through correctly.
     */
    public function test_export_includes_summary(): void {
        $this->resetAfterTest();
        global $PAGE;

        $result = $this->build_result();
        $page = new preview_page(1, 'shift_dates', ['delta' => 86400], [101, 102], $result);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertSame(3, $data['summary_total']);
        $this->assertSame(2, $data['summary_changes']);
        $this->assertSame(1, $data['summary_skipped']);
        $this->assertSame(0, $data['summary_errors']);
    }

    /**
     * canexecute must be true when changes > 0 and errors == 0.
     */
    public function test_canexecute_true_when_changes_and_no_errors(): void {
        $this->resetAfterTest();
        global $PAGE;

        $result = $this->build_result();
        $page = new preview_page(1, 'shift_dates', ['delta' => 86400], [101, 102], $result);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['canexecute']);
    }

    /**
     * canexecute must be false when there are errors.
     */
    public function test_canexecute_false_with_errors(): void {
        $this->resetAfterTest();
        global $PAGE;

        $result = $this->build_result();
        $result['errors'] = [['cmid' => 101, 'code' => 'invalid_delta', 'message' => 'bad']];
        $result['summary']['errors'] = 1;

        $page = new preview_page(1, 'shift_dates', ['delta' => 86400], [101], $result);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertFalse($data['canexecute']);
        $this->assertTrue($data['haserrors']);
    }

    /**
     * canexecute must be false when there are no changes.
     */
    public function test_canexecute_false_without_changes(): void {
        $this->resetAfterTest();
        global $PAGE;

        $result = [
            'action' => 'shift_dates',
            'payload' => ['delta' => 0],
            'changes' => [],
            'skipped' => [],
            'errors' => [],
            'summary' => ['total' => 0, 'changes' => 0, 'skipped' => 0, 'errors' => 0],
        ];

        $page = new preview_page(1, 'shift_dates', ['delta' => 0], [], $result);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertFalse($data['canexecute']);
        $this->assertFalse($data['hasrows']);
    }

    /**
     * Each change must be expanded into per-field rows with formatted dates.
     */
    public function test_export_formats_fields_with_dates(): void {
        $this->resetAfterTest();
        global $PAGE;

        $result = $this->build_result();
        $page = new preview_page(1, 'shift_dates', ['delta' => 86400], [101, 102], $result);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['hasrows']);
        $this->assertCount(2, $data['rows']);

        // First row: assign with 2 fields.
        $assignrow = $data['rows'][0];
        $this->assertSame(101, $assignrow['cmid']);
        $this->assertSame('Homework 1', $assignrow['name']);
        $this->assertCount(2, $assignrow['fields']);
        $this->assertSame(2, $assignrow['fieldcount']);

        // First field must have 'first' flag.
        $this->assertTrue($assignrow['fields'][0]['first'] ?? false);
        $this->assertArrayNotHasKey('first', $assignrow['fields'][1]);

        // duedate must be shifted.
        $this->assertSame('duedate', $assignrow['fields'][0]['fieldname']);
        $this->assertTrue($assignrow['fields'][0]['shifted']);
        $this->assertNotSame('–', $assignrow['fields'][0]['oldvalue']);

        // cutoffdate must be unset.
        $this->assertSame('cutoffdate', $assignrow['fields'][1]['fieldname']);
        $this->assertFalse($assignrow['fields'][1]['shifted']);
        $this->assertTrue($assignrow['fields'][1]['isunset']);
    }

    /**
     * The delta label must include days and a + prefix for positive shifts.
     */
    public function test_export_formats_delta_label(): void {
        $this->resetAfterTest();
        global $PAGE;

        $result = $this->build_result();
        // 2 days + 3 hours = 183600 seconds.
        $delta = 2 * 86400 + 3 * 3600;
        $result['payload']['delta'] = $delta;
        $page = new preview_page(1, 'shift_dates', ['delta' => $delta], [101], $result);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertStringContainsString('+', $data['deltalabel']);
        $this->assertStringContainsString('2', $data['deltalabel']);
        $this->assertStringContainsString('3', $data['deltalabel']);
    }

    /**
     * Skipped items must be passed through to the template.
     */
    public function test_export_includes_skipped(): void {
        $this->resetAfterTest();
        global $PAGE;

        $result = $this->build_result();
        $page = new preview_page(1, 'shift_dates', ['delta' => 86400], [101, 102, 200], $result);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['hasskipped']);
        $this->assertCount(1, $data['skipped']);
        $this->assertSame(200, $data['skipped'][0]['cmid']);
        $this->assertSame('no_adapter', $data['skipped'][0]['reason']);
    }

    /**
     * Hidden fields (payloadjson, cmidsjson) must be valid JSON for the execute form.
     */
    public function test_export_includes_json_hidden_fields(): void {
        $this->resetAfterTest();
        global $PAGE;

        $result = $this->build_result();
        $cmids = [101, 102];
        $payload = ['delta' => 86400];
        $page = new preview_page(1, 'shift_dates', $payload, $cmids, $result);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $decodedpayload = json_decode($data['payloadjson'], true);
        $decodedcmids = json_decode($data['cmidsjson'], true);

        $this->assertSame(86400, $decodedpayload['delta']);
        $this->assertSame([101, 102], $decodedcmids);
    }
}
