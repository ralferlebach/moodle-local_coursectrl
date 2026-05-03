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
 * Tests for the history_page renderable.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\output\history_page::class)]
/**
 * Unit tests for history_page::export_for_template().
 *
 * @covers \local_coursectrl\output\history_page
 */
final class history_page_test extends \advanced_testcase {
    /**
     * Required scalar keys are always present with no batches.
     * @covers \local_coursectrl\output\history_page
     */
    public function test_export_has_required_keys_when_empty(): void {
        $this->resetAfterTest();
        global $PAGE;
        $page = new history_page(1);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $requiredkeys = [
            'courseid',
            'sesskey',
            'batchrows',
            'hasbatchrows',
            'batchcount',
            'hasrollbackresult',
            'dashboardurl',
            'rollbackurl',
        ];
        foreach ($requiredkeys as $key) {
            $this->assertArrayHasKey($key, $data, "Missing key: $key");
        }
        $this->assertFalse($data['hasbatchrows']);
        $this->assertSame(0, $data['batchcount']);
    }

    /**
     * Without a rollback result hasrollbackresult is false.
     * @covers \local_coursectrl\output\history_page
     */
    public function test_no_rollback_result_by_default(): void {
        $this->resetAfterTest();
        global $PAGE;
        $page = new history_page(1);
        $data = $page->export_for_template($PAGE->get_renderer('core'));
        $this->assertFalse($data['hasrollbackresult']);
    }

    /**
     * A successful rollback result is surfaced correctly.
     * @covers \local_coursectrl\output\history_page
     */
    public function test_successful_rollback_result_exported(): void {
        $this->resetAfterTest();
        global $PAGE;
        $rollbackresult = [
            'success' => true,
            'error' => '',
            'restored' => 3,
            'failed' => 0,
            'items' => [
                ['entityid' => 1, 'component' => 'mod_assign', 'status' => 'restored', 'message' => ''],
                ['entityid' => 2, 'component' => 'mod_assign', 'status' => 'restored', 'message' => ''],
                ['entityid' => 3, 'component' => 'mod_quiz', 'status' => 'restored', 'message' => ''],
            ],
        ];
        $page = new history_page(1, $rollbackresult);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['hasrollbackresult']);
        $this->assertTrue($data['rollbacksuccess']);
        $this->assertSame(3, $data['rollbackrestored']);
        $this->assertSame(0, $data['rollbackfailed']);
        $this->assertCount(3, $data['rollbackitems']);
        $this->assertTrue($data['rollbackitems'][0]['isrestored']);
    }

    /**
     * A failed rollback result is surfaced with error info.
     * @covers \local_coursectrl\output\history_page
     */
    public function test_failed_rollback_result_exported(): void {
        $this->resetAfterTest();
        global $PAGE;
        $rollbackresult = [
            'success' => false,
            'error' => 'no_adapter',
            'restored' => 0,
            'failed' => 1,
            'items' => [
                ['entityid' => 5, 'component' => 'mod_unknown', 'status' => 'error', 'message' => 'no_adapter'],
            ],
        ];
        $page = new history_page(1, $rollbackresult);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['hasrollbackresult']);
        $this->assertFalse($data['rollbacksuccess']);
        $this->assertSame('no_adapter', $data['rollbackerror']);
        $this->assertSame(1, $data['rollbackfailed']);
        $this->assertTrue($data['rollbackitems'][0]['iserror']);
    }

    /**
     * Batch list is populated from the database.
     * @covers \local_coursectrl\output\history_page
     */
    public function test_batches_loaded_from_db(): void {
        $this->resetAfterTest();
        global $DB, $PAGE;

        $courseid = 1;
        $DB->insert_record('local_coursectrl_batch', (object)[
            'courseid' => $courseid,
            'userid' => 2,
            'action' => 'shift_dates',
            'payloadjson' => '{}',
            'status' => 'executed',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $page = new history_page($courseid);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['hasbatchrows']);
        $this->assertSame(1, $data['batchcount']);
        $this->assertSame('shift_dates', $data['batchrows'][0]['action']);
        $this->assertTrue($data['batchrows'][0]['status_executed']);
    }
}
