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
 * Server-side normaliser for simulation completion/passed/grade parameters.
 *
 * Applies the same four consistency rules as the client-side syncSimulationRow()
 * function in amd/src/simulation.js so that the simulation result is correct
 * even when JavaScript was not executed or was bypassed.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\simulation;

/**
 * Normalises raw simulation parameters to a consistent completion/pass/grade state.
 */
class simulation_state_normalizer {
    /**
     * Normalise completion, passed, and grade arrays using the four sync rules.
     *
     * Rules (mirror of JS syncSimulationRow):
     *   1. completed + completion_requires_pass → passed; grade ≥ gradepass_pct.
     *   2. passed + completion_requires_pass → completed; grade ≥ gradepass_pct.
     *   3. grade ≥ gradepass_pct → passed; if completion_requires_pass → completed.
     *   4. grade < gradepass_pct → not passed; if completion_requires_pass → not completed.
     *
     * @param array $rawcomplete int[cmid]  Submitted sim_complete values (0 or 1).
     * @param array $rawpassed   int[cmid]  Submitted sim_passed values (0 or 1).
     * @param array $rawgrade    float[cmid] Submitted sim_grade values (0–100).
     * @param array $cmmeta      array[cmid] Per-CM metadata:
     *                             'completion_requires_pass' => bool,
     *                             'gradepass_pct'            => float (0–100),
     *                             'completion_enabled'       => bool,
     *                             'has_pass_grade'           => bool.
     * @param int[] $validcmids  Whitelist of cmids belonging to this course.
     * @return array{completions: array, grades: array}
     *         Normalised completion states (0/2/3) and grade values.
     */
    public static function normalise(
        array $rawcomplete,
        array $rawpassed,
        array $rawgrade,
        array $cmmeta,
        array $validcmids
    ): array {
        $validset = array_flip($validcmids);
        $completions = [];
        $grades = [];

        // Collect all cmids mentioned in any input array.
        $allkeys = array_unique(array_merge(
            array_keys($rawcomplete),
            array_keys($rawpassed),
            array_keys($rawgrade)
        ));

        foreach ($allkeys as $cmidstr) {
            $cmid = (int) $cmidstr;
            // Reject cmids not belonging to this course.
            if (!isset($validset[$cmid])) {
                continue;
            }

            $meta            = $cmmeta[$cmid] ?? [];
            $reqpass         = !empty($meta['completion_requires_pass']);
            $gradepassthresh = (float) ($meta['gradepass_pct'] ?? 0.0);
            $completionenabled = !empty($meta['completion_enabled']);
            $haspassgrade    = !empty($meta['has_pass_grade']);

            $completed = !empty($rawcomplete[$cmidstr]);
            $passed    = !empty($rawpassed[$cmidstr]);

            // Determine whether a grade was explicitly submitted (non-empty, numeric).
            // (float)'abc' would silently become 0.0, so is_numeric() is required here.
            $rawgradestr = isset($rawgrade[$cmidstr]) ? trim((string) $rawgrade[$cmidstr]) : '';
            $gradesubmitted = ($rawgradestr !== '' && is_numeric($rawgradestr));
            $grade = $gradesubmitted ? max(0.0, min(100.0, (float) $rawgradestr)) : null;

            // Apply rule 1: completed + requires pass → passed.
            // Grade is only elevated when no grade was explicitly submitted;
            // an explicitly submitted grade is resolved by rules 3+4 below.
            if ($completed && $reqpass && $haspassgrade) {
                $passed = true;
                if (!$gradesubmitted && $gradepassthresh > 0.0) {
                    $grade = $gradepassthresh;
                }
            }

            // Apply rule 2: passed + requires pass → completed.
            // Same guard: do not elevate grade when one was explicitly submitted.
            if ($passed && $reqpass && $completionenabled) {
                $completed = true;
                if (!$gradesubmitted && $gradepassthresh > 0.0) {
                    $grade = $gradepassthresh;
                }
            }

            // Apply rules 3+4: explicitly submitted grade always takes priority.
            // This overrides any completed/passed state set by rules 1+2 above.
            if ($gradesubmitted && $grade !== null && $haspassgrade && $gradepassthresh > 0.0) {
                if ($grade >= $gradepassthresh) {
                    // Rule 3: grade >= threshold → passed (and completed if required).
                    $passed = true;
                    if ($reqpass && $completionenabled) {
                        $completed = true;
                    }
                } else {
                    // Rule 4: grade < threshold → not passed; clears completed if required.
                    $passed = false;
                    if ($reqpass) {
                        $completed = false;
                    }
                }
            }

            // Map completed/passed → completion state integer used by learner_state.
            if ($completionenabled) {
                if ($completed && $passed) {
                    $completions[$cmid] = 2; // Completed with pass.
                } else if ($completed) {
                    $completions[$cmid] = 3; // Completed without pass.
                } else {
                    $completions[$cmid] = 0; // Not completed.
                }
            }

            if ($grade !== null) {
                $grades[$cmid] = $grade;
            }
        }

        return [
            'completions' => $completions,
            'grades'      => $grades,
        ];
    }
}
