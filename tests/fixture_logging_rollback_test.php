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
 * Integration tests: Logging und Rollback (Undo).
 *
 * Prüft den vollständigen Zyklus: batch_manager::execute → Snapshot-Erzeugung →
 * rollback_manager::rollback_batch → Werte-Wiederherstellung.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl;

use local_coursectrl\local\persistent\batch;
use local_coursectrl\manager\batch_manager;
use local_coursectrl\manager\rollback_manager;

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

    /** @var int 7 Tage in Sekunden */
    private const WEEK = 604800;

    // Helpers.

    /**
     * Erstelle Kurs mit Assign und Quiz.
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

    // Logging: Batch-Persistierung.

    /**
     * execute() erzeugt eine Batch-Zeile mit Status 'executed'.
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
     * execute() erzeugt Batch-Item-Zeilen für jede bearbeitete Aktivität.
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
     * execute() erzeugt Snapshot-Zeilen für den Rollback.
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
     * rollback_manager::get_course_batches gibt Batches für Kurs zurück.
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
     * rollback_batch stellt den ursprünglichen duedate-Wert wieder her.
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

        // Datum wurde verschoben.
        $shiftedduedate = (int) $DB->get_field('assign', 'duedate', ['id' => $data['assigniid']]);
        $this->assertSame($originalduedate + self::WEEK, $shiftedduedate, 'Shift should have been applied');

        // Rollback.
        $rollbackmgr = new rollback_manager();
        $result = $rollbackmgr->rollback_batch($batchid, $admin->id);

        $this->assertTrue($result['success'], 'Rollback should succeed: ' . ($result['error'] ?? ''));
        $this->assertSame(0, $result['failed'], 'No failures in rollback');

        $restoreddate = (int) $DB->get_field('assign', 'duedate', ['id' => $data['assigniid']]);
        $this->assertSame($originalduedate, $restoreddate, 'duedate should be restored to original');
    }

    /**
     * rollback_batch stellt quiz-Zeiten korrekt wieder her.
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
        $result = $rollbackmgr->rollback_batch($batchid, $admin->id);

        $this->assertTrue($result['success']);
        $quiz = $DB->get_record('quiz', ['id' => $data['quiziid']]);
        $this->assertSame(self::T_BASE, (int)$quiz->timeopen, 'timeopen should be restored');
        $this->assertSame(self::T_BASE + self::WEEK, (int)$quiz->timeclose, 'timeclose should be restored');
    }

    /**
     * Nach Rollback ist Batch-Status NICHT mehr 'executed' (kein doppelter Rollback).
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
        $rollbackmgr->rollback_batch($batchid, $admin->id);

        $batches = $rollbackmgr->get_course_batches($data['courseid']);
        $rolledback = array_filter($batches, fn($b) => $b['id'] === $batchid);
        $b = reset($rolledback);
        $this->assertFalse($b['can_rollback'], 'After rollback, batch should not be rollbackable again');
    }

    /**
     * Rollback auf nicht existierende Batch-ID liefert Fehler.
     */
    public function test_rollback_nonexistent_batch_returns_error(): void {
        $this->resetAfterTest();
        $admin = get_admin();
        $rollbackmgr = new rollback_manager();
        $result = $rollbackmgr->rollback_batch(99999, $admin->id);
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['error']);
    }

    /**
     * Mehrstufige Verschiebung: zwei aufeinanderfolgende Batches sind unabhängig rollbackbar.
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

        // Rollback des zweiten Batch → zurück auf +1 Woche.
        $rollbackmgr = new rollback_manager();
        $r2 = $rollbackmgr->rollback_batch($batchid2, $admin->id);
        $this->assertTrue($r2['success']);

        $afterr2 = (int) $DB->get_field('assign', 'duedate', ['id' => $data['assigniid']]);
        $this->assertSame(
            $original + self::WEEK,
            $afterr2,
            'After rolling back batch 2, value should be back to +1 week'
        );

        // Rollback des ersten Batch → zurück auf original.
        $r1 = $rollbackmgr->rollback_batch($batchid1, $admin->id);
        $this->assertTrue($r1['success']);
        $afterr1 = (int) $DB->get_field('assign', 'duedate', ['id' => $data['assigniid']]);
        $this->assertSame($original, $afterr1, 'After rolling back batch 1, value should be fully restored');
    }
}
