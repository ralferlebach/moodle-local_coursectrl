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
 * Condition evaluator for the Course Control Hub simulation engine.
 *
 * Recursively evaluates a Moodle availability JSON tree against a
 * learner_state and produces a structured result explaining which
 * conditions passed, which failed, and why.
 *
 * Supported condition types:
 *   completion   — checks assumed completion state of the given cmid.
 *   date         — compares simulated timestamp against the threshold.
 *   group        — checks assumed group membership.
 *   grouping     — checks assumed grouping membership.
 *   grade        — evaluates the simulated grade percentage against min/max
 *                  thresholds when a gradeitemmap is supplied; otherwise
 *                  returns 'unknown'.
 *   (others)     — always return 'unknown'.
 *
 * Operators handled:
 *   &   — all leaf results must be PASS (AND); unknown counts as FAIL.
 *   |   — any leaf result must be PASS (OR); unknown counts as FAIL.
 *   !&  — NAND: all leaves must FAIL (i.e. the opposite of &).
 *   !|  — NOR:  all leaves must FAIL (i.e. the opposite of |).
 *
 * Result status codes:
 *   pass    — condition satisfied.
 *   fail    — condition not satisfied.
 *   unknown — cannot be evaluated (e.g. grade condition).
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\simulation;

/**
 * Stateless recursive evaluator for Moodle availability condition trees.
 */
class condition_evaluator {
    /** @var string Condition passes. */
    public const STATUS_PASS = 'pass';

    /** @var string Condition fails. */
    public const STATUS_FAIL = 'fail';

    /** @var string Condition cannot be evaluated. */
    public const STATUS_UNKNOWN = 'unknown';

    /**
     * Grade item id → ['cmid' => int, 'grademax' => float] map.
     *
     * Populated externally by the simulation layer so that grade availability
     * conditions (which reference grade_items.id) can be resolved to cmids
     * and evaluated against the learner state's grade percentages.
     *
     * @var array<int, array{cmid: int, grademax: float}>
     */
    private array $gradeitemmap;

    /**
     * Constructor.
     *
     * @param array<int, array> $gradeitemmap Optional map of grade item id →
     *   ['cmid' => int, 'grademax' => float]. Required to evaluate grade conditions.
     */
    public function __construct(array $gradeitemmap = []) {
        $this->gradeitemmap = $gradeitemmap;
    }

    /**
     * Evaluate a raw availability JSON string against a learner state.
     *
     * @param string|null  $json  Raw availability JSON from course_modules, or null.
     * @param learner_state $state The hypothetical learner state.
     * @return array{
     *     accessible: bool,
     *     status: string,
     *     reasons: array
     * }
     *   accessible — true when the CM would be accessible to this learner.
     *   status     — overall STATUS_* constant.
     *   reasons    — flat list of per-condition result arrays.
     */
    public function evaluate(?string $json, learner_state $state): array {
        if ($json === null || $json === '') {
            return [
                'accessible' => true,
                'status' => self::STATUS_PASS,
                'reasons' => [],
            ];
        }

        $tree = json_decode($json, true);
        if (!is_array($tree)) {
            return [
                'accessible' => true,
                'status' => self::STATUS_PASS,
                'reasons' => [],
            ];
        }

        $reasons = [];
        $result = $this->eval_node($tree, $state, $reasons);

        return [
            'accessible' => $result === self::STATUS_PASS,
            'status' => $result,
            'reasons' => $reasons,
        ];
    }

    /**
     * Recursively evaluate a node (either a condition set or leaf condition).
     *
     * @param array         $node    Decoded JSON node.
     * @param learner_state $state   Learner state.
     * @param array         $reasons Reasons list (modified in place).
     * @return string STATUS_PASS | STATUS_FAIL | STATUS_UNKNOWN
     */
    private function eval_node(array $node, learner_state $state, array &$reasons): string {
        // Leaf condition (has a 'type' key).
        if (isset($node['type'])) {
            return $this->eval_leaf($node, $state, $reasons);
        }

        // Condition set (has an 'op' key and 'c' children).
        $op = $node['op'] ?? '&';
        $children = $node['c'] ?? [];
        if (empty($children)) {
            return self::STATUS_PASS;
        }

        $childresults = [];
        foreach ($children as $child) {
            $childresults[] = $this->eval_node($child, $state, $reasons);
        }

        return $this->combine($op, $childresults);
    }

    /**
     * Evaluate a single leaf condition.
     *
     * @param array         $condition Decoded condition node.
     * @param learner_state $state     Learner state.
     * @param array         $reasons   Reasons list (modified in place).
     * @return string STATUS_*
     */
    private function eval_leaf(array $condition, learner_state $state, array &$reasons): string {
        $type = $condition['type'] ?? '';

        switch ($type) {
            case 'completion':
                return $this->eval_completion($condition, $state, $reasons);

            case 'date':
                return $this->eval_date($condition, $state, $reasons);

            case 'group':
                return $this->eval_group($condition, $state, $reasons);

            case 'grouping':
                return $this->eval_grouping($condition, $state, $reasons);

            case 'grade':
                return $this->eval_grade($condition, $state, $reasons);

            default:
                $reasons[] = [
                    'type' => $type ?: 'unknown',
                    'status' => self::STATUS_UNKNOWN,
                    'detail' => 'unsupported_condition_type',
                ];
                return self::STATUS_UNKNOWN;
        }
    }

    /**
     * Evaluate a completion condition.
     *
     * @param array         $condition Condition node.
     * @param learner_state $state     Learner state.
     * @param array         $reasons   Reasons list (modified in place).
     * @return string STATUS_*
     */
    private function eval_completion(
        array $condition,
        learner_state $state,
        array &$reasons
    ): string {
        $cmid = (int) ($condition['cm'] ?? 0);
        $expected = (int) ($condition['e'] ?? 1);
        $actual = $state->get_completion($cmid);
        $pass = $this->completion_matches($actual, $expected);
        $status = $pass ? self::STATUS_PASS : self::STATUS_FAIL;
        $reasons[] = [
            'type' => 'completion',
            'status' => $status,
            'cmid' => $cmid,
            'expected' => $expected,
            'actual' => $actual,
        ];
        return $status;
    }

    /**
     * Evaluate a date condition.
     *
     * @param array         $condition Condition node.
     * @param learner_state $state     Learner state.
     * @param array         $reasons   Reasons list (modified in place).
     * @return string STATUS_*
     */
    private function eval_date(array $condition, learner_state $state, array &$reasons): string {
        $direction = (string) ($condition['d'] ?? '>=');
        $threshold = (int) ($condition['t'] ?? 0);
        $now = $state->timestamp;
        $pass = $direction === '>=' ? $now >= $threshold : $now < $threshold;
        $status = $pass ? self::STATUS_PASS : self::STATUS_FAIL;
        $reasons[] = [
            'type' => 'date',
            'status' => $status,
            'direction' => $direction,
            'threshold' => $threshold,
            'simulated_ts' => $now,
        ];
        return $status;
    }

    /**
     * Evaluate a group membership condition.
     *
     * @param array         $condition Condition node.
     * @param learner_state $state     Learner state.
     * @param array         $reasons   Reasons list (modified in place).
     * @return string STATUS_*
     */
    private function eval_group(array $condition, learner_state $state, array &$reasons): string {
        $groupid = (int) ($condition['id'] ?? 0);
        $pass = $groupid === 0 || $state->is_in_group($groupid);
        $status = $pass ? self::STATUS_PASS : self::STATUS_FAIL;
        $reasons[] = [
            'type' => 'group',
            'status' => $status,
            'groupid' => $groupid,
        ];
        return $status;
    }

    /**
     * Evaluate a grouping membership condition.
     *
     * @param array         $condition Condition node.
     * @param learner_state $state     Learner state.
     * @param array         $reasons   Reasons list (modified in place).
     * @return string STATUS_*
     */
    private function eval_grouping(
        array $condition,
        learner_state $state,
        array &$reasons
    ): string {
        $groupingid = (int) ($condition['id'] ?? 0);
        $pass = $groupingid === 0 || $state->is_in_grouping($groupingid);
        $status = $pass ? self::STATUS_PASS : self::STATUS_FAIL;
        $reasons[] = [
            'type' => 'grouping',
            'status' => $status,
            'groupingid' => $groupingid,
        ];
        return $status;
    }

    /**
     * Evaluate a grade condition against the simulated learner grade.
     *
     * Grade condition JSON fields:
     *   id  — grade_items.id (resolved via gradeitemmap to cmid)
     *   min — minimum grade percentage for condition to pass (optional)
     *   max — maximum grade percentage (exclusive) for condition to pass (optional)
     *
     * Returns STATUS_UNKNOWN when no grade is simulated for this item.
     *
     * @param array         $condition Condition node.
     * @param learner_state $state     Learner state.
     * @param array         $reasons   Reasons list (modified in place).
     * @return string STATUS_*
     */
    private function eval_grade(array $condition, learner_state $state, array &$reasons): string {
        $itemid = (int) ($condition['id'] ?? 0);
        $min = isset($condition['min']) ? (float) $condition['min'] : null;
        $max = isset($condition['max']) ? (float) $condition['max'] : null;

        $cmid = $this->gradeitemmap[$itemid]['cmid'] ?? null;
        $grade = $cmid !== null ? $state->get_grade($cmid) : null;

        if ($grade === null) {
            $reasons[] = [
                'type'   => 'grade',
                'status' => self::STATUS_UNKNOWN,
                'detail' => 'grade_not_simulated',
                'itemid' => $itemid,
                // Grade item may not map to any CM; cmid will be null in that case.
                'cmid'   => $cmid,
                'min'    => $min,
                'max'    => $max,
            ];
            return self::STATUS_UNKNOWN;
        }

        $pass = ($min === null || $grade >= $min) && ($max === null || $grade < $max);
        $status = $pass ? self::STATUS_PASS : self::STATUS_FAIL;
        $reasons[] = [
            'type'      => 'grade',
            'status'    => $status,
            'itemid'    => $itemid,
            'cmid'      => $cmid,
            'grade'     => $grade,
            'min'       => $min,
            'max'       => $max,
        ];
        return $status;
    }

    /**
     * Combine child result statuses using a Moodle availability operator.
     *
     * @param string   $op      Moodle availability operator ('&', '|', '!&', '!|').
     * @param string[] $results Child STATUS_* values.
     * @return string STATUS_*
     */
    private function combine(string $op, array $results): string {
        // Any unknown in AND context → unknown if no fail present.
        // Strategy: compute ignoring unknown first, then check for unknowns.
        $negate = str_starts_with($op, '!');
        $baseop = $negate ? substr($op, 1) : $op;

        $passcount = count(array_filter($results, fn($r) => $r === self::STATUS_PASS));
        $failcount = count(array_filter($results, fn($r) => $r === self::STATUS_FAIL));
        $unknowncount = count(array_filter($results, fn($r) => $r === self::STATUS_UNKNOWN));
        $total = count($results);

        if ($baseop === '&') {
            // All must pass.
            if ($failcount > 0) {
                $base = self::STATUS_FAIL;
            } else if ($unknowncount > 0) {
                $base = self::STATUS_UNKNOWN;
            } else {
                $base = self::STATUS_PASS;
            }
        } else {
            // Operator |: at least one must pass.
            if ($passcount > 0) {
                $base = self::STATUS_PASS;
            } else if ($unknowncount > 0 && $failcount < $total) {
                $base = self::STATUS_UNKNOWN;
            } else {
                $base = self::STATUS_FAIL;
            }
        }

        if (!$negate) {
            return $base;
        }
        // Negation: pass↔fail, unknown stays unknown.
        if ($base === self::STATUS_PASS) {
            return self::STATUS_FAIL;
        }
        if ($base === self::STATUS_FAIL) {
            return self::STATUS_PASS;
        }
        return self::STATUS_UNKNOWN;
    }

    /**
     * Check whether an actual completion state satisfies the expected state.
     *
     * Moodle's expected state semantics:
     *   e=0 → must be INCOMPLETE (state=0)
     *   e=1 → must be COMPLETE in any form (state 1, 2, or 3)
     *   e=2 → must be COMPLETE_PASS (state=2)
     *   e=3 → must be COMPLETE_FAIL (state=3)
     *
     * @param int $actual   Actual completion state (0/1/2/3).
     * @param int $expected Required state from availability JSON.
     * @return bool
     */
    private function completion_matches(int $actual, int $expected): bool {
        if ($expected === 0) {
            return $actual === 0;
        }
        if ($expected === 1) {
            // Any completed state satisfies "must be complete".
            return $actual === 1 || $actual === 2 || $actual === 3;
        }
        // Exact match required for e=2 and e=3.
        return $actual === $expected;
    }

    /**
     * Evaluate conditions and return OR-grouped reason arrays for structured display.
     *
     * Returns an array of groups. Each group represents one OR-branch (or the
     * whole condition set for AND-only conditions). Within a group, all conditions
     * must be satisfied simultaneously (AND). Between groups, one group suffices (OR).
     *
     * @param string|null   $json  Raw availability JSON from course_modules.availability.
     * @param learner_state $state Current learner state.
     * @return array[] Array of groups; each group is an array of raw reason arrays.
     */
    public function evaluate_groups(?string $json, learner_state $state): array {
        if ($json === null || $json === '') {
            return [];
        }
        $tree = json_decode($json, true);
        if (!is_array($tree)) {
            return [];
        }
        return $this->build_display_groups($tree, $state);
    }

    /**
     * Recursively build display groups from an availability tree node.
     *
     * @param array         $node  Decoded availability JSON node.
     * @param learner_state $state Learner state.
     * @return array[]
     */
    private function build_display_groups(array $node, learner_state $state): array {
        if (isset($node['type'])) {
            // Leaf: single group with single condition.
            $reasons = [];
            $this->eval_leaf($node, $state, $reasons);
            return [$reasons];
        }
        $op = $node['op'] ?? '&';
        $children = $node['c'] ?? [];
        if ($op === '|' || $op === '!|') {
            // OR at top level: each child is its own branch.
            $groups = [];
            foreach ($children as $child) {
                $childreasons = [];
                $this->eval_node($child, $state, $childreasons);
                $groups[] = $childreasons;
            }
            return $groups;
        }
        // AND: everything in a single group.
        $reasons = [];
        foreach ($children as $child) {
            $this->eval_node($child, $state, $reasons);
        }
        return [$reasons];
    }
}
