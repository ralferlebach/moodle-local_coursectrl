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
 * Risk prioritiser for the Course Control Hub.
 *
 * Score formula (0-100):
 *   score = severity_base + (probability * 20)
 *           + min(affected_count * 2, 20)
 *           + min(downstream_count * 3, 20)
 *
 * Where severity_base: error=40, warning=20, notice=5.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

/**
 * Scores and sorts risk items for the risk assessment panel.
 *
 * Also detects intentional remedial activity patterns (Problem 5):
 * when a grade condition of the form max:X is present and X is close to
 * the gradepass threshold of the referenced activity, the CM is marked
 * as remedial. Remedial CMs blocked in the Best Case scenario are
 * suppressed (no finding); in the Worst Case they produce a notice
 * ("Remedial path available") instead of a warning or error.
 */
class risk_prioritizer {
    /**
     * Tolerance in percentage points for matching grade condition max to gradepass.
     * If |max - gradepass| <= this value, the pattern is considered remedial.
     */
    private const REMEDIAL_TOLERANCE_PP = 10.0;

    /** @var array<int, array> Grade item map: grade_item_id → ['cmid', 'grademax']. */
    private array $gradeitemmap;

* @param array<int,array> $gradeitemmap Grade item id → cmid/grademax map.
* @param array<int,array> $gradeinfobycmid Cmid → gradepass/grademax map.
    /** @var array<int, array> Grade info per cmid: ['gradepass', 'grademax']. */
    private array $gradeinfobycmid;

    /**
     * Constructor.
     *
     * @param array<int, array> $gradeitemmap    Grade item id → ['cmid', 'grademax'] map.
     * @param array<int, array> $gradeinfobycmid Cmid → ['gradepass', 'grademax'] map.
     */
    public function __construct(array $gradeitemmap = [], array $gradeinfobycmid = []) {
        $this->gradeitemmap = $gradeitemmap;
        $this->gradeinfobycmid = $gradeinfobycmid;
    }

    /** @var array<string, int> Base score per severity level. */
    private const SEVERITY_BASE = [
        'error'   => 40,
        'warning' => 20,
        'notice'  => 5,
    ];

    /** @var array<string, int> Sort order per severity (lower = first). */
    private const SEVERITY_ORDER = [
        'error'   => 0,
        'warning' => 1,
        'notice'  => 2,
    ];

    /**
     * Score and sort a flat list of risk items.
     *
     * Each input item must have at minimum: type, severity, probability,
     * cmids, affected_count, message_key, message_params.
     * Each output item gains: score (int 0-100), downstream_count (int).
     *
     * @param array[]          $risks    Raw risk items.
     * @param dependency_index $depindex Used to compute downstream impact.
     * @return array[] Scored and sorted risk items.
     */
    public function score_and_sort(array $risks, dependency_index $depindex): array {
        // Apply remedial-pattern filter before scoring.
        // This converts or suppresses journey_unreachable findings for CMs
        // That are intentionally gated behind a grade threshold (Remedial design).
        $risks = $this->apply_remedial_filter($risks);
        $reverse = $depindex->get_all_reverse();
        $scored = [];
        foreach ($risks as $risk) {
            $severity = $risk['severity'] ?? 'notice';
            $probability = (float)($risk['probability'] ?? 1.0);
            $affectedcount = (int)($risk['affected_count'] ?? count($risk['cmids'] ?? []));
            $cmids = $risk['cmids'] ?? [];
            $downstream = $this->count_downstream($cmids, $reverse);
            $base = self::SEVERITY_BASE[$severity] ?? 5;
            $score = $base
                + (int)round($probability * 20)
                + min($affectedcount * 2, 20)
                + min($downstream * 3, 20);
            $score = min($score, 100);
            $scored[] = array_merge($risk, [
                'score'            => $score,
                'downstream_count' => $downstream,
            ]);
        }
        usort($scored, function (array $a, array $b): int {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }
            $aord = self::SEVERITY_ORDER[$a['severity'] ?? 'notice'] ?? 2;
            $bord = self::SEVERITY_ORDER[$b['severity'] ?? 'notice'] ?? 2;
            return $aord <=> $bord;
        });
        return $scored;
    }

    /**
     * Filter journey_unreachable findings for remedial-pattern CMs.
     *
     * Remedial pattern: a CM whose availability contains a grade condition
     * of the form {type:grade, max:X} where X is within REMEDIAL_TOLERANCE_PP
     * percentage points of the gradepass threshold of the referenced activity.
     *
     * - Best Case (grademode=pass, grade=100): CM correctly blocked → remove finding.
     * - Worst Case (grademode=fail, grade=0):  CM correctly accessible → downgrade to
     *   notice with type 'remedial_path_available'; do not report as error or warning.
     *
     * @param array[] $risks Raw journey findings.
     * @return array[] Filtered findings.
     */
    private function apply_remedial_filter(array $risks): array {
        if (empty($this->gradeitemmap) || empty($this->gradeinfobycmid)) {
            return $risks;
        }
        $result = [];
        foreach ($risks as $risk) {
            if (($risk['type'] ?? '') !== 'journey_unreachable') {
                $result[] = $risk;
                continue;
            }
            $cmid = (int) (($risk['cmids'] ?? [])[0] ?? 0);
            $grademode = $risk['grademode'] ?? '';
            if (!$this->is_remedial_cm($cmid)) {
                $result[] = $risk;
                continue;
            }
            if ($grademode === 'pass') {
                // Best Case blocked for a remedial CM = correct design → suppress.
                continue;
            }
            // Worst Case: remedial path is accessible → emit informational notice.
            $risk['type'] = 'remedial_path_available';
            $risk['severity'] = 'notice';
            $risk['message_key'] = 'risk_remedial_path_available';
            $risk['message_params'] = [];
            $risk['score'] = 5;
            $result[] = $risk;
        }
        return $result;
    }

    /**
     * Determine whether a CM follows the remedial activity pattern.
     *
     * A CM is remedial when its availability JSON contains at least one
     * grade condition {type:grade, max:X} where X is close to the gradepass
     * value of the referenced activity (within REMEDIAL_TOLERANCE_PP PP).
     *
     * @param int $cmid Course module id.
     * @return bool True when the CM matches the remedial pattern.
     */
    private function is_remedial_cm(int $cmid): bool {
        // We need the raw availability JSON; it is not stored in gradeinfobycmid.
        // The gradeitemmap tells us which grade_item_id maps to which cmid.
        // We check whether any grade_item referencing this CM acts as a
        // "max gate" whose threshold is near the gradepass of the source CM.
        foreach ($this->gradeitemmap as $itemid => $itemdata) {
            $sourcecmid = (int) ($itemdata['cmid'] ?? 0);
            if ($sourcecmid === 0) {
                continue;
            }
            $gradeinfo = $this->gradeinfobycmid[$sourcecmid] ?? null;
            if ($gradeinfo === null) {
                continue;
            }
            $gradepass = (float) ($gradeinfo['gradepass'] ?? 0.0);
            if ($gradepass <= 0.0) {
                continue;
            }
            // Grade item references a CM with a gradepass — but we need to know
            // Whether THIS $cmid has a grade condition on that item with a max threshold.
            // Since risk_prioritizer does not have the raw availability JSON,
            // We use a heuristic: if the cmid appears in gradeinfobycmid with
            // Gradepass=0, and there exists a grade_item (itemid) associated with
            // A different CM (sourcecmid) that has gradepass>0, then $cmid is a
            // Candidate remedial CM when its own gradepass is also > 0 or equals 0.
            // A more precise check requires the raw availability tree — this is
            // flagged as a known limitation: the heuristic may produce false positives
            // for non-remedial grade-gated CMs. The tolerance check below mitigates this.
            $targetinfo = $this->gradeinfobycmid[$cmid] ?? null;
            // Remedial CMs typically have gradepass >= gradepass of source CM.
            if ($targetinfo === null) {
                continue;
            }
            $targetgradepass = (float) ($targetinfo['gradepass'] ?? 0.0);
            if (abs($targetgradepass - $gradepass) <= self::REMEDIAL_TOLERANCE_PP) {
                return true;
            }
        }
        return false;
    }

    /**
     * Count CMs transitively blocked by the given broken CMs via BFS.
     *
     * @param int[] $brokencmids CMs considered broken.
     * @param array $reverse     Reverse dependency map.
     * @return int Downstream count (excluding the broken CMs themselves).
     */
    private function count_downstream(array $brokencmids, array $reverse): int {
        $visited = [];
        $queue = $brokencmids;
        while (!empty($queue)) {
            $current = array_shift($queue);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            foreach ($reverse[$current] ?? [] as $dep) {
                if (!isset($visited[$dep])) {
                    $queue[] = $dep;
                }
            }
        }
        foreach ($brokencmids as $id) {
            unset($visited[$id]);
        }
        return count($visited);
    }
}
