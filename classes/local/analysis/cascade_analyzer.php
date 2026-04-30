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
 * Cascade analyzer for the Course Control Hub risk pipeline.
 *
 * Problem solved
 * --------------
 * When a primary blockage (e.g. a group condition prevents access to an
 * activity) exists, all downstream activities that require the blocked CM's
 * completion will also appear as unreachable — as independent errors. This
 * creates noise: the same root cause produces dozens of separate findings.
 *
 * This analyzer post-processes the flat finding list produced by
 * deep_journey_simulator and classifies each journey_unreachable finding as
 * either PRIMARY (has its own root cause) or DERIVED (is only unreachable
 * because a prerequisite is itself unreachable).
 *
 * DERIVED findings are removed from the top-level list and attached as
 * 'cascade_cmids' on the PRIMARY finding that caused them. This reduces the
 * visible card count from O(blocked CMs) to O(root causes).
 *
 * Algorithm
 * ---------
 *  1. Build a set of all cmids that are unreachable in ANY scenario
 *     (across both grade modes and all group profiles).
 *  2. For each finding, inspect its e=1 unlock-prerequisites via the
 *     dependency_index. If any prerequisite is itself in the unreachable
 *     set, the finding is DERIVED.
 *  3. Walk the derivation graph recursively to reach the true PRIMARY root.
 *  4. Attach DERIVED cmids to the PRIMARY finding; drop DERIVED findings
 *     from the top-level list.
 *
 * Only journey_unreachable findings are processed. All other finding types
 * (temporal conflicts, dead-ends, etc.) pass through unchanged.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

/**
 * Classifies journey_unreachable findings as PRIMARY or DERIVED.
 */
class cascade_analyzer {
    /**
     * Classify findings and attach derived ones to their root primary.
     *
     * @param array[]          $findings All risk findings from the pipeline.
     * @param dependency_index $depindex Pre-built dependency index for the course.
     * @return array[] Findings with DERIVED journey items folded into PRIMARY cascade lists.
     */
    public function classify(array $findings, dependency_index $depindex): array {
        // Separate journey findings from other findings (pass-through).
        $journeyfindings = [];
        $otherfindings = [];

        foreach ($findings as $item) {
            if (($item['type'] ?? '') === 'journey_unreachable') {
                $journeyfindings[] = $item;
            } else {
                $otherfindings[] = $item;
            }
        }

        if (empty($journeyfindings)) {
            return $findings;
        }

        // Step 1: build set of all cmids that are unreachable in any scenario.
        $unreachablecmids = [];
        foreach ($journeyfindings as $item) {
            foreach ($item['cmids'] ?? [] as $cmid) {
                $unreachablecmids[(int) $cmid] = true;
            }
        }

        // Step 2: for each finding, determine whether it is DERIVED.
        // A finding is DERIVED when at least one of its e=1 unlock-prerequisites
        // Is itself in the unreachable set.
        $unlockforward = $depindex->get_unlock_forward();

        // Reverse-map: cmid → all cmids whose e=1 prereqs include this cmid.
        // Used to find which primarys caused a derived.
        $reversedeps = [];
        foreach ($unlockforward as $cmid => $prereqs) {
            foreach ($prereqs as $preqcmid) {
                $reversedeps[$preqcmid][] = (int) $cmid;
            }
        }

        // Classify each journey finding.
        $derivedcmids = []; // Cmid → root primary cmid.

        foreach (array_keys($unreachablecmids) as $cmid) {
            $prereqs = $unlockforward[$cmid] ?? [];
            foreach ($prereqs as $preqcmid) {
                if (isset($unreachablecmids[$preqcmid])) {
                    // This CM is blocked because a prerequisite is blocked.
                    $derivedcmids[(int) $cmid] = true;
                    break;
                }
            }
        }

        // Step 3: walk derivation graph to find true root (handle chains).
        // Primary: unreachable but NOT derived.
        // For chains (DERIVED² etc.), bubble up to the topmost PRIMARY.
        $rootmap = []; // Cmid → root primary cmid.
        foreach (array_keys($derivedcmids) as $cmid) {
            $rootmap[$cmid] = $this->find_root($cmid, $unlockforward, $derivedcmids, []);
        }

        // Step 4: for each PRIMARY finding, collect cascade cmids from all
        // findings that trace back to it.
        // Use the first scenario's finding as the canonical PRIMARY card —
        // de-duplication across scenarios is handled by finding_deduplicator.
        $cascadebyprimary = []; // Primary cmid → [derived cmid, ...].
        foreach ($rootmap as $derivedcmid => $primarycmid) {
            $cascadebyprimary[$primarycmid][] = $derivedcmid;
        }

        // Step 5: rebuild finding list.
        // Keep only PRIMARY journey findings; attach cascade; remove DERIVED.
        $result = [];
        $seenprimary = [];

        foreach ($journeyfindings as $item) {
            $cmid = (int) (($item['cmids'] ?? [])[0] ?? 0);
            if (isset($derivedcmids[$cmid])) {
                // Derived — skip; its information is on the primary card.
                continue;
            }
            // This is a PRIMARY finding; attach cascade cmids.
            $existing = $cascadebyprimary[$cmid] ?? [];
            $item['cascade_cmids'] = array_values(array_unique(
                array_merge($item['cascade_cmids'] ?? [], $existing)
            ));
            $item['cascade_count'] = count($item['cascade_cmids']);
            $result[] = $item;
        }

        return array_merge($result, $otherfindings);
    }

    /**
     * Walk the dependency graph upward to find the PRIMARY root for a derived cmid.
     *
     * @param int   $cmid         Starting cmid.
     * @param array $unlockfwd    Unlock-forward map cmid → prereq cmids.
     * @param array $derivedcmids Set of all derived cmids.
     * @param array $visited      Visited set for cycle guard.
     * @return int Root primary cmid.
     */
    private function find_root(
        int $cmid,
        array $unlockfwd,
        array $derivedcmids,
        array $visited
    ): int {
        if (isset($visited[$cmid])) {
            return $cmid;
        }
        $visited[$cmid] = true;

        $prereqs = $unlockfwd[$cmid] ?? [];
        foreach ($prereqs as $preqcmid) {
            if (!isset($derivedcmids[$preqcmid])) {
                // Found a prerequisite that is PRIMARY (unreachable but not derived).
                return (int) $preqcmid;
            }
            // Prereq is also derived — recurse upward.
            return $this->find_root((int) $preqcmid, $unlockfwd, $derivedcmids, $visited);
        }
        // No blocking prereq found in the index — treat self as primary.
        return $cmid;
    }
}
