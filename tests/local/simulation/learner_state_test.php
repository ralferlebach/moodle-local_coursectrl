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
 * Tests for learner_state DTO.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\simulation;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\simulation\learner_state::class)]
/**
 * Unit tests for learner_state.
 *
 * @covers \local_coursectrl\local\simulation\learner_state
 */
final class learner_state_test extends \advanced_testcase {
    /**
     * Default constructor uses current time when timestamp is 0.
     * @covers \local_coursectrl\local\simulation\learner_state
     * @return void
     */
    public function test_zero_timestamp_defaults_to_now(): void {
        $this->resetAfterTest();
        $before = time();
        $state = new learner_state(0);
        $after = time();
        $this->assertGreaterThanOrEqual($before, $state->timestamp);
        $this->assertLessThanOrEqual($after, $state->timestamp);
    }

    /**
     * Explicit timestamp is preserved as-is.
     * @covers \local_coursectrl\local\simulation\learner_state
     * @return void
     */
    public function test_explicit_timestamp_preserved(): void {
        $this->resetAfterTest();
        $ts = 1750507200;
        $state = new learner_state($ts);
        $this->assertSame($ts, $state->timestamp);
    }

    /**
     * get_completion returns 0 for cmids not in the map.
     * @covers \local_coursectrl\local\simulation\learner_state
     * @return void
     */
    public function test_get_completion_returns_zero_for_unknown_cmid(): void {
        $this->resetAfterTest();
        $state = new learner_state(1750507200, [1 => 1]);
        $this->assertSame(0, $state->get_completion(99));
    }

    /**
     * get_completion returns the stored value for a known cmid.
     * @covers \local_coursectrl\local\simulation\learner_state
     * @return void
     */
    public function test_get_completion_returns_stored_value(): void {
        $this->resetAfterTest();
        $state = new learner_state(1750507200, [5 => 2]);
        $this->assertSame(2, $state->get_completion(5));
    }

    /**
     * is_in_group correctly identifies membership.
     * @covers \local_coursectrl\local\simulation\learner_state
     * @return void
     */
    public function test_is_in_group(): void {
        $this->resetAfterTest();
        $state = new learner_state(1750507200, [], [3, 7]);
        $this->assertTrue($state->is_in_group(3));
        $this->assertTrue($state->is_in_group(7));
        $this->assertFalse($state->is_in_group(99));
    }

    /**
     * is_in_grouping correctly identifies membership.
     * @covers \local_coursectrl\local\simulation\learner_state
     * @return void
     */
    public function test_is_in_grouping(): void {
        $this->resetAfterTest();
        $state = new learner_state(1750507200, [], [], [4]);
        $this->assertTrue($state->is_in_grouping(4));
        $this->assertFalse($state->is_in_grouping(1));
    }

    /**
     * from_array round-trips through to_array without data loss.
     * @covers \local_coursectrl\local\simulation\learner_state
     * @return void
     */
    public function test_round_trip_via_array(): void {
        $this->resetAfterTest();
        $original = new learner_state(
            1750507200,
            [1 => 1, 2 => 2],
            [3],
            [4],
            [5 => 72.5]
        );
        $restored = learner_state::from_array($original->to_array());
        $this->assertSame($original->timestamp, $restored->timestamp);
        $this->assertSame($original->completions, $restored->completions);
        $this->assertSame($original->groupids, $restored->groupids);
        $this->assertSame($original->groupingids, $restored->groupingids);
        $this->assertEqualsWithDelta(72.5, $restored->get_grade(5), 0.001);
    }

    // Grade tests.

    /**
     * get_grade returns null for a cmid not in the grades map.
     * @covers \local_coursectrl\local\simulation\learner_state
     * @return void
     */
    public function test_get_grade_returns_null_for_unknown_cmid(): void {
        $this->resetAfterTest();
        $state = new learner_state(1750507200, [], [], [], [10 => 80.0]);
        $this->assertNull($state->get_grade(99));
    }

    /**
     * get_grade returns the stored float for a known cmid.
     * @covers \local_coursectrl\local\simulation\learner_state
     * @return void
     */
    public function test_get_grade_returns_stored_value(): void {
        $this->resetAfterTest();
        $state = new learner_state(1750507200, [], [], [], [7 => 63.5]);
        $this->assertEqualsWithDelta(63.5, $state->get_grade(7), 0.001);
    }

    /**
     * grades are coerced to float regardless of input type.
     * @covers \local_coursectrl\local\simulation\learner_state
     * @return void
     */
    public function test_grades_are_cast_to_float(): void {
        $this->resetAfterTest();
        $state = new learner_state(1750507200, [], [], [], [3 => '55']);
        $this->assertIsFloat($state->get_grade(3));
        $this->assertEqualsWithDelta(55.0, $state->get_grade(3), 0.001);
    }

    /**
     * Grades survive a to_array → from_array round-trip.
     * @covers \local_coursectrl\local\simulation\learner_state
     * @return void
     */
    public function test_grades_round_trip(): void {
        $this->resetAfterTest();
        $original = new learner_state(1750507200, [], [], [], [4 => 91.0, 8 => 45.5]);
        $restored = learner_state::from_array($original->to_array());
        $this->assertEqualsWithDelta(91.0, $restored->get_grade(4), 0.001);
        $this->assertEqualsWithDelta(45.5, $restored->get_grade(8), 0.001);
        $this->assertNull($restored->get_grade(99));
    }
}
