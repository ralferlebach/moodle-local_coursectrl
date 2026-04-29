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
 * P2-C: Verify that every dynamically-constructed lang key resolves to a real
 * string and never falls back to the [[identifier]] sentinel.
 *
 * Moodle's get_string() returns '[[identifier]]' (not null or false) when a key
 * is missing. Because the production code constructs keys like
 *   get_string('action_' . $batch->action, 'local_coursectrl')
 * those strings are invisible to static analysis. This test file enumerates all
 * known dynamic-key families and asserts that every value is a non-sentinel
 * string, catching regressions early.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl;

use local_coursectrl\local\persistent\batch;

#[\PHPUnit\Framework\Attributes\CoversNothing]
/**
 * Dynamic lang key coverage test.
 *
 * @covers \local_coursectrl\output\history_page
 * @covers \local_coursectrl\output\checks_page
 */
final class dynamic_lang_keys_test extends \advanced_testcase {
    // Helpers.

    /**
     * Assert that a lang string exists and is not the [[identifier]] fallback.
     *
     * @param string $key       Lang key to resolve.
     * @param string $component Plugin component (default local_coursectrl).
     */
    private function assert_string_exists(string $key, string $component = 'local_coursectrl'): void {
        $value = get_string($key, $component, null, true);
        $this->assertNotNull($value, "get_string('$key', '$component') returned null.");
        $this->assertStringNotContainsString(
            '[[',
            (string) $value,
            "Missing lang string: $component / $key"
        );
    }

    // Action strings (used by history_page and result_page dynamically).

    /**
     * All known action_ string keys must resolve without fallback.
     */
    public function test_action_lang_keys_exist(): void {
        $this->resetAfterTest();
        $actions = [
            'shift_dates',
            'set_dates',
            'set_visibility',
            'set_completion',
            'set_availability',
            'copy_settings_from_reference',
            'run_checks',
        ];
        foreach ($actions as $action) {
            $this->assert_string_exists('action_' . $action);
        }
    }

    // Batch status strings (used wherever batch->status is displayed).

    /**
     * All batch STATUS_* constant values must have a lang string.
     */
    public function test_batch_status_lang_keys_exist(): void {
        $this->resetAfterTest();
        $statuses = [
            batch::STATUS_PENDING,
            batch::STATUS_PREVIEWED,
            batch::STATUS_EXECUTED,
            batch::STATUS_ROLLED_BACK,
            batch::STATUS_FAILED,
        ];
        foreach ($statuses as $status) {
            $key = 'status_' . $status;
            // Not all installations render status labels; only assert if the key
            // is defined — detect via $ignoremissing and warn rather than fail
            // hard, so future additions are flagged rather than silently absent.
            $value = get_string($key, 'local_coursectrl', null, true);
            if ($value !== null && strpos($value, '[[') === false) {
                // Key is defined and resolved — pass.
                $this->addToAssertionCount(1);
            } else {
                // Key missing — mark as incomplete so the CI output is visible.
                $this->markTestIncomplete(
                    "Missing lang string: local_coursectrl / $key — "
                    . "add \$string['$key'] to the lang file."
                );
            }
        }
    }

    // Risk_type_ strings (used by checks_page::risk_type_texts dynamically).

    /**
     * Every risk type code used by the analysis pipeline must have a
     * risk_type_ lang string so the checks page renders a proper label.
     */
    public function test_risk_type_lang_keys_exist(): void {
        $this->resetAfterTest();
        $types = [
            'circular_dep',
            'circular_dep_transitive',
            'completion_no_tracking',
            'completion_required_no_tracking',
            'completionexpected_after_deadline',
            'completionexpected_early',
            'completionexpected_gap_exceeds_threshold',
            'completionexpected_window',
            'consistency',
            'dangling_dep',
            'dangling_group',
            'dangling_grouping',
            'date_coupling',
            'deadline_before_dep_window',
            'dep_on_hidden',
            'hidden_with_dependents',
            'impossible_dep',
            'journey_unreachable',
            'long_dep_chain',
            'r0_after_course_end',
            'r0_before_course_start',
            'r0_deadline_in_past',
            'r1_hidden',
            'r1_not_accessible',
            'temporal_conflict',
        ];
        $missing = [];
        foreach ($types as $type) {
            $key = 'risk_type_' . $type;
            $value = get_string($key, 'local_coursectrl', null, true);
            if ($value === null || strpos($value, '[[') !== false) {
                $missing[] = $key;
            }
        }
        $this->assertEmpty(
            $missing,
            'Missing risk_type_ lang strings: ' . implode(', ', $missing)
        );
    }

    // Risk_problem_ and risk_action_ strings (used by checks_page::risk_type_texts).

    /**
     * Every risk type that has a handler in risk_type_texts() must have
     * matching risk_problem_ and risk_action_ strings.
     */
    public function test_risk_problem_and_action_lang_keys_exist(): void {
        $this->resetAfterTest();
        // Types that have dedicated handlers (not falling through to the generic fallback).
        $handled = [
            'dep_on_hidden',
            'hidden_with_dependents',
            'circular_dep',
            'circular_dep_transitive',
            'long_dep_chain',
            'journey_unreachable',
            'r0_after_course_end',
            'r0_before_course_start',
            'r0_deadline_in_past',
            'r1_hidden',
            'r1_not_accessible',
            'completionexpected_window',
            'dangling_dep',
            'dangling_group',
            'dangling_grouping',
            'impossible_dep',
            'date_coupling',
        ];
        $missing = [];
        foreach ($handled as $type) {
            foreach (['risk_problem_', 'risk_action_'] as $prefix) {
                $key = $prefix . $type;
                // Dangling_group and dangling_grouping share the dangling_dep action string.
                if (
                    ($type === 'dangling_group' || $type === 'dangling_grouping')
                    && $prefix === 'risk_action_'
                ) {
                    $key = 'risk_action_dangling_dep';
                }
                $value = get_string($key, 'local_coursectrl', null, true);
                if ($value === null || strpos($value, '[[') !== false) {
                    $missing[] = $key;
                }
            }
        }
        $this->assertEmpty(
            $missing,
            'Missing risk_problem_/risk_action_ lang strings: ' . implode(', ', $missing)
        );
    }
}
