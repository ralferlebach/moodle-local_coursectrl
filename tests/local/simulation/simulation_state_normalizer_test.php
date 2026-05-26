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
 * Unit tests for simulation_state_normalizer.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\simulation;

/**
 * Tests for simulation_state_normalizer.
 *
 * @covers \local_coursectrl\local\simulation\simulation_state_normalizer
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\simulation\simulation_state_normalizer::class)]
final class simulation_state_normalizer_test extends \advanced_testcase {
    /**
     * Return meta for a CM with pass-grade-required completion.
     *
     * @return array
     */
    private function meta_with_pass(): array {
        return [
            'completion_requires_pass' => true,
            'gradepass_pct'            => 50.0,
            'completion_enabled'       => true,
            'has_pass_grade'           => true,
        ];
    }

    /**
     * Return meta for a CM with completion but no pass requirement.
     *
     * @return array
     */
    private function meta_no_pass(): array {
        return [
            'completion_requires_pass' => false,
            'gradepass_pct'            => 0.0,
            'completion_enabled'       => true,
            'has_pass_grade'           => false,
        ];
    }

    /**
     * Return meta for a CM with a pass grade but completion_requires_pass disabled.
     *
     * @return array
     */
    private function meta_grade_no_require(): array {
        return [
            'completion_requires_pass' => false,
            'gradepass_pct'            => 60.0,
            'completion_enabled'       => true,
            'has_pass_grade'           => true,
        ];
    }

    /**
     * Rule 1: completed + completion_requires_pass sets passed and grade >= threshold.
     * @covers \local_coursectrl\local\simulation\simulation_state_normalizer
     */
    public function test_rule1_completed_with_reqpass_sets_passed_and_grade(): void {
        $result = simulation_state_normalizer::normalise(
            ['10' => 1], // Completed.
            [], // Not passed.
            [], // No grade.
            [10 => $this->meta_with_pass()],
            [10]
        );
        $this->assertSame(2, $result['completions'][10], 'Should be completed+passed (2)');
        $this->assertGreaterThanOrEqual(50.0, $result['grades'][10] ?? 0.0, 'Grade must reach threshold.');
    }

    /**
     * Rule 2: passed + completion_requires_pass sets completed and grade >= threshold.
     * @covers \local_coursectrl\local\simulation\simulation_state_normalizer
     */
    public function test_rule2_passed_with_reqpass_sets_completed_and_grade(): void {
        $result = simulation_state_normalizer::normalise(
            [], // Not completed.
            ['10' => 1], // Passed.
            [], // No grade.
            [10 => $this->meta_with_pass()],
            [10]
        );
        $this->assertSame(2, $result['completions'][10], 'Should be completed+passed (2)');
        $this->assertGreaterThanOrEqual(50.0, $result['grades'][10] ?? 0.0);
    }

    /**
     * Rule 3: grade >= threshold sets passed and completed when reqpass is active.
     * @covers \local_coursectrl\local\simulation\simulation_state_normalizer
     */
    public function test_rule3_grade_above_threshold_sets_passed_and_completed(): void {
        $result = simulation_state_normalizer::normalise(
            [], // Not completed.
            [], // Not passed.
            ['10' => 75], // Grade above threshold.
            [10 => $this->meta_with_pass()],
            [10]
        );
        $this->assertSame(2, $result['completions'][10]);
        $this->assertEqualsWithDelta(75.0, $result['grades'][10], 0.01);
    }

    /**
     * Rule 4: grade < threshold clears passed and completed when reqpass is active.
     * @covers \local_coursectrl\local\simulation\simulation_state_normalizer
     */
    public function test_rule4_grade_below_threshold_clears_passed_and_completed(): void {
        $result = simulation_state_normalizer::normalise(
            ['10' => 1], // Completed (will be overridden by rule 4).
            ['10' => 1], // Passed (will be overridden by rule 4).
            ['10' => 30], // Grade below threshold of 50.
            [10 => $this->meta_with_pass()],
            [10]
        );
        $this->assertSame(0, $result['completions'][10], 'Completed must be cleared by rule 4.');
    }

    /**
     * Without pass-required, completed is not forced by grade alone.
     * @covers \local_coursectrl\local\simulation\simulation_state_normalizer
     */
    public function test_no_reqpass_grade_does_not_force_completion(): void {
        $result = simulation_state_normalizer::normalise(
            [], // Not completed.
            [], // Not passed.
            ['10' => 90], // Grade well above any threshold.
            [10 => $this->meta_no_pass()],
            [10]
        );
        // No completion set — should be absent or 0.
        $completionval = $result['completions'][10] ?? 0;
        $this->assertSame(0, $completionval, 'Completion should not be forced without reqpass.');
    }

    /**
     * Grade >= threshold sets passed even when completion_requires_pass is false.
     * @covers \local_coursectrl\local\simulation\simulation_state_normalizer
     */
    public function test_grade_sets_passed_without_reqpass(): void {
        $result = simulation_state_normalizer::normalise(
            [],
            [],
            ['10' => 70],
            [10 => $this->meta_grade_no_require()],
            [10]
        );
        // Grade above 60 threshold: passed set, but completed not forced (no reqpass).
        $completionval = $result['completions'][10] ?? 0;
        $this->assertSame(0, $completionval, 'Completed should not be set without reqpass.');
    }

    /**
     * CMIDs not in the validcmids whitelist are ignored.
     * @covers \local_coursectrl\local\simulation\simulation_state_normalizer
     */
    public function test_invalid_cmid_is_rejected(): void {
        $result = simulation_state_normalizer::normalise(
            ['999' => 1], // Cmid 999 is not in validcmids.
            [],
            [],
            [999 => $this->meta_with_pass()],
            [10] // Only cmid 10 is valid.
        );
        $this->assertArrayNotHasKey(999, $result['completions']);
    }

    /**
     * Multiple cmids are processed independently of each other.
     * @covers \local_coursectrl\local\simulation\simulation_state_normalizer
     */
    public function test_multiple_cmids_processed_independently(): void {
        $result = simulation_state_normalizer::normalise(
            ['10' => 1, '20' => 0],
            ['20' => 1],
            ['30' => 80],
            [
                10 => $this->meta_with_pass(),
                20 => $this->meta_with_pass(),
                30 => $this->meta_with_pass(),
            ],
            [10, 20, 30]
        );

        // Cmid 10: completed + reqpass → completed+passed.
        $this->assertSame(2, $result['completions'][10]);

        // Cmid 20: passed + reqpass → completed+passed.
        $this->assertSame(2, $result['completions'][20]);

        // Cmid 30: grade 80 >= 50 → passed+completed.
        $this->assertSame(2, $result['completions'][30]);
    }

    /**
     * Non-numeric grade values in raw input are silently ignored.
     * @covers \local_coursectrl\local\simulation\simulation_state_normalizer
     */
    public function test_non_numeric_grade_is_skipped(): void {
        $result = simulation_state_normalizer::normalise(
            [],
            [],
            ['10' => 'abc'],
            [10 => $this->meta_with_pass()],
            [10]
        );
        $this->assertArrayNotHasKey(10, $result['grades'], 'Non-numeric grade must be dropped.');
    }

    /**
     * Grade values are clamped to the 0-100 range.
     * @covers \local_coursectrl\local\simulation\simulation_state_normalizer
     */
    public function test_grade_is_clamped(): void {
        $result = simulation_state_normalizer::normalise(
            [],
            [],
            ['10' => 150, '20' => -5],
            [
                10 => $this->meta_with_pass(),
                20 => $this->meta_with_pass(),
            ],
            [10, 20]
        );
        $this->assertEqualsWithDelta(100.0, $result['grades'][10], 0.01);
        $this->assertEqualsWithDelta(0.0, $result['grades'][20], 0.01);
    }
}
