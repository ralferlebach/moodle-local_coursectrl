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
 * Unit tests for the coursectrlmod_assign adapter.
 *
 * Verifies the patch-018 surface: contract integration, supported actions
 * and fields, instance enumeration, normalised description, snapshot
 * capture, validation, shift_dates preview semantics including the
 * unset-zero-date special case.
 *
 * @package    coursectrlmod_assign
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace coursectrlmod_assign;

use local_coursectrl\local\contract\abstract_activity_adapter;
use local_coursectrl\local\contract\activity_adapter;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \coursectrlmod_assign\adapter
 */
final class adapter_test extends \advanced_testcase {
    /** @var int Reference timestamp used by all date-bearing fixtures. */
    private const BASE_TIME = 1700000000;

    /** @var int One-day delta in seconds. */
    private const ONE_DAY = 86400;

    /**
     * Create a course with a single assign instance with all four date
     * fields set, and return the cmid plus the original date values.
     *
     * @return array{cmid: int, courseid: int, dates: array}
     */
    private function create_assign_with_dates(): array {
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $instance = $generator->create_instance([
            'course'                   => $course->id,
            'name'                     => 'Patch-018 Assign',
            'duedate'                  => self::BASE_TIME,
            'allowsubmissionsfromdate' => self::BASE_TIME - self::ONE_DAY,
            'cutoffdate'               => self::BASE_TIME + self::ONE_DAY,
            'gradingduedate'           => self::BASE_TIME + (2 * self::ONE_DAY),
        ]);
        return [
            'cmid'     => (int)$instance->cmid,
            'courseid' => (int)$course->id,
            'dates'    => [
                'duedate'                  => self::BASE_TIME,
                'allowsubmissionsfromdate' => self::BASE_TIME - self::ONE_DAY,
                'cutoffdate'               => self::BASE_TIME + self::ONE_DAY,
                'gradingduedate'           => self::BASE_TIME + (2 * self::ONE_DAY),
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
     * the four date fields exposed via field_map.
     */
    public function test_static_metadata(): void {
        $adapter = new adapter();
        $this->assertSame('mod_assign', adapter::component());
        $this->assertTrue($adapter->is_available());
        $this->assertSame(['shift_dates'], $adapter->get_supported_actions());
        $fields = $adapter->get_supported_fields();
        $this->assertArrayHasKey('duedate', $fields);
        $this->assertArrayHasKey('allowsubmissionsfromdate', $fields);
        $this->assertArrayHasKey('cutoffdate', $fields);
        $this->assertArrayHasKey('gradingduedate', $fields);
        $this->assertCount(4, $fields);
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
        $fixture = $this->create_assign_with_dates();
        $adapter = new adapter();
        $instances = $adapter->get_instances_for_course($fixture['courseid']);
        $this->assertArrayHasKey($fixture['cmid'], $instances);
        $entry = $instances[$fixture['cmid']];
        $this->assertSame($fixture['cmid'], $entry['cmid']);
        $this->assertSame('Patch-018 Assign', $entry['name']);
        $this->assertTrue($entry['visible']);
    }

    /**
     * describe_instance must return the four date fields exactly.
     */
    public function test_describe_instance_returns_dates(): void {
        $this->resetAfterTest();
        $fixture = $this->create_assign_with_dates();
        $adapter = new adapter();
        $description = $adapter->describe_instance($fixture['cmid']);
        $this->assertSame('mod_assign', $description['component']);
        $this->assertSame($fixture['cmid'], $description['cmid']);
        $this->assertSame('Patch-018 Assign', $description['name']);
        $this->assertSame($fixture['dates'], $description['dates']);
    }

    /**
     * export_state must capture all four date fields plus identifying ids.
     */
    public function test_export_state_captures_snapshot(): void {
        $this->resetAfterTest();
        $fixture = $this->create_assign_with_dates();
        $adapter = new adapter();
        $snapshot = $adapter->export_state($fixture['cmid']);
        $this->assertSame('mod_assign', $snapshot['component']);
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
        $fixture = $this->create_assign_with_dates();
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
     *
     * This is the "unset" special case: mod_assign uses 0 to mean "not set"
     * and a blind delta would yield epoch + delta.
     */
    public function test_preview_skips_unset_zero_dates(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $instance = $generator->create_instance([
            'course'                   => $course->id,
            'name'                     => 'Sparse Assign',
            'duedate'                  => self::BASE_TIME,
            'allowsubmissionsfromdate' => 0,
            'cutoffdate'               => 0,
            'gradingduedate'           => 0,
        ]);
        $adapter = new adapter();
        $preview = $adapter->preview_action(
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [(int)$instance->cmid]
        );
        $fields = $preview['items'][0]['fields'];
        $this->assertSame(self::BASE_TIME, $fields['duedate']['old']);
        $this->assertSame(self::BASE_TIME + self::ONE_DAY, $fields['duedate']['new']);
        $this->assertTrue($fields['duedate']['shifted']);
        foreach (['allowsubmissionsfromdate', 'cutoffdate', 'gradingduedate'] as $name) {
            $this->assertSame(0, $fields[$name]['old']);
            $this->assertSame(0, $fields[$name]['new']);
            $this->assertFalse($fields[$name]['shifted']);
            $this->assertSame('unset', $fields[$name]['reason']);
        }
    }

    /**
     * preview_action must return an empty result for any non-supported
     * action identifier (defensive default, no exception).
     */
    public function test_preview_returns_empty_for_unsupported_action(): void {
        $adapter = new adapter();
        $this->assertSame([], $adapter->preview_action('set_visibility', ['visible' => 1], [1]));
    }

    /**
     * execute_action and restore_state are intentionally inherited as
     * no-ops in patch-018 and must therefore return an empty array.
     */
    public function test_mutating_methods_are_still_noops(): void {
        $adapter = new adapter();
        $this->assertSame(
            [],
            $adapter->execute_action('shift_dates', ['delta' => self::ONE_DAY], [1], 7)
        );
        $this->assertSame(
            [],
            $adapter->restore_state(['component' => 'mod_assign', 'cmid' => 1, 'fields' => []])
        );
    }
}
