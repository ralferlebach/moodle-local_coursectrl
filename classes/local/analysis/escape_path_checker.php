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
 * Escape-path checker for the Course Control Hub.
 *
 * For each finding produced by dead_end_detector, determines whether a
 * corrective action exists and whether fixing it would unblock further CMs.
 *
 * Each escape-path result:
 *   finding_type   string   The dead-end type this escape path addresses.
 *   cmids          int[]    The cmids from the original finding.
 *   has_escape     bool     True if a structural fix is available.
 *   escape_type    string   'enable_completion' | 'unhide_cm' | 'break_cycle' | 'none'
 *   cascade_cmids  int[]    Additional cmids that would be unblocked by this fix.
 *   cascade_count  int      Number of additionally unblocked CMs.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;

/**
 * Analyses escape paths for dead-end findings.
 */
class escape_path_checker {
    /**
     * Analyse escape paths for a set of dead-end findings.
     *
     * @param array[]          $findings Dead-end findings from dead_end_detector::detect().
     * @param cm_item[]        $cms      Course modules keyed by cmid.
     * @param dependency_index $depindex Pre-built dependency index.
     * @return array[] Escape-path results, one per finding.
     */
    public function analyse(array $findings, array $cms, dependency_index $depindex): array {
        $results = [];
        foreach ($findings as $finding) {
            $results[] = $this->analyse_finding($finding, $cms, $depindex);
        }
        return $results;
    }

    /**
     * Analyse escape path for a single finding.
     *
     * @param array            $finding
     * @param cm_item[]        $cms
     * @param dependency_index $depindex
     * @return array
     */
    private function analyse_finding(
        array $finding,
        array $cms,
        dependency_index $depindex
    ): array {
        $type = $finding['type'];
        $cmids = $finding['cmids'];

        if ($type === 'circular_dep') {
            return $this->escape_circular($cmids, $cms, $depindex);
        }
        if ($type === 'dep_on_hidden') {
            return $this->escape_hidden($cmids, $cms, $depindex);
        }
        if ($type === 'completion_no_tracking') {
            return $this->escape_no_tracking($cmids, $cms, $depindex);
        }
        return [
            'finding_type'  => $type,
            'cmids'         => $cmids,
            'has_escape'    => false,
            'escape_type'   => 'none',
            'cascade_cmids' => [],
            'cascade_count' => 0,
        ];
    }

    /**
     * Escape path for circular dependency: break one edge in the cycle.
     *
     * The fix is always available (remove one dependency). The cascade
     * counts how many other CMs become reachable once the cycle is broken.
     *
     * @param int[]            $cmids    CMs in the cycle.
     * @param cm_item[]        $cms
     * @param dependency_index $depindex
     * @return array
     */
    private function escape_circular(
        array $cmids,
        array $cms,
        dependency_index $depindex
    ): array {
        $cascade = $this->collect_cascade_unblock($cmids, $depindex);
        return [
            'finding_type'  => 'circular_dep',
            'cmids'         => $cmids,
            'has_escape'    => true,
            'escape_type'   => 'break_cycle',
            'cascade_cmids' => $cascade,
            'cascade_count' => count($cascade),
        ];
    }

    /**
     * Escape path for dependency on hidden CM: unhide the CM.
     *
     * @param int[]            $cmids    [dependent_cmid, hidden_cmid].
     * @param cm_item[]        $cms
     * @param dependency_index $depindex
     * @return array
     */
    private function escape_hidden(
        array $cmids,
        array $cms,
        dependency_index $depindex
    ): array {
        // Cmids[1] is the hidden CM.
        $hiddencmid = $cmids[1] ?? null;
        $cascade = $hiddencmid !== null
            ? $this->collect_cascade_unblock([$hiddencmid], $depindex)
            : [];
        return [
            'finding_type'  => 'dep_on_hidden',
            'cmids'         => $cmids,
            'has_escape'    => true,
            'escape_type'   => 'unhide_cm',
            'cascade_cmids' => $cascade,
            'cascade_count' => count($cascade),
        ];
    }

    /**
     * Escape path for completion-tracking missing: enable completion on prereq.
     *
     * @param int[]            $cmids    [dependent_cmid, prereq_cmid].
     * @param cm_item[]        $cms
     * @param dependency_index $depindex
     * @return array
     */
    private function escape_no_tracking(
        array $cmids,
        array $cms,
        dependency_index $depindex
    ): array {
        $prereqcmid = $cmids[1] ?? null;
        $cascade = $prereqcmid !== null
            ? $this->collect_cascade_unblock([$prereqcmid], $depindex)
            : [];
        return [
            'finding_type'  => 'completion_no_tracking',
            'cmids'         => $cmids,
            'has_escape'    => true,
            'escape_type'   => 'enable_completion',
            'cascade_cmids' => $cascade,
            'cascade_count' => count($cascade),
        ];
    }

    /**
     * Collect all CMs that would be unblocked if the given cmids became completable.
     *
     * Uses BFS on the reverse dependency map (dependents of dependents…).
     *
     * @param int[]            $fixedcmids CMs that would become completable.
     * @param dependency_index $depindex
     * @return int[] Cmids that would become reachable.
     */
    private function collect_cascade_unblock(
        array $fixedcmids,
        dependency_index $depindex
    ): array {
        $unblocked = [];
        $queue = $fixedcmids;
        $seen = array_fill_keys($fixedcmids, true);

        while (!empty($queue)) {
            $cmid = array_shift($queue);
            foreach ($depindex->get_dependents($cmid) as $dependentid) {
                if (isset($seen[$dependentid])) {
                    continue;
                }
                $seen[$dependentid] = true;
                $unblocked[] = $dependentid;
                $queue[] = $dependentid;
            }
        }
        return $unblocked;
    }
}
