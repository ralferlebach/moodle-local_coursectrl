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
 * Tests for rollback_manager.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\manager;

use local_coursectrl\local\persistent\batch;
use local_coursectrl\local\persistent\snapshot;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\manager\rollback_manager::class)]
/**
 * Unit tests for rollback_manager.
 *
 * These tests use the real database (advanced_testcase) to exercise the full
 * persistence stack without mocking Moodle's $DB.
 *
 * @covers \local_coursectrl\manager\rollback_manager
 */
final class rollback_manager_test extends \advanced_testcase {
    /**
     * Create a batch record and return its id.
     *
     * @param int    $courseid Course id.
     * @param string $status   Batch status constant.
     * @return int Batch id.
     */
    private function create_batch(int $courseid, string $status = batch::STATUS_EXECUTED): int {
        global $DB;
        return (int) $DB->insert_record('local_coursectrl_batch', (object)[
            'courseid' => $courseid,
            'userid' => 2,
            'action' => 'shift_dates',
            'payloadjson' => '{}',
            'status' => $status,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Create a snapshot record for a batch.
     *
     * @param int    $batchid   Batch id.
     * @param int    $entityid  Entity (cmid) id.
     * @param string $component Frankenstyle component.
     * @param array  $state     State to serialise.
     */
    private function create_snapshot(
        int $batchid,
        int $entityid,
        string $component,
        array $state = []
    ): void {
        global $DB;
        $DB->insert_record('local_coursectrl_snapshot', (object)[
            'batchid' => $batchid,
            'entitytype' => 'cm',
            'entityid' => $entityid,
            'component' => $component,
            'statejson' => json_encode($state),
            'timecreated' => time(),
        ]);
    }

    /**
     * get_course_batches returns empty array when no batches exist.
     * @covers \local_coursectrl\manager\rollback_manager
     */
    public function test_get_course_batches_empty(): void {
        $this->resetAfterTest();
        $manager = new rollback_manager();
        $this->assertSame([], $manager->get_course_batches(999));
    }

    /**
     * get_course_batches returns batches for the course newest-first.
     * @covers \local_coursectrl\manager\rollback_manager
     */
    public function test_get_course_batches_returns_newest_first(): void {
        $this->resetAfterTest();
        $courseid = 1;
        $id1 = $this->create_batch($courseid);
        sleep(1);
        $id2 = $this->create_batch($courseid);

        $manager = new rollback_manager();
        $batches = $manager->get_course_batches($courseid);

        $this->assertCount(2, $batches);
        // Newest first.
        $this->assertSame($id2, $batches[0]['id']);
        $this->assertSame($id1, $batches[1]['id']);
    }

    /**
     * can_rollback is true only for executed batches that have snapshots.
     * @covers \local_coursectrl\manager\rollback_manager
     */
    public function test_can_rollback_flag(): void {
        $this->resetAfterTest();
        $courseid = 1;
        $batchid = $this->create_batch($courseid, batch::STATUS_EXECUTED);
        $this->create_snapshot($batchid, 10, 'mod_assign', ['duedate' => 1000]);

        $manager = new rollback_manager();
        $batches = $manager->get_course_batches($courseid);

        $this->assertTrue($batches[0]['has_snapshots']);
        $this->assertTrue($batches[0]['can_rollback']);
    }

    /**
     * can_rollback is false for a batch without snapshots.
     * @covers \local_coursectrl\manager\rollback_manager
     */
    public function test_no_snapshots_cannot_rollback(): void {
        $this->resetAfterTest();
        $batchid = $this->create_batch(1, batch::STATUS_EXECUTED);
        unset($batchid);

        $manager = new rollback_manager();
        $batches = $manager->get_course_batches(1);

        $this->assertFalse($batches[0]['has_snapshots']);
        $this->assertFalse($batches[0]['can_rollback']);
    }

    /**
     * rollback_batch returns error result for non-existent batch.
     * @covers \local_coursectrl\manager\rollback_manager
     */
    public function test_rollback_nonexistent_batch_returns_error(): void {
        $this->resetAfterTest();
        $manager = new rollback_manager();
        $result = $manager->rollback_batch(999999, 2);
        $this->assertFalse($result['success']);
        $this->assertSame('batch_not_found', $result['error']);
    }

    /**
     * rollback_batch rejects batches not in 'executed' status.
     * @covers \local_coursectrl\manager\rollback_manager
     */
    public function test_rollback_non_executed_batch_rejected(): void {
        $this->resetAfterTest();
        $batchid = $this->create_batch(1, batch::STATUS_ROLLED_BACK);
        $manager = new rollback_manager();
        $result = $manager->rollback_batch($batchid, 2);
        $this->assertFalse($result['success']);
        $this->assertSame('batch_not_rollbackable', $result['error']);
    }

    /**
     * rollback_batch returns error when no snapshots exist for the batch.
     * @covers \local_coursectrl\manager\rollback_manager
     */
    public function test_rollback_with_no_snapshots_returns_error(): void {
        $this->resetAfterTest();
        $batchid = $this->create_batch(1, batch::STATUS_EXECUTED);
        $manager = new rollback_manager();
        $result = $manager->rollback_batch($batchid, 2);
        $this->assertFalse($result['success']);
        $this->assertSame('no_snapshots', $result['error']);
    }

    /**
     * rollback_batch returns error-item when no adapter is registered for a component.
     * @covers \local_coursectrl\manager\rollback_manager
     */
    public function test_rollback_missing_adapter_recorded_as_error(): void {
        $this->resetAfterTest();
        $batchid = $this->create_batch(1, batch::STATUS_EXECUTED);
        $this->create_snapshot($batchid, 10, 'mod_nonexistent', ['foo' => 1]);

        $manager = new rollback_manager();
        $result = $manager->rollback_batch($batchid, 2);

        // Failed because no adapter exists.
        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['restored']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame('error', $result['items'][0]['status']);
        $this->assertSame('no_adapter', $result['items'][0]['message']);
    }
}
