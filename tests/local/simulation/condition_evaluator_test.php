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
 * Tests for condition_evaluator.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\simulation;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\simulation\condition_evaluator::class)]
/**
 * Unit tests for condition_evaluator::evaluate().
 *
 * @covers \local_coursectrl\local\simulation\condition_evaluator
 */
final class condition_evaluator_test extends \advanced_testcase {
    /** @var int Fixed "now" timestamp (2026-06-15 12:00 UTC). */
    private const NOW = 1750507200;

    /** @var int One week before NOW. */
    private const PAST = 1749902400;

    /** @var int One week after NOW. */
    private const FUTURE = 1751112000;

    /**
     * Build an availability JSON string from conditions.
     *
     * @param string $op   Operator ('&' or '|').
     * @param array  $conds Condition arrays.
     * @return string
     */
    private function avail(string $op, array $conds): string {
        return json_encode(['op' => $op, 'c' => $conds, 'show' => false]);
    }

    /**
     * Null/empty availability means accessible=true with no reasons.
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @return void
     */
    public function test_null_availability_is_accessible(): void {
        $this->resetAfterTest();
        $ev = new condition_evaluator();
        $state = new learner_state(self::NOW);
        $result = $ev->evaluate(null, $state);
        $this->assertTrue($result['accessible']);
        $this->assertEmpty($result['reasons']);
    }

    /**
     * Completion condition passes when cmid is marked complete (state=1).
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @return void
     */
    public function test_completion_passes_when_complete(): void {
        $this->resetAfterTest();
        $ev = new condition_evaluator();
        $state = new learner_state(self::NOW, [5 => 1]);
        $json = $this->avail('&', [['type' => 'completion', 'cm' => 5, 'e' => 1]]);
        $result = $ev->evaluate($json, $state);
        $this->assertTrue($result['accessible']);
        $this->assertSame(condition_evaluator::STATUS_PASS, $result['reasons'][0]['status']);
    }

    /**
     * Completion condition fails when cmid is incomplete.
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @return void
     */
    public function test_completion_fails_when_incomplete(): void {
        $this->resetAfterTest();
        $ev = new condition_evaluator();
        $state = new learner_state(self::NOW, [5 => 0]);
        $json = $this->avail('&', [['type' => 'completion', 'cm' => 5, 'e' => 1]]);
        $result = $ev->evaluate($json, $state);
        $this->assertFalse($result['accessible']);
    }

    /**
     * Completion state=2 (pass) satisfies e=1 (any complete).
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @return void
     */
    public function test_completion_pass_satisfies_e1(): void {
        $this->resetAfterTest();
        $ev = new condition_evaluator();
        $state = new learner_state(self::NOW, [7 => 2]);
        $json = $this->avail('&', [['type' => 'completion', 'cm' => 7, 'e' => 1]]);
        $result = $ev->evaluate($json, $state);
        $this->assertTrue($result['accessible']);
    }

    /**
     * Completion e=2 (must pass) fails when actual state is 1 (complete, no pass flag).
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @return void
     */
    public function test_completion_e2_fails_when_state_is_1(): void {
        $this->resetAfterTest();
        $ev = new condition_evaluator();
        $state = new learner_state(self::NOW, [7 => 1]);
        $json = $this->avail('&', [['type' => 'completion', 'cm' => 7, 'e' => 2]]);
        $result = $ev->evaluate($json, $state);
        $this->assertFalse($result['accessible']);
    }

    /**
     * Date condition >= past timestamp passes.
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @return void
     */
    public function test_date_from_past_passes(): void {
        $this->resetAfterTest();
        $ev = new condition_evaluator();
        $state = new learner_state(self::NOW);
        $json = $this->avail('&', [['type' => 'date', 'd' => '>=', 't' => self::PAST]]);
        $result = $ev->evaluate($json, $state);
        $this->assertTrue($result['accessible']);
    }

    /**
     * Date condition >= future timestamp fails.
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @return void
     */
    public function test_date_from_future_fails(): void {
        $this->resetAfterTest();
        $ev = new condition_evaluator();
        $state = new learner_state(self::NOW);
        $json = $this->avail('&', [['type' => 'date', 'd' => '>=', 't' => self::FUTURE]]);
        $result = $ev->evaluate($json, $state);
        $this->assertFalse($result['accessible']);
    }

    /**
     * Date condition < future threshold passes (content available before deadline).
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @return void
     */
    public function test_date_until_future_passes(): void {
        $this->resetAfterTest();
        $ev = new condition_evaluator();
        $state = new learner_state(self::NOW);
        $json = $this->avail('&', [['type' => 'date', 'd' => '<', 't' => self::FUTURE]]);
        $result = $ev->evaluate($json, $state);
        $this->assertTrue($result['accessible']);
    }

    /**
     * Group condition passes when learner is in the required group.
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @return void
     */
    public function test_group_passes_when_member(): void {
        $this->resetAfterTest();
        $ev = new condition_evaluator();
        $state = new learner_state(self::NOW, [], [3, 7]);
        $json = $this->avail('&', [['type' => 'group', 'id' => 3]]);
        $result = $ev->evaluate($json, $state);
        $this->assertTrue($result['accessible']);
    }

    /**
     * Group condition fails when learner is not in the required group.
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @return void
     */
    public function test_group_fails_when_not_member(): void {
        $this->resetAfterTest();
        $ev = new condition_evaluator();
        $state = new learner_state(self::NOW, [], [7]);
        $json = $this->avail('&', [['type' => 'group', 'id' => 3]]);
        $result = $ev->evaluate($json, $state);
        $this->assertFalse($result['accessible']);
    }

    /**
     * Grade condition always returns unknown.
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @return void
     */
    public function test_grade_condition_is_unknown(): void {
        $this->resetAfterTest();
        // No gradeitemmap → grade is still unknown.
        $ev = new condition_evaluator();
        $state = new learner_state(self::NOW);
        $json = $this->avail('&', [['type' => 'grade', 'id' => 1, 'min' => 50]]);
        $result = $ev->evaluate($json, $state);
        $this->assertFalse($result['accessible']);
        $this->assertSame(condition_evaluator::STATUS_UNKNOWN, $result['status']);
    }

    // Grade condition with gradeitemmap.

    /**
     * Grade above min threshold passes when gradeitemmap resolves the item.
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @return void
     */
    public function test_grade_passes_when_above_min(): void {
        $this->resetAfterTest();
        $map = [1 => ['cmid' => 10, 'grademax' => 100.0, 'gradepass' => 50.0]];
        $ev = new condition_evaluator($map);
        $state = new learner_state(self::NOW, [], [], [], [10 => 75.0]);
        $json = $this->avail('&', [['type' => 'grade', 'id' => 1, 'min' => 50]]);
        $result = $ev->evaluate($json, $state);
        $this->assertTrue($result['accessible']);
        $this->assertSame(condition_evaluator::STATUS_PASS, $result['status']);
    }

    /**
     * Grade below min threshold fails.
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @return void
     */
    public function test_grade_fails_when_below_min(): void {
        $this->resetAfterTest();
        $map = [1 => ['cmid' => 10, 'grademax' => 100.0, 'gradepass' => 50.0]];
        $ev = new condition_evaluator($map);
        $state = new learner_state(self::NOW, [], [], [], [10 => 40.0]);
        $json = $this->avail('&', [['type' => 'grade', 'id' => 1, 'min' => 50]]);
        $result = $ev->evaluate($json, $state);
        $this->assertFalse($result['accessible']);
        $this->assertSame(condition_evaluator::STATUS_FAIL, $result['status']);
    }

    /**
     * Grade below max threshold passes (exclusive upper bound).
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @return void
     */
    public function test_grade_passes_when_below_max(): void {
        $this->resetAfterTest();
        $map = [2 => ['cmid' => 20, 'grademax' => 100.0, 'gradepass' => 0.0]];
        $ev = new condition_evaluator($map);
        $state = new learner_state(self::NOW, [], [], [], [20 => 49.9]);
        $json = $this->avail('&', [['type' => 'grade', 'id' => 2, 'max' => 50]]);
        $result = $ev->evaluate($json, $state);
        $this->assertTrue($result['accessible']);
    }

    /**
     * Grade exactly at max fails (exclusive upper bound).
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @return void
     */
    public function test_grade_fails_when_at_max(): void {
        $this->resetAfterTest();
        $map = [2 => ['cmid' => 20, 'grademax' => 100.0, 'gradepass' => 0.0]];
        $ev = new condition_evaluator($map);
        $state = new learner_state(self::NOW, [], [], [], [20 => 50.0]);
        $json = $this->avail('&', [['type' => 'grade', 'id' => 2, 'max' => 50]]);
        $result = $ev->evaluate($json, $state);
        $this->assertFalse($result['accessible']);
    }

    /**
     * No grade in learner state → unknown (even with gradeitemmap present).
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @return void
     */
    public function test_grade_unknown_when_no_grade_in_state(): void {
        $this->resetAfterTest();
        $map = [1 => ['cmid' => 10, 'grademax' => 100.0, 'gradepass' => 50.0]];
        $ev = new condition_evaluator($map);
        $state = new learner_state(self::NOW); // No grade set.
        $json = $this->avail('&', [['type' => 'grade', 'id' => 1, 'min' => 50]]);
        $result = $ev->evaluate($json, $state);
        $this->assertSame(condition_evaluator::STATUS_UNKNOWN, $result['status']);
    }

    /**
     * OR operator passes when at least one condition passes.
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @return void
     */
    public function test_or_operator_passes_with_one_match(): void {
        $this->resetAfterTest();
        $ev = new condition_evaluator();
        // CM 1 incomplete, CM 2 complete — OR should pass.
        $state = new learner_state(self::NOW, [1 => 0, 2 => 1]);
        $json = $this->avail('|', [
            ['type' => 'completion', 'cm' => 1, 'e' => 1],
            ['type' => 'completion', 'cm' => 2, 'e' => 1],
        ]);
        $result = $ev->evaluate($json, $state);
        $this->assertTrue($result['accessible']);
        $this->assertCount(2, $result['reasons']);
    }

    /**
     * AND operator fails when any condition fails.
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @return void
     */
    public function test_and_operator_fails_with_one_mismatch(): void {
        $this->resetAfterTest();
        $ev = new condition_evaluator();
        $state = new learner_state(self::NOW, [1 => 1, 2 => 0]);
        $json = $this->avail('&', [
            ['type' => 'completion', 'cm' => 1, 'e' => 1],
            ['type' => 'completion', 'cm' => 2, 'e' => 1],
        ]);
        $result = $ev->evaluate($json, $state);
        $this->assertFalse($result['accessible']);
    }

    /**
     * Negated AND (!&) passes when at least one child fails.
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @return void
     */
    public function test_nand_operator_accessible_when_one_fails(): void {
        $this->resetAfterTest();
        $ev = new condition_evaluator();
        // Date from future fails → !& passes.
        $state = new learner_state(self::NOW);
        $json = $this->avail('!&', [['type' => 'date', 'd' => '>=', 't' => self::FUTURE]]);
        $result = $ev->evaluate($json, $state);
        $this->assertTrue($result['accessible']);
    }
}
