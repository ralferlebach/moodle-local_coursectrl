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
 * Integration tests for the audit-log and rollback (undo) pipeline.
 *
 * Covers the full cycle: batch_manager::execute() → snapshot creation →
 * rollback_manager::rollback_batch() → field-value restoration.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl;

use local_coursectrl\local\persistent\batch;
use local_coursectrl\manager\batch_manager;
use local_coursectrl\manager\rollback_manager;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\manager\batch_manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\manager\rollback_manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\persistent\batch::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\persistent\snapshot::class)]
/**
 * Tests for the full execute → snapshot → rollback pipeline.
 *
 * @covers \local_coursectrl\manager\batch_manager
 * @covers \local_coursectrl\manager\rollback_manager
 * @covers \local_coursectrl\local\persistent\batch
 * @covers \local_coursectrl\local\persistent\snapshot
 */
final class fixture_logging_rollback_test extends \advanced_testcase {
    /** @var int 2026-06-01 00:00 UTC */
    private const T_BASE = 1748736000;

    /** @var int Seven days in seconds. */
    private const WEEK = 604800;

    // Fixture helpers.

    /**
     * Create a course containing an Assignment and a Quiz with known due dates.
     *
     * @return array{courseid:int, assigncmid:int, quizcmid:int, assigniid:int, quiziid:int}
     */
    private function create_dated_course(): array {
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $gen = $this->getDataGenerator();
        $assign = $gen->get_plugin_generator('mod_assign')->create_instance([
            'course' => $course->id,
            'name' => 'LogAssign',
            'duedate' => self::T_BASE + self::WEEK,
            'completion' => 2,
        ]);
        $quiz = $gen->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $course->id,
            'name' => 'LogQuiz',
            'timeopen' => self::T_BASE,
            'timeclose' => self::T_BASE + self::WEEK,
            'completion' => 2,
        ]);
        return [
            'courseid' => (int) $course->id,
            'assigncmid' => (int) $assign->cmid,
            'quizcmid' => (int) $quiz->cmid,
            'assigniid' => (int) $assign->id,
            'quiziid' => (int) $quiz->id,
        ];
    }

    // Logging: batch persistence.

    /**
     * execute() creates a batch record with status 'executed'.
     * @covers \local_coursectrl\manager\batch_manager
     * @covers \local_coursectrl\manager\rollback_manager
     * @covers \local_coursectrl\local\persistent\batch
     * @covers \local_coursectrl\local\persistent\snapshot
     */
    public function test_execute_creates_batch_record(): void {
        $this->resetAfterTest();
        global $DB;
        $data = $this->create_dated_course();
        $admin = get_admin();
        $batchmgr = new batch_manager();

        $batchid = $batchmgr->execute(
            $data['courseid'],
            'shift_dates',
            ['delta' => self::WEEK],
            [$data['assigncmid']],
            $admin->id
        );

        $this->assertGreaterThan(0, $batchid);
        $rec = $DB->get_record('local_coursectrl_batch', ['id' => $batchid]);
        $this->assertNotFalse($rec, 'Batch row should be created');
        $this->assertSame(batch::STATUS_EXECUTED, $rec->status);
        $this->assertSame('shift_dates', $rec->action);
        $this->assertSame($data['courseid'], (int)$rec->courseid);
    }

    /**
     * execute() creates batch-item rows for every affected activity.
     * @covers \local_coursectrl\manager\batch_manager
     * @covers \local_coursectrl\manager\rollback_manager
     * @covers \local_coursectrl\local\persistent\batch
     * @covers \local_coursectrl\local\persistent\snapshot
     */
    public function test_execute_creates_batch_items(): void {
        $this->resetAfterTest();
        global $DB;
        $data = $this->create_dated_course();
        $admin = get_admin();
        $batchmgr = new batch_manager();

        $batchid = $batchmgr->execute(
            $data['courseid'],
            'shift_dates',
            ['delta' => self::WEEK],
            [$data['assigncmid'], $data['quizcmid']],
            $admin->id
        );

        $items = $DB->get_records('local_coursectrl_batch_item', ['batchid' => $batchid]);
        $this->assertNotEmpty($items, 'Batch items should be created for each CM');
    }

    /**
     * execute() creates snapshot rows that enable rollback.
     * @covers \local_coursectrl\manager\batch_manager
     * @covers \local_coursectrl\manager\rollback_manager
     * @covers \local_coursectrl\local\persistent\batch
     * @covers \local_coursectrl\local\persistent\snapshot
     */
    public function test_execute_creates_snapshots(): void {
        $this->resetAfterTest();
        global $DB;
        $data = $this->create_dated_course();
        $admin = get_admin();
        $batchmgr = new batch_manager();

        $batchid = $batchmgr->execute(
            $data['courseid'],
            'shift_dates',
            ['delta' => self::WEEK],
            [$data['assigncmid']],
            $admin->id
        );

        $snaps = $DB->get_records('local_coursectrl_snapshot', ['batchid' => $batchid]);
        $this->assertNotEmpty($snaps, 'Snapshots must be created for rollback capability');
        $snap = reset($snaps);
        $state = json_decode($snap->statejson, true);
        $this->assertIsArray($state, 'Snapshot statejson should be valid JSON');
        $this->assertArrayHasKey('duedate', $state, 'Snapshot should contain duedate');
    }

    /**
     * get_course_batches() returns the batch list for a course.
     * @covers \local_coursectrl\manager\batch_manager
     * @covers \local_coursectrl\manager\rollback_manager
     * @covers \local_coursectrl\local\persistent\batch
     * @covers \local_coursectrl\local\persistent\snapshot
     */
    public function test_get_course_batches_returns_batch(): void {
        $this->resetAfterTest();
        $data = $this->create_dated_course();
        $admin = get_admin();
        $batchmgr = new batch_manager();

        $batchmgr->execute(
            $data['courseid'],
            'shift_dates',
            ['delta' => self::WEEK],
            [$data['assigncmid']],
            $admin->id
        );

        $rollbackmgr = new rollback_manager();
        $batches = $rollbackmgr->get_course_batches($data['courseid']);
        $this->assertCount(1, $batches, 'One batch should be listed');
        $this->assertTrue($batches[0]['has_snapshots'], 'Batch should report has_snapshots');
        $this->assertTrue($batches[0]['can_rollback'], 'Batch should be rollbackable');
    }

    // Rollback.

    /**
     * rollback_batch() restores the original duedate value.
     * @covers \local_coursectrl\manager\batch_manager
     * @covers \local_coursectrl\manager\rollback_manager
     * @covers \local_coursectrl\local\persistent\batch
     * @covers \local_coursectrl\local\persistent\snapshot
     */
    public function test_rollback_restores_original_duedate(): void {
        $this->resetAfterTest();
        global $DB;
        $data = $this->create_dated_course();
        $admin = get_admin();
        $originalduedate = self::T_BASE + self::WEEK;

        $batchmgr = new batch_manager();
        $batchid = $batchmgr->execute(
            $data['courseid'],
            'shift_dates',
            ['delta' => self::WEEK],
            [$data['assigncmid']],
            $admin->id
        );

        // Verify the date was actually shifted before rolling back.
        $shiftedduedate = (int) $DB->get_field('assign', 'duedate', ['id' => $data['assigniid']]);
        $this->assertSame($originalduedate + self::WEEK, $shiftedduedate, 'Shift should have been applied');

        // Execute rollback.
        $rollbackmgr = new rollback_manager();
        $result = $rollbackmgr->rollback_batch($data['courseid'], $batchid, $admin->id);

        $this->assertTrue($result['success'], 'Rollback should succeed: ' . ($result['error'] ?? ''));
        $this->assertSame(0, $result['failed'], 'No failures in rollback');

        $restoreddate = (int) $DB->get_field('assign', 'duedate', ['id' => $data['assigniid']]);
        $this->assertSame($originalduedate, $restoreddate, 'duedate should be restored to original');
    }

    /**
     * rollback_batch() restores both quiz timeopen and timeclose.
     * @covers \local_coursectrl\manager\batch_manager
     * @covers \local_coursectrl\manager\rollback_manager
     * @covers \local_coursectrl\local\persistent\batch
     * @covers \local_coursectrl\local\persistent\snapshot
     */
    public function test_rollback_restores_quiz_times(): void {
        $this->resetAfterTest();
        global $DB;
        $data = $this->create_dated_course();
        $admin = get_admin();

        $batchmgr = new batch_manager();
        $batchid = $batchmgr->execute(
            $data['courseid'],
            'shift_dates',
            ['delta' => self::WEEK],
            [$data['quizcmid']],
            $admin->id
        );

        $rollbackmgr = new rollback_manager();
        $result = $rollbackmgr->rollback_batch($data['courseid'], $batchid, $admin->id);

        $this->assertTrue($result['success']);
        $quiz = $DB->get_record('quiz', ['id' => $data['quiziid']]);
        $this->assertSame(self::T_BASE, (int)$quiz->timeopen, 'timeopen should be restored');
        $this->assertSame(self::T_BASE + self::WEEK, (int)$quiz->timeclose, 'timeclose should be restored');
    }

    /**
     * After rollback the batch is no longer in 'executed' state and cannot be rolled back again.
     * @covers \local_coursectrl\manager\batch_manager
     * @covers \local_coursectrl\manager\rollback_manager
     * @covers \local_coursectrl\local\persistent\batch
     * @covers \local_coursectrl\local\persistent\snapshot
     */
    public function test_rollback_marks_batch_not_re_rollbackable(): void {
        $this->resetAfterTest();
        global $DB;
        $data = $this->create_dated_course();
        $admin = get_admin();

        $batchmgr = new batch_manager();
        $batchid = $batchmgr->execute(
            $data['courseid'],
            'shift_dates',
            ['delta' => self::WEEK],
            [$data['assigncmid']],
            $admin->id
        );

        $rollbackmgr = new rollback_manager();
        $rollbackmgr->rollback_batch($data['courseid'], $batchid, $admin->id);

        $batches = $rollbackmgr->get_course_batches($data['courseid']);
        $rolledback = array_filter($batches, fn($b) => $b['id'] === $batchid);
        $b = reset($rolledback);
        $this->assertFalse($b['can_rollback'], 'After rollback, batch should not be rollbackable again');
    }

    /**
     * Rollback with a non-existent batch ID returns an error result.
     * @covers \local_coursectrl\manager\batch_manager
     * @covers \local_coursectrl\manager\rollback_manager
     * @covers \local_coursectrl\local\persistent\batch
     * @covers \local_coursectrl\local\persistent\snapshot
     */
    public function test_rollback_nonexistent_batch_returns_error(): void {
        $this->resetAfterTest();
        $admin = get_admin();
        $rollbackmgr = new rollback_manager();
        $result = $rollbackmgr->rollback_batch(0, 99999, $admin->id);
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['error']);
    }

    /**
     * Two consecutive shift batches can each be rolled back independently.
     * @covers \local_coursectrl\manager\batch_manager
     * @covers \local_coursectrl\manager\rollback_manager
     * @covers \local_coursectrl\local\persistent\batch
     * @covers \local_coursectrl\local\persistent\snapshot
     */
    public function test_two_batches_rollback_independently(): void {
        $this->resetAfterTest();
        global $DB;
        $data = $this->create_dated_course();
        $admin = get_admin();
        $original = self::T_BASE + self::WEEK;

        $batchmgr = new batch_manager();
        $batchid1 = $batchmgr->execute(
            $data['courseid'],
            'shift_dates',
            ['delta' => self::WEEK],
            [$data['assigncmid']],
            $admin->id
        );
        $batchid2 = $batchmgr->execute(
            $data['courseid'],
            'shift_dates',
            ['delta' => self::WEEK],
            [$data['assigncmid']],
            $admin->id
        );

        // Roll back the second batch; value should return to +1 week.
        $rollbackmgr = new rollback_manager();
        $r2 = $rollbackmgr->rollback_batch($data['courseid'], $batchid2, $admin->id);
        $this->assertTrue($r2['success']);

        $afterr2 = (int) $DB->get_field('assign', 'duedate', ['id' => $data['assigniid']]);
        $this->assertSame(
            $original + self::WEEK,
            $afterr2,
            'After rolling back batch 2, value should be back to +1 week'
        );

        // Roll back the first batch; value should return to the original.
        $r1 = $rollbackmgr->rollback_batch($data['courseid'], $batchid1, $admin->id);
        $this->assertTrue($r1['success']);
        $afterr1 = (int) $DB->get_field('assign', 'duedate', ['id' => $data['assigniid']]);
        $this->assertSame($original, $afterr1, 'After rolling back batch 1, value should be fully restored');
    }

    /**
     * Adapter-backed date shift with completionexpected set: full rollback.
     *
     * Verifies that rolling back an adapter-based shift batch restores both
     * the adapter-owned date fields (duedate) and the CM-level field
     * completionexpected to their original values.
     *
     * @covers \local_coursectrl\manager\batch_manager
     * @covers \local_coursectrl\manager\rollback_manager
     * @covers \local_coursectrl\local\contract\shift_dates_executor
     */
    public function test_rollback_restores_completionexpected_from_adapter_shift(): void {
        global $DB;
        $this->resetAfterTest();

        $origdue = self::T_BASE + self::WEEK;
        $origce = self::T_BASE + (int) (self::WEEK * 0.5);
        $delta = self::WEEK;

        // Enable completion tracking so completionexpected is meaningful.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $assign = $this->getDataGenerator()
            ->get_plugin_generator('mod_assign')
            ->create_instance([
                'course'      => $course->id,
                'duedate'     => $origdue,
                'completion'  => 2,
            ]);
        $cmid = (int) $assign->cmid;

        // Set completionexpected via direct DB write to simulate a prior shift.
        $DB->set_field('course_modules', 'completionexpected', $origce, ['id' => $cmid]);

        // Execute the date shift.
        $admin = get_admin();
        $batchmgr = new batch_manager();
        $batchid = $batchmgr->execute(
            (int) $course->id,
            'shift_dates',
            ['delta' => $delta],
            [$cmid],
            $admin->id
        );

        // Verify both fields are shifted by the delta.
        $shifteddue = (int) $DB->get_field('assign', 'duedate', ['id' => $assign->id]);
        $shiftedce = (int) $DB->get_field('course_modules', 'completionexpected', ['id' => $cmid]);
        $this->assertSame($origdue + $delta, $shifteddue, 'duedate must be shifted');
        $this->assertSame($origce + $delta, $shiftedce, 'completionexpected must be shifted');

        // Rollback.
        $rollbackmgr = new rollback_manager();
        $result = $rollbackmgr->rollback_batch((int) $course->id, $batchid, $admin->id);
        $this->assertTrue($result['success'], 'Rollback must succeed');

        // Verify both fields are back to their original values.
        $restoreddue = (int) $DB->get_field('assign', 'duedate', ['id' => $assign->id]);
        $restoredce = (int) $DB->get_field('course_modules', 'completionexpected', ['id' => $cmid]);
        $this->assertSame($origdue, $restoreddue, 'duedate must be restored');
        $this->assertSame($origce, $restoredce, 'completionexpected must be restored');

        // No item must fail with a no_adapter error.
        foreach ($result['items'] as $item) {
            $this->assertNotSame(
                'no_adapter',
                $item['message'],
                'No item should fail with no_adapter'
            );
        }
    }
}
