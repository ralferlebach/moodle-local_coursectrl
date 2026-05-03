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
 * Course completion reachability checker for the Course Control Hub.
 *
 * Problem solved
 * The existing checks (R0–R7) inspect individual activities and time windows
 * but do not verify whether the course completion criteria defined in
 * mdl_course_completion_criteria are actually achievable by any learner.
 *
 * This checker activates only for courses with enablecompletion=1 and
 * examines each completion criterion against the set of valid learner
 * profiles derived by group_profile_extractor:
 *
 *   criteriatype=4 (Activity)
 *     → Is the referenced CM visible and reachable in at least one profile?
 *
 *   criteriatype=6 (Grade / gradepass)
 *     → Is the CM reachable AND does its gradepass threshold look achievable?
 *       (Heuristic: gradepass <= grademax and grademax > 0.)
 *
 * Important distinction enforced here:
 *   gradepass on a grade_item is a PEDAGOGICAL threshold (affects availability
 *   conditions and sim_passed). It is NOT a course completion criterion unless
 *   explicitly listed in mdl_course_completion_criteria with criteriatype=6.
 *   The checker enforces this boundary and never treats gradepass alone as an
 *   implicit completion requirement.
 *
 * Findings produced
 *   error   — course completion unreachable for every valid learner profile.
 *   warning — course completion unreachable for some learner profiles.
 *   notice  — all profiles can complete the course; informational summary.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;
use local_coursectrl\local\simulation\condition_evaluator;
use local_coursectrl\local\simulation\learner_state;

/**
 * Verifies that course completion criteria are reachable by at least one learner profile.
 */
class completion_reachability_checker {
    /** @var int Moodle criteriatype constant for activity completion. */
    private const CRITERIATYPE_ACTIVITY = 4;

    /** @var int Moodle criteriatype constant for grade pass. */
    private const CRITERIATYPE_GRADE = 6;

    /**
     * Run completion reachability checks for a course.
     *
     * Returns an empty array when enablecompletion=0 or no criteria are defined.
     *
     * @param int $courseid Course id.
     * @param cm_item[] $cms Course modules keyed by cmid.
     * @param array[] $groupprofiles Learner profiles from group_profile_extractor.
     * @param array $gradeitemmap Grade item id → ['cmid', 'grademax'].
     * @param array $gradeinfobycmid Cmid → ['gradepass', 'grademax'].
     * @return array[] Risk findings (may be empty).
     */
    public function check(
        int $courseid,
        array $cms,
        array $groupprofiles,
        array $gradeitemmap,
        array $gradeinfobycmid
    ): array {
        global $DB;

        // Only run for courses with completion tracking enabled.
        $enablecompletion = (int) $DB->get_field('course', 'enablecompletion', ['id' => $courseid]);
        if ($enablecompletion !== 1) {
            return [];
        }

        // Load completion criteria.
        $criteria = $DB->get_records('course_completion_criteria', ['course' => $courseid]);
        if (empty($criteria)) {
            return [];
        }

        $evaluator = new condition_evaluator($gradeitemmap);

        // Build a best-case grade set: all graded CMs have score 100.
        // This ensures grade-based availability conditions (e.g. grade < 75
        // for remedial CMs) evaluate correctly instead of returning UNKNOWN.
        // Main path CMs with no conditions are always accessible.
        // Remedial CMs gated by a max-grade condition are correctly blocked.
        $bestcasegrades = [];
        foreach ($gradeinfobycmid as $gradecmid => $info) {
            $bestcasegrades[(int) $gradecmid] = 100.0;
        }

        // Classify criteria by type; skip types we cannot analyse.
        $activitycriteria = []; // Cmid → criterion record.
        $gradecriteria = [];    // Cmid → criterion record.

        foreach ($criteria as $crit) {
            $type = (int) $crit->criteriatype;
            $cmid = (int) ($crit->moduleinstance ?? 0);
            if ($cmid === 0) {
                continue;
            }
            if ($type === self::CRITERIATYPE_ACTIVITY) {
                $activitycriteria[$cmid] = $crit;
            } else if ($type === self::CRITERIATYPE_GRADE) {
                $gradecriteria[$cmid] = $crit;
            }
        }

        if (empty($activitycriteria) && empty($gradecriteria)) {
            return [];
        }

        // For each profile, determine which criteria are satisfiable.
        // A criterion is satisfiable when the CM is visible, its availability
        // conditions pass for the profile, and (grade criteria only) gradepass
        // does not exceed grademax.
        $profilecount = count($groupprofiles);
        $failingprofiles = []; // Profile indexes that cannot complete the course.
        $failingcmids = [];    // Cmids whose criteria are never satisfiable.

        foreach ($groupprofiles as $profileidx => $profile) {
            $groupids = (array) ($profile['groupids'] ?? []);
            $groupingids = (array) ($profile['groupingids'] ?? []);
            $state = new learner_state(time(), [], $groupids, $groupingids, $bestcasegrades);

            $profilecomplete = true;

            foreach ($activitycriteria as $cmid => $crit) {
                if (!isset($cms[$cmid]) || !$cms[$cmid]->visible) {
                    $profilecomplete = false;
                    $failingcmids[$cmid] = true;
                    continue;
                }
                $eval = $evaluator->evaluate($cms[$cmid]->availability, $state);
                // Treat UNKNOWN as accessible: if we cannot determine availability,
                // Assume the criterion might be satisfiable rather than failing.
                if ($eval['status'] === 'fail') {
                    $profilecomplete = false;
                    $failingcmids[$cmid] = true;
                }
            }

            foreach ($gradecriteria as $cmid => $crit) {
                if (!isset($cms[$cmid]) || !$cms[$cmid]->visible) {
                    $profilecomplete = false;
                    $failingcmids[$cmid] = true;
                    continue;
                }
                $eval = $evaluator->evaluate($cms[$cmid]->availability, $state);
                // Treat UNKNOWN as accessible — only explicit FAIL blocks this criterion.
                if ($eval['status'] === 'fail') {
                    $profilecomplete = false;
                    $failingcmids[$cmid] = true;
                    continue;
                }
                // Check gradepass achievability heuristic.
                $info = $gradeinfobycmid[$cmid] ?? null;
                if ($info !== null) {
                    $gradepass = (float) ($info['gradepass'] ?? 0.0);
                    $grademax = (float) ($info['grademax'] ?? 100.0);
                    if ($grademax <= 0.0 || ($gradepass > 0.0 && $gradepass > $grademax)) {
                        // Pass threshold exceeds maximum achievable grade — impossible.
                        $profilecomplete = false;
                        $failingcmids[$cmid] = true;
                    }
                }
            }

            if (!$profilecomplete) {
                $failingprofiles[] = $profileidx;
            }
        }

        $failingcount = count($failingprofiles);

        if ($failingcount === 0) {
            // All profiles can complete — emit informational notice only.
            return [[
                'type'           => 'completion_reachable',
                'severity'       => 'notice',
                'probability'    => 1.0,
                'cmids'          => [],
                'affected_count' => 0,
                'score'          => 5,
                'message_key'    => 'risk_completion_reachable',
                'message_params' => ['profiles' => $profilecount],
                'cascade_cmids'  => [],
                'cascade_count'  => 0,
            ]];
        }

        $severity = $failingcount === $profilecount ? 'error' : 'warning';
        $score = $severity === 'error' ? 90 : 50;
        $affectedcmids = array_keys($failingcmids);

        return [[
            'type'           => 'completion_unreachable',
            'severity'       => $severity,
            'probability'    => 1.0,
            'cmids'          => $affectedcmids,
            'affected_count' => count($affectedcmids),
            'score'          => $score,
            'message_key'    => 'risk_completion_unreachable',
            'message_params' => [
                'failing'  => $failingcount,
                'total'    => $profilecount,
            ],
            'cascade_cmids'  => [],
            'cascade_count'  => 0,
            'has_escape'     => false,
            'escape_type'    => 'none',
        ]];
    }
}
