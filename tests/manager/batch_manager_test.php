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
 * Behaviour tests for the productive batch_manager.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\manager;

use local_coursectrl\local\persistent\batch;
use local_coursectrl\local\persistent\batch_item;
use local_coursectrl\local\persistent\snapshot;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\manager\batch_manager::class)]
/**
 * Verifies the patch-026 batch_manager pipeline against real adapters
 * and real DB writes: persistents, snapshots, status transitions and
 * the batch_executed event.
 *
 * @covers \local_coursectrl\manager\batch_manager
 */
final class batch_manager_test extends \advanced_testcase {
    /** @var int Reference timestamp used by all date-bearing fixtures. */
    private const BASE_TIME = 1700000000;

    /** @var int One-day delta in seconds. */
    private const ONE_DAY = 86400;

    /**
     * Helper that creates a course with one assign, one quiz and one
     * feedback instance.
     *
     * @return array
     */
    private function create_mixed_course(): array {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->get_plugin_generator('mod_assign')->create_instance([
            'course'   => $course->id,
            'name'     => 'A1',
            'duedate'  => self::BASE_TIME,
        ]);
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course'    => $course->id,
            'name'      => 'Q1',
            'timeopen'  => self::BASE_TIME,
            'timeclose' => self::BASE_TIME + self::ONE_DAY,
        ]);
        $feedback = $this->getDataGenerator()->get_plugin_generator('mod_feedback')->create_instance([
            'course'    => $course->id,
            'name'      => 'F1',
            'timeopen'  => self::BASE_TIME,
            'timeclose' => self::BASE_TIME + self::ONE_DAY,
        ]);
        return [
            'courseid'      => (int)$course->id,
            'assign_cmid'   => (int)$assign->cmid,
            'quiz_cmid'     => (int)$quiz->cmid,
            'feedback_cmid' => (int)$feedback->cmid,
            'assign_iid'    => (int)$assign->id,
            'quiz_iid'      => (int)$quiz->id,
            'feedback_iid'  => (int)$feedback->id,
        ];
    }

    /**
     * The constructor still accepts an injected registry.
     * @covers \local_coursectrl\manager\batch_manager
     */
    public function test_constructor_accepts_injected_registry(): void {
        $registry = new registry([]);
        $manager = new batch_manager($registry);
        $this->assertSame($registry, $manager->get_registry());
    }

    /**
     * A successful single-adapter execute persists a batch row in
     * 'executed' status and returns its id.
     * @covers \local_coursectrl\manager\batch_manager
     */
    public function test_execute_single_adapter_persists_batch(): void {
        $this->resetAfterTest();
        $fixture = $this->create_mixed_course();
        $manager = new batch_manager();
        $batchid = $manager->execute(
            $fixture['courseid'],
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [$fixture['assign_cmid']],
            0
        );
        $this->assertGreaterThan(0, $batchid);
        $reloaded = new batch($batchid);
        $this->assertSame(batch::STATUS_EXECUTED, $reloaded->get('status'));
        $this->assertSame($fixture['courseid'], $reloaded->get('courseid'));
        $this->assertSame('shift_dates', $reloaded->get('action'));
    }

    /**
     * The execute call writes the shifted dates back to the underlying
     * mod_* tables for every routed cmid.
     * @covers \local_coursectrl\manager\batch_manager
     */
    public function test_execute_writes_dates_to_db(): void {
        global $DB;
        $this->resetAfterTest();
        $fixture = $this->create_mixed_course();
        $manager = new batch_manager();
        $manager->execute(
            $fixture['courseid'],
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [
                $fixture['assign_cmid'],
                $fixture['quiz_cmid'],
                $fixture['feedback_cmid'],
            ],
            0
        );
        $assignrec = $DB->get_record('assign', ['id' => $fixture['assign_iid']]);
        $quizrec = $DB->get_record('quiz', ['id' => $fixture['quiz_iid']]);
        $feedbackrec = $DB->get_record('feedback', ['id' => $fixture['feedback_iid']]);
        $this->assertSame(self::BASE_TIME + self::ONE_DAY, (int)$assignrec->duedate);
        $this->assertSame(self::BASE_TIME + self::ONE_DAY, (int)$quizrec->timeopen);
        $this->assertSame(self::BASE_TIME + self::ONE_DAY, (int)$feedbackrec->timeopen);
    }

    /**
     * One snapshot row is persisted per successfully processed cmid.
     * @covers \local_coursectrl\manager\batch_manager
     */
    public function test_execute_persists_snapshots_per_cmid(): void {
        $this->resetAfterTest();
        $fixture = $this->create_mixed_course();
        $manager = new batch_manager();
        $batchid = $manager->execute(
            $fixture['courseid'],
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [
                $fixture['assign_cmid'],
                $fixture['quiz_cmid'],
                $fixture['feedback_cmid'],
            ],
            0
        );
        $snapshots = snapshot::get_records(['batchid' => $batchid]);
        $this->assertCount(3, $snapshots);
        $cmids = array_map(fn($s) => (int)$s->get('entityid'), $snapshots);
        $this->assertContains($fixture['assign_cmid'], $cmids);
        $this->assertContains($fixture['quiz_cmid'], $cmids);
        $this->assertContains($fixture['feedback_cmid'], $cmids);
    }

    /**
     * The persisted snapshot statejson contains the pre-mutation date
     * values, not the post-mutation ones.
     * @covers \local_coursectrl\manager\batch_manager
     */
    public function test_snapshot_carries_pre_mutation_state(): void {
        $this->resetAfterTest();
        $fixture = $this->create_mixed_course();
        $manager = new batch_manager();
        $batchid = $manager->execute(
            $fixture['courseid'],
            'shift_dates',
            ['delta' => 7 * self::ONE_DAY],
            [$fixture['assign_cmid']],
            0
        );
        $snapshots = snapshot::get_records(['batchid' => $batchid, 'entityid' => $fixture['assign_cmid']]);
        $this->assertCount(1, $snapshots);
        $state = json_decode(reset($snapshots)->get('statejson'), true);
        $this->assertSame('mod_assign', $state['component']);
        $this->assertSame(self::BASE_TIME, (int)$state['fields']['duedate']);
    }

    /**
     * One batch_item row is persisted per cmid with the correct status
     * and the result JSON.
     * @covers \local_coursectrl\manager\batch_manager
     */
    public function test_execute_persists_batch_items(): void {
        $this->resetAfterTest();
        $fixture = $this->create_mixed_course();
        $manager = new batch_manager();
        $batchid = $manager->execute(
            $fixture['courseid'],
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [
                $fixture['assign_cmid'],
                $fixture['quiz_cmid'],
            ],
            0
        );
        $items = batch_item::get_records(['batchid' => $batchid]);
        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertSame(batch_item::STATUS_SUCCESS, $item->get('status'));
            $this->assertNotEmpty($item->get('resultjson'));
            $result = json_decode($item->get('resultjson'), true);
            $this->assertSame('ok', $result['status']);
        }
    }

    /**
     * cmids without a registered adapter and no CM-level date fields to shift
     * (completionexpected=0, no availability conditions) produce no batch_item.
     *
     * The old behaviour logged a skipped item; the new behaviour silently
     * skips CMs where there is genuinely nothing to do at any level.
     * @covers \local_coursectrl\manager\batch_manager
     */
    public function test_execute_no_adapter_no_cm_dates_produces_no_item(): void {
        $this->resetAfterTest();
        $fixture = $this->create_mixed_course();
        $label = $this->getDataGenerator()->create_module('label', [
            'course' => $fixture['courseid'],
            'name'   => 'L1',
        ]);
        // Label has no adapter, completionexpected=0, no availability → nothing to shift.
        $manager = new batch_manager();
        $batchid = $manager->execute(
            $fixture['courseid'],
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [(int)$label->cmid],
            0
        );
        $items = batch_item::get_records(['batchid' => $batchid]);
        $this->assertCount(0, $items);
    }

    /**
     * cmids without a registered adapter but WITH completionexpected set produce
     * a successful batch_item via the CM-level shift path.
     * @covers \local_coursectrl\manager\batch_manager
     */
    public function test_execute_no_adapter_with_completionexpected_shifts_it(): void {
        global $DB;
        $this->resetAfterTest();
        $fixture = $this->create_mixed_course();
        $label = $this->getDataGenerator()->create_module('label', [
            'course' => $fixture['courseid'],
            'name'   => 'L2',
        ]);
        // Set completionexpected on the CM.
        $ts = mktime(0, 0, 0, 6, 1, 2026);
        $DB->set_field('course_modules', 'completionexpected', $ts, ['id' => (int)$label->cmid]);

        $manager = new batch_manager();
        $batchid = $manager->execute(
            $fixture['courseid'],
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [(int)$label->cmid],
            0
        );
        $items = batch_item::get_records(['batchid' => $batchid]);
        $this->assertCount(1, $items);
        $item = reset($items);
        $this->assertSame(batch_item::STATUS_SUCCESS, $item->get('status'));
        // Verify the timestamp was actually shifted.
        $newts = (int) $DB->get_field('course_modules', 'completionexpected', ['id' => (int)$label->cmid]);
        $this->assertSame($ts + self::ONE_DAY, $newts);
    }

    /**
     * Empty cmids list defaults to "all CMs of all supported components".
     * @covers \local_coursectrl\manager\batch_manager
     */
    public function test_execute_empty_cmids_processes_whole_course(): void {
        $this->resetAfterTest();
        $fixture = $this->create_mixed_course();
        $manager = new batch_manager();
        $batchid = $manager->execute(
            $fixture['courseid'],
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [],
            0
        );
        $items = batch_item::get_records(['batchid' => $batchid]);
        $successful = array_filter(
            $items,
            fn($i) => $i->get('status') === batch_item::STATUS_SUCCESS
        );
        $this->assertCount(3, $successful);
    }

    /**
     * delta=0 results in noop items that still produce a SUCCESS status
     * (no error), do NOT touch the DB and DO produce snapshots.
     * @covers \local_coursectrl\manager\batch_manager
     */
    public function test_execute_noop_delta_zero(): void {
        global $DB;
        $this->resetAfterTest();
        $fixture = $this->create_mixed_course();
        $before = $DB->get_record('assign', ['id' => $fixture['assign_iid']]);
        $manager = new batch_manager();
        $batchid = $manager->execute(
            $fixture['courseid'],
            'shift_dates',
            ['delta' => 0],
            [$fixture['assign_cmid']],
            0
        );
        $reloaded = new batch($batchid);
        $this->assertSame(batch::STATUS_EXECUTED, $reloaded->get('status'));
        $items = batch_item::get_records(['batchid' => $batchid]);
        $item = reset($items);
        $this->assertSame(batch_item::STATUS_SUCCESS, $item->get('status'));
        $result = json_decode($item->get('resultjson'), true);
        $this->assertSame('noop', $result['status']);
        $after = $DB->get_record('assign', ['id' => $fixture['assign_iid']]);
        $this->assertSame((int)$before->duedate, (int)$after->duedate);
    }

    /**
     * Cross-component routing in one batch: assign + quiz + feedback are
     * each routed to their respective adapter and produce three correct
     * batch_item rows with their per-component identifiers.
     * @covers \local_coursectrl\manager\batch_manager
     */
    public function test_execute_cross_component_routing(): void {
        $this->resetAfterTest();
        $fixture = $this->create_mixed_course();
        $manager = new batch_manager();
        $batchid = $manager->execute(
            $fixture['courseid'],
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [
                $fixture['assign_cmid'],
                $fixture['quiz_cmid'],
                $fixture['feedback_cmid'],
            ],
            0
        );
        $items = batch_item::get_records(['batchid' => $batchid]);
        $this->assertCount(3, $items);
        $components = array_map(fn($i) => $i->get('component'), $items);
        $this->assertContains('mod_assign', $components);
        $this->assertContains('mod_quiz', $components);
        $this->assertContains('mod_feedback', $components);
    }

    /**
     * The batch_executed event is fired after a successful execute call
     * and carries the batch id, course id, action and summary in 'other'.
     * @covers \local_coursectrl\manager\batch_manager
     */
    public function test_execute_triggers_batch_executed_event(): void {
        $this->resetAfterTest();
        $fixture = $this->create_mixed_course();
        $manager = new batch_manager();
        $sink = $this->redirectEvents();
        $batchid = $manager->execute(
            $fixture['courseid'],
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [$fixture['assign_cmid']],
            0
        );
        $events = $sink->get_events();
        $batchevents = array_values(array_filter(
            $events,
            fn($e) => $e instanceof \local_coursectrl\event\batch_executed
        ));
        $this->assertCount(1, $batchevents);
        $event = $batchevents[0];
        $this->assertSame($batchid, (int)$event->objectid);
        $this->assertSame($fixture['courseid'], (int)$event->courseid);
        $this->assertSame('shift_dates', $event->other['action']);
        $this->assertSame(1, $event->other['summary']['success']);
    }

    /**
     * A round-trip: execute then read back the batch, items and snapshots
     * via the persistents and verify they reference each other consistently.
     * @covers \local_coursectrl\manager\batch_manager
     */
    public function test_round_trip_through_persistents(): void {
        $this->resetAfterTest();
        $fixture = $this->create_mixed_course();
        $manager = new batch_manager();
        $batchid = $manager->execute(
            $fixture['courseid'],
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [$fixture['assign_cmid'], $fixture['quiz_cmid']],
            0
        );

        $batchrec = new batch($batchid);
        $this->assertSame(batch::STATUS_EXECUTED, $batchrec->get('status'));

        $items = batch_item::get_records(['batchid' => $batchid]);
        $snapshots = snapshot::get_records(['batchid' => $batchid]);
        $this->assertCount(2, $items);
        $this->assertCount(2, $snapshots);

        foreach ($items as $item) {
            $this->assertSame($batchid, (int)$item->get('batchid'));
        }
        foreach ($snapshots as $snap) {
            $this->assertSame($batchid, (int)$snap->get('batchid'));
        }
    }

    /**
     * shift_dates on a CM with no registered adapter (simulated via a page
     * module) still updates the availability JSON in course_modules.
     *
     * This is the regression test for the cache-invalidation bug: even though
     * the course_modules.availability is updated in the DB, the timeline used
     * to show the old date because rebuild_course_cache() was not called.
     * This test verifies the DB-level change; the cache-rebuild is tested
     * implicitly through shift.php (Behat).
     *
     * @covers \local_coursectrl\manager\batch_manager
     */
    public function test_shift_updates_availability_json_for_unadapted_cm(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();

        // Use label module (no coursectrlmod_label adapter) so the CM
        // falls into the system-level shift_cm_level_dates() path.
        $label = $this->getDataGenerator()->create_module('label', [
            'course' => $course->id,
            'intro'  => 'Avail test label.',
        ]);
        $cmid = (int) $label->cmid;

        // Manually inject an availability date condition into course_modules.
        $avail = json_encode([
            'op' => '&',
            'c'  => [
                ['type' => 'date', 'd' => '>=', 't' => self::BASE_TIME],
            ],
            'showc' => [true],
        ]);
        $DB->set_field('course_modules', 'availability', $avail, ['id' => $cmid]);

        // Execute the shift — the CM may be routed through an adapter OR
        // fall into the skipped/system-level path; either way, if availability
        // JSON was not null before, it must shift by ONE_DAY.
        $manager  = new batch_manager();
        $manager->execute(
            (int) $course->id,
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [$cmid],
            0
        );

        $newavail = $DB->get_field('course_modules', 'availability', ['id' => $cmid]);
        $decoded  = json_decode($newavail, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('c', $decoded);
        $first = $decoded['c'][0] ?? null;
        $this->assertNotNull($first);
        $this->assertEquals('date', $first['type']);
        // Timestamp must have been incremented by at least ONE_DAY.
        $this->assertGreaterThanOrEqual(
            self::BASE_TIME + self::ONE_DAY,
            (int) $first['t'],
            'Availability date timestamp must be shifted forward by one day.'
        );
    }

    /**
     * shift_dates with a negative delta decrements availability timestamps.
     *
     * @covers \local_coursectrl\manager\batch_manager
     */
    public function test_shift_backward_decrements_availability_timestamp(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $label = $this->getDataGenerator()->create_module('label', [
            'course' => $course->id,
            'intro'  => 'Backward shift label.',
        ]);
        $cmid = (int) $label->cmid;

        $avail = json_encode([
            'op' => '&',
            'c'  => [['type' => 'date', 'd' => '>=', 't' => self::BASE_TIME]],
            'showc' => [true],
        ]);
        $DB->set_field('course_modules', 'availability', $avail, ['id' => $cmid]);

        $manager = new batch_manager();
        $manager->execute(
            (int) $course->id,
            'shift_dates',
            ['delta' => -self::ONE_DAY],
            [$cmid],
            0
        );

        $decoded = json_decode(
            $DB->get_field('course_modules', 'availability', ['id' => $cmid]),
            true
        );
        $this->assertEquals(
            self::BASE_TIME - self::ONE_DAY,
            (int) ($decoded['c'][0]['t'] ?? 0),
            'Availability timestamp must be decremented by one day on negative delta.'
        );
    }
}
