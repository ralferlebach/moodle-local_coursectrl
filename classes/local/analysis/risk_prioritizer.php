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
 */
class risk_prioritizer {
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
