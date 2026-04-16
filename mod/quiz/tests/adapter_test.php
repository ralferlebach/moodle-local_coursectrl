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
 * Unit tests for the coursectrlmod_quiz adapter.
 *
 * @package    coursectrlmod_quiz
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace coursectrlmod_quiz;

use local_coursectrl\local\contract\abstract_activity_adapter;
use local_coursectrl\local\contract\activity_adapter;

/**
 * Verifies the coursectrlmod_quiz adapter against the same surface as the
 * coursectrlmod_assign adapter: contract integration, supported actions
 * and fields, instance enumeration, normalised description, snapshot
 * capture, validation, shift_dates preview including the unset-zero-date
 * special case, and the write side execute_action / restore_state with
 * real database writes.
 *
 * @covers \coursectrlmod_quiz\adapter
 */
final class adapter_test extends \advanced_testcase {
    /** @var int Reference timestamp used by all date-bearing fixtures. */
    private const BASE_TIME = 1700000000;

    /** @var int One-day delta in seconds. */
    private const ONE_DAY = 86400;

    /**
     * Create a course with a single quiz instance with both date fields
     * set, and return the cmid plus the original date values.
     *
     * @return array
     */
    private function create_quiz_with_dates(): array {
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $instance = $generator->create_instance([
            'course'    => $course->id,
            'name'      => 'Patch-021 Quiz',
            'timeopen'  => self::BASE_TIME,
            'timeclose' => self::BASE_TIME + self::ONE_DAY,
        ]);
        return [
            'cmid'       => (int)$instance->cmid,
            'instanceid' => (int)$instance->id,
            'courseid'   => (int)$course->id,
            'dates'      => [
                'timeopen'  => self::BASE_TIME,
                'timeclose' => self::BASE_TIME + self::ONE_DAY,
            ],
        ];
    }

    /**
     * Adapter must extend the production base and implement the contract.
     */
    public function test_extends_abstract_base_and_implements_contract(): void {
        $this->assertTrue(
            is_subclass_of(adapter::class, abstract_activity_adapter::class, true),
            'adapter must extend abstract_activity_adapter.'
        );
        $this->assertTrue(
            is_subclass_of(adapter::class, activity_adapter::class, true),
            'adapter must implement activity_adapter.'
        );
    }

    /**
     * Static metadata: component name, availability, supported actions and
     * the two date fields exposed via field_map.
     */
    public function test_static_metadata(): void {
        $adapter = new adapter();
        $this->assertSame('mod_quiz', adapter::component());
        $this->assertTrue($adapter->is_available());
        $this->assertSame(['shift_dates', 'unset_dates'], $adapter->get_supported_actions());
        $fields = $adapter->get_supported_fields();
        $this->assertArrayHasKey('timeopen', $fields);
        $this->assertArrayHasKey('timeclose', $fields);
        $this->assertCount(2, $fields);
        foreach ($fields as $descriptor) {
            $this->assertTrue($descriptor['shiftable']);
            $this->assertTrue($descriptor['nullable_zero']);
        }
    }

    /**
     * get_instances_for_course must return the cmid keyed entry.
     */
    public function test_get_instances_for_course(): void {
        $this->resetAfterTest();
        $fixture = $this->create_quiz_with_dates();
        $adapter = new adapter();
        $instances = $adapter->get_instances_for_course($fixture['courseid']);
        $this->assertArrayHasKey($fixture['cmid'], $instances);
        $entry = $instances[$fixture['cmid']];
        $this->assertSame($fixture['cmid'], $entry['cmid']);
        $this->assertSame('Patch-021 Quiz', $entry['name']);
        $this->assertTrue($entry['visible']);
    }

    /**
     * describe_instance must return both date fields exactly.
     */
    public function test_describe_instance_returns_dates(): void {
        $this->resetAfterTest();
        $fixture = $this->create_quiz_with_dates();
        $adapter = new adapter();
        $description = $adapter->describe_instance($fixture['cmid']);
        $this->assertSame('mod_quiz', $description['component']);
        $this->assertSame($fixture['cmid'], $description['cmid']);
        $this->assertSame('Patch-021 Quiz', $description['name']);
        $this->assertSame($fixture['dates'], $description['dates']);
    }

    /**
     * export_state must capture both date fields plus identifying ids.
     */
    public function test_export_state_captures_snapshot(): void {
        $this->resetAfterTest();
        $fixture = $this->create_quiz_with_dates();
        $adapter = new adapter();
        $snapshot = $adapter->export_state($fixture['cmid']);
        $this->assertSame('mod_quiz', $snapshot['component']);
        $this->assertSame($fixture['cmid'], $snapshot['cmid']);
        $this->assertSame(1, $snapshot['version']);
        $this->assertSame($fixture['dates'], $snapshot['fields']);
    }

    /**
     * validate_action must accept a numeric delta and reject everything else.
     */
    public function test_validate_action(): void {
        $adapter = new adapter();
        $ok = $adapter->validate_action('shift_dates', ['delta' => self::ONE_DAY], [1, 2, 3]);
        $this->assertTrue($ok['valid']);
        $this->assertSame([], $ok['errors']);
        $this->assertSame([1, 2, 3], $ok['cmids']);

        $missing = $adapter->validate_action('shift_dates', [], [1]);
        $this->assertFalse($missing['valid']);
        $this->assertSame('invalid_delta', $missing['errors'][0]['code']);

        $bad = $adapter->validate_action('shift_dates', ['delta' => 'tomorrow'], [1]);
        $this->assertFalse($bad['valid']);
        $this->assertSame('invalid_delta', $bad['errors'][0]['code']);

        $other = $adapter->validate_action('set_visibility', ['visible' => 1], [1]);
        $this->assertFalse($other['valid']);
        $this->assertSame('unsupported_action', $other['errors'][0]['code']);
    }

    /**
     * preview_action must compute old/new for every set field and report
     * shifted=true when the value actually changed.
     */
    public function test_preview_shift_dates_for_set_fields(): void {
        $this->resetAfterTest();
        $fixture = $this->create_quiz_with_dates();
        $adapter = new adapter();
        $preview = $adapter->preview_action(
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [$fixture['cmid']]
        );
        $this->assertSame('shift_dates', $preview['action']);
        $this->assertSame(self::ONE_DAY, $preview['payload']['delta']);
        $this->assertCount(1, $preview['items']);
        $this->assertSame([], $preview['errors']);
        $item = $preview['items'][0];
        $this->assertSame($fixture['cmid'], $item['cmid']);
        foreach ($fixture['dates'] as $name => $oldvalue) {
            $this->assertSame($oldvalue, $item['fields'][$name]['old']);
            $this->assertSame($oldvalue + self::ONE_DAY, $item['fields'][$name]['new']);
            $this->assertTrue($item['fields'][$name]['shifted']);
        }
    }

    /**
     * preview_action must NOT shift fields whose stored value is 0.
     */
    public function test_preview_skips_unset_zero_dates(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $instance = $generator->create_instance([
            'course'    => $course->id,
            'name'      => 'Sparse Quiz',
            'timeopen'  => self::BASE_TIME,
            'timeclose' => 0,
        ]);
        $adapter = new adapter();
        $preview = $adapter->preview_action(
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [(int)$instance->cmid]
        );
        $fields = $preview['items'][0]['fields'];
        $this->assertSame(self::BASE_TIME, $fields['timeopen']['old']);
        $this->assertSame(self::BASE_TIME + self::ONE_DAY, $fields['timeopen']['new']);
        $this->assertTrue($fields['timeopen']['shifted']);
        $this->assertSame(0, $fields['timeclose']['old']);
        $this->assertSame(0, $fields['timeclose']['new']);
        $this->assertFalse($fields['timeclose']['shifted']);
        $this->assertSame('unset', $fields['timeclose']['reason']);
    }

    /**
     * preview_action must return an empty result for any non-supported
     * action identifier.
     */
    public function test_preview_returns_empty_for_unsupported_action(): void {
        $adapter = new adapter();
        $this->assertSame([], $adapter->preview_action('set_visibility', ['visible' => 1], [1]));
    }

    /**
     * execute_action must shift the two date fields in the database.
     */
    public function test_execute_shifts_dates_in_db(): void {
        global $DB;
        $this->resetAfterTest();
        $fixture = $this->create_quiz_with_dates();
        $adapter = new adapter();
        $result = $adapter->execute_action(
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [$fixture['cmid']],
            0
        );
        $this->assertSame('shift_dates', $result['action']);
        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['items']);
        $item = $result['items'][0];
        $this->assertSame('ok', $item['status']);
        $this->assertSame(['timeopen', 'timeclose'], $item['changed']);
        $record = $DB->get_record('quiz', ['id' => $fixture['instanceid']]);
        $this->assertSame(self::BASE_TIME + self::ONE_DAY, (int)$record->timeopen);
        $this->assertSame(
            self::BASE_TIME + self::ONE_DAY + self::ONE_DAY,
            (int)$record->timeclose
        );
    }

    /**
     * The snapshot returned in each execute_action item must contain the
     * pre-mutation values, captured before the DB write.
     */
    public function test_execute_returns_snapshot_with_old_values(): void {
        $this->resetAfterTest();
        $fixture = $this->create_quiz_with_dates();
        $adapter = new adapter();
        $result = $adapter->execute_action(
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [$fixture['cmid']],
            0
        );
        $snapshot = $result['items'][0]['snapshot'];
        $this->assertSame('mod_quiz', $snapshot['component']);
        $this->assertSame($fixture['cmid'], $snapshot['cmid']);
        $this->assertSame($fixture['instanceid'], $snapshot['instanceid']);
        $this->assertSame($fixture['dates'], $snapshot['fields']);
        $this->assertSame(1, $snapshot['version']);
    }

    /**
     * execute_action must skip unset (zero) date fields just like preview.
     */
    public function test_execute_skips_unset_zero_dates(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $instance = $generator->create_instance([
            'course'    => $course->id,
            'name'      => 'Sparse Quiz',
            'timeopen'  => self::BASE_TIME,
            'timeclose' => 0,
        ]);
        $adapter = new adapter();
        $result = $adapter->execute_action(
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [(int)$instance->cmid],
            0
        );
        $item = $result['items'][0];
        $this->assertSame('ok', $item['status']);
        $this->assertSame(['timeopen'], $item['changed']);
        $record = $DB->get_record('quiz', ['id' => $instance->id]);
        $this->assertSame(self::BASE_TIME + self::ONE_DAY, (int)$record->timeopen);
        $this->assertSame(0, (int)$record->timeclose);
    }

    /**
     * execute_action with delta=0 must not write to the DB and must return
     * status 'noop' for the affected cmid.
     */
    public function test_execute_noop_when_delta_is_zero(): void {
        global $DB;
        $this->resetAfterTest();
        $fixture = $this->create_quiz_with_dates();
        $adapter = new adapter();
        $before = $DB->get_record('quiz', ['id' => $fixture['instanceid']]);
        $result = $adapter->execute_action(
            'shift_dates',
            ['delta' => 0],
            [$fixture['cmid']],
            0
        );
        $item = $result['items'][0];
        $this->assertSame('noop', $item['status']);
        $this->assertSame([], $item['changed']);
        $after = $DB->get_record('quiz', ['id' => $fixture['instanceid']]);
        $this->assertSame((int)$before->timeopen, (int)$after->timeopen);
        $this->assertSame((int)$before->timemodified, (int)$after->timemodified);
    }

    /**
     * execute_action must reject invalid payloads via validate_action and
     * return the validation errors without touching the database.
     */
    public function test_execute_validates_payload_first(): void {
        global $DB;
        $this->resetAfterTest();
        $fixture = $this->create_quiz_with_dates();
        $adapter = new adapter();
        $before = $DB->get_record('quiz', ['id' => $fixture['instanceid']]);
        $result = $adapter->execute_action(
            'shift_dates',
            ['delta' => 'next monday'],
            [$fixture['cmid']],
            0
        );
        $this->assertSame([], $result['items']);
        $this->assertNotEmpty($result['errors']);
        $this->assertSame('invalid_delta', $result['errors'][0]['code']);
        $after = $DB->get_record('quiz', ['id' => $fixture['instanceid']]);
        $this->assertSame((int)$before->timeopen, (int)$after->timeopen);
    }

    /**
     * execute_action must return an empty result for unsupported actions.
     */
    public function test_execute_returns_empty_for_unsupported_action(): void {
        $adapter = new adapter();
        $this->assertSame(
            [],
            $adapter->execute_action('set_visibility', ['visible' => 1], [1], 0)
        );
    }

    /**
     * Round-trip: execute then restore must restore the original DB state.
     */
    public function test_restore_state_round_trip(): void {
        global $DB;
        $this->resetAfterTest();
        $fixture = $this->create_quiz_with_dates();
        $adapter = new adapter();
        $exec = $adapter->execute_action(
            'shift_dates',
            ['delta' => 7 * self::ONE_DAY],
            [$fixture['cmid']],
            0
        );
        $snapshot = $exec['items'][0]['snapshot'];
        $shifted = $DB->get_record('quiz', ['id' => $fixture['instanceid']]);
        $this->assertNotSame((int)$shifted->timeopen, $fixture['dates']['timeopen']);

        $restore = $adapter->restore_state($snapshot);
        $this->assertSame('ok', $restore['status']);
        $this->assertSame($fixture['cmid'], $restore['cmid']);
        $this->assertSame($fixture['dates'], $restore['restored']);

        $restored = $DB->get_record('quiz', ['id' => $fixture['instanceid']]);
        $this->assertSame($fixture['dates']['timeopen'], (int)$restored->timeopen);
        $this->assertSame($fixture['dates']['timeclose'], (int)$restored->timeclose);
    }

    /**
     * restore_state must reject snapshots whose component does not match.
     */
    public function test_restore_state_rejects_invalid_component(): void {
        $adapter = new adapter();
        $result = $adapter->restore_state([
            'component' => 'mod_assign',
            'cmid'      => 1,
            'fields'    => ['timeopen' => 1700000000],
        ]);
        $this->assertSame('failed', $result['status']);
        $this->assertSame('invalid_component', $result['code']);
    }

    /**
     * restore_state must reject snapshots without a fields array.
     */
    public function test_restore_state_rejects_missing_fields(): void {
        $adapter = new adapter();
        $result = $adapter->restore_state([
            'component' => 'mod_quiz',
            'cmid'      => 1,
        ]);
        $this->assertSame('failed', $result['status']);
        $this->assertSame('invalid_snapshot', $result['code']);
    }

    /**
     * restore_state must directly write a hand-built snapshot to the DB
     * without requiring a prior execute_action call.
     */
    public function test_restore_state_writes_directly_to_db(): void {
        global $DB;
        $this->resetAfterTest();
        $fixture = $this->create_quiz_with_dates();
        $adapter = new adapter();
        $result = $adapter->restore_state([
            'component'  => 'mod_quiz',
            'cmid'       => $fixture['cmid'],
            'instanceid' => $fixture['instanceid'],
            'fields'     => [
                'timeopen'  => 1234500000,
                'timeclose' => 1234600000,
            ],
            'version'    => 1,
        ]);
        $this->assertSame('ok', $result['status']);
        $record = $DB->get_record('quiz', ['id' => $fixture['instanceid']]);
        $this->assertSame(1234500000, (int)$record->timeopen);
        $this->assertSame(1234600000, (int)$record->timeclose);
    }
}
