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
     * Build a typical preview result array with two changes, one skip.
     *
     * @return array{changes: preview_change[], skipped: array, errors: array, summary: array}
     */
    private function build_result(): array {
        $changes = [
            101 => new preview_change(101, 'mod_assign', 'Homework 1', [
                'duedate' => [
                    'old' => 1700000000,
                    'new' => 1700086400,
                    'shifted' => true,
                ],
                'cutoffdate' => [
                    'old' => 0,
                    'new' => 0,
                    'shifted' => false,
                    'reason' => 'unset',
                ],
            ]),
            102 => new preview_change(102, 'mod_quiz', 'Quiz 1', [
                'timeopen' => [
                    'old' => 1700000000,
                    'new' => 1700086400,
                    'shifted' => true,
                ],
                'timeclose' => [
                    'old' => 1700100000,
                    'new' => 1700186400,
                    'shifted' => true,
                ],
            ]),
        ];
        $skipped = [
            ['cmid' => 100, 'reason' => 'no_adapter'],
        ];
        return [
            'action' => 'shift_dates',
            'payload' => ['delta' => 86400],
            'changes' => $changes,
            'skipped' => $skipped,
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
     * Context must carry action label and delta label.
     */
    public function test_export_includes_action_info(): void {
        $this->resetAfterTest();
        global $PAGE;

        $result = $this->build_result();
        $page = new preview_page(1, 'shift_dates', ['delta' => 86400], [101, 102], $result);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertSame('shift_dates', $data['action']);
        $this->assertNotEmpty($data['actionlabel']);
        $this->assertStringContainsString('1', $data['deltalabel']);
    }

    /**
     * Summary counts must match the preview result.
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
     * Change rows must carry formatted date values and first-field flag.
     */
    public function test_export_formats_change_rows(): void {
        $this->resetAfterTest();
        global $PAGE;

        $result = $this->build_result();
        $page = new preview_page(1, 'shift_dates', ['delta' => 86400], [101, 102], $result);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['hasrows']);
        $this->assertCount(2, $data['rows']);

        $assign = $data['rows'][0];
        $this->assertSame(101, $assign['cmid']);
        $this->assertSame('mod_assign', $assign['component']);
        $this->assertSame('Homework 1', $assign['name']);
        $this->assertSame(2, $assign['fieldcount']);

        // First field must have the 'first' flag.
        $this->assertTrue($assign['fields'][0]['first'] ?? false);
        $this->assertArrayNotHasKey('first', $assign['fields'][1]);
    }

    /**
     * Shifted fields must be flagged, unset fields must show reason.
     */
    public function test_export_field_status_flags(): void {
        $this->resetAfterTest();
        global $PAGE;

        $result = $this->build_result();
        $page = new preview_page(1, 'shift_dates', ['delta' => 86400], [101, 102], $result);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $assignfields = $data['rows'][0]['fields'];

        // duedate: shifted.
        $duedate = $assignfields[0];
        $this->assertSame('duedate', $duedate['fieldname']);
        $this->assertTrue($duedate['shifted']);
        $this->assertFalse($duedate['isunset']);
        $this->assertNotSame('–', $duedate['oldvalue']);
        $this->assertNotSame('–', $duedate['newvalue']);

        // cutoffdate: unset.
        $cutoff = $assignfields[1];
        $this->assertSame('cutoffdate', $cutoff['fieldname']);
        $this->assertFalse($cutoff['shifted']);
        $this->assertTrue($cutoff['isunset']);
        $this->assertSame('–', $cutoff['oldvalue']);
        $this->assertSame('–', $cutoff['newvalue']);
    }

    /**
     * Skipped items must be present in the context.
     */
    public function test_export_includes_skipped(): void {
        $this->resetAfterTest();
        global $PAGE;

        $result = $this->build_result();
        $page = new preview_page(1, 'shift_dates', ['delta' => 86400], [101, 102], $result);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['hasskipped']);
        $this->assertCount(1, $data['skipped']);
        $this->assertSame(100, $data['skipped'][0]['cmid']);
        $this->assertSame('no_adapter', $data['skipped'][0]['reason']);
    }

    /**
     * canexecute must be true when there are changes and no errors.
     */
    public function test_export_canexecute_true_when_changes_no_errors(): void {
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
    public function test_export_canexecute_false_with_errors(): void {
        $this->resetAfterTest();
        global $PAGE;

        $result = $this->build_result();
        $result['errors'] = [['cmid' => 101, 'code' => 'test_error', 'message' => 'broken']];
        $result['summary']['errors'] = 1;
        $page = new preview_page(1, 'shift_dates', ['delta' => 86400], [101, 102], $result);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertFalse($data['canexecute']);
        $this->assertTrue($data['haserrors']);
    }

    /**
     * Hidden fields in the execute form must carry correct JSON payloads.
     */
    public function test_export_includes_form_hidden_fields(): void {
        $this->resetAfterTest();
        global $PAGE;

        $result = $this->build_result();
        $cmids = [101, 102];
        $payload = ['delta' => 86400];
        $page = new preview_page(1, 'shift_dates', $payload, $cmids, $result);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertSame('{"delta":86400}', $data['payloadjson']);
        $this->assertSame('[101,102]', $data['cmidsjson']);
        $this->assertStringContainsString('execute.php', $data['executeurl']);
    }

    /**
     * Delta label for zero must show '0'.
     */
    public function test_export_delta_label_zero(): void {
        $this->resetAfterTest();
        global $PAGE;

        $result = $this->build_result();
        $result['payload'] = ['delta' => 0];
        $page = new preview_page(1, 'shift_dates', ['delta' => 0], [101], $result);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertSame('0', $data['deltalabel']);
    }
}
