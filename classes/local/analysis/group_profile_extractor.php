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
 * Group profile extractor for the Course Control Hub simulation engine.
 *
 * Analyses the availability conditions of all course modules and the
 * grouping structure of the course to derive the set of meaningful learner
 * group profiles that should be simulated.
 *
 * Problem solved
 * --------------
 * The naive approach (power-set of all course groups) produces O(2^n)
 * scenarios, most of which are unrealistic:
 *  - Two groups from the same "choice dimension" (e.g. two Lehrveranstaltung
 *    groups) are never both assigned to the same learner.
 *  - Groups not referenced in any availability condition are irrelevant.
 *
 * This extractor identifies grouping dimensions — sets of groups within one
 * Moodle grouping — and computes the Cartesian product: exactly one group per
 * dimension. The result is the smallest set of scenarios that covers all
 * realistic learner profiles while generating no redundant simulations.
 *
 * Algorithm
 * ---------
 *  1. Load all groups referenced in any CM availability tree.
 *  2. Load grouping membership for those groups from mdl_groupings_groups.
 *  3. Classify referenced groups into dimensions:
 *       - Groups sharing a grouping → one dimension (learner picks one).
 *       - Groups belonging to no grouping → independent dimension (one group).
 *  4. Compute Cartesian product of dimensions → valid profiles.
 *  5. Always include the no-group profile (for ungrouped learners).
 *  6. For each profile, derive groupingids from groupids.
 *  7. Fall back to power-set (capped at max_profiles) when no grouping
 *     structure is detected.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;

/**
 * Derives the set of meaningful learner group profiles for a course.
 */
class group_profile_extractor {
    /** @var int Maximum number of profiles to return as a safety cap. */
    private int $maxprofiles;

    /**
     * Constructor.
     *
     * @param int $maxprofiles Maximum profiles to return (default 64).
     */
    public function __construct(int $maxprofiles = 64) {
        $this->maxprofiles = max(1, $maxprofiles);
    }

    /**
     * Extract valid learner group profiles for a course.
     *
     * Each profile is an array with keys:
     *   groupids    — int[] group ids the learner belongs to.
     *   groupingids — int[] grouping ids derived from those groups.
     *   label       — string human-readable label for the profile.
     *
     * The first profile is always the no-group profile (groupids=[]).
     *
     * @param int       $courseid    Course id.
     * @param cm_item[] $cms         Course modules keyed by cmid.
     * @return array[] List of profile arrays.
     */
    public function extract(int $courseid, array $cms): array {
        global $DB;

        // Step 1: collect all group ids referenced in any availability tree.
        $referencedgroups = $this->collect_referenced_groups($cms);

        if (empty($referencedgroups)) {
            // No group conditions in this course — only the no-group profile.
            return [['groupids' => [], 'groupingids' => [], 'label' => '(no groups)']];
        }

        // Step 2: load grouping membership for referenced groups.
        [$insql, $inparams] = $DB->get_in_or_equal(array_values($referencedgroups), SQL_PARAMS_NAMED);
        $inparams['courseid'] = $courseid;

        $rows = $DB->get_records_sql(
            "SELECT gg.groupid, gg.groupingid, g.name AS groupname, gi.name AS groupingname
               FROM {groupings_groups} gg
               JOIN {groups} g ON g.id = gg.groupid
               JOIN {groupings} gi ON gi.id = gg.groupingid
              WHERE g.courseid = :courseid
                AND gg.groupid {$insql}",
            $inparams
        );

        // Step 3: build dimension map — groupingid → [groupid, ...].
        $dimensionmap = [];
        $groupsinagrouping = [];

        foreach ($rows as $row) {
            $gid = (int) $row->groupid;
            $giid = (int) $row->groupingid;
            $dimensionmap[$giid][] = $gid;
            $groupsinagrouping[$gid] = true;
        }

        // Groups that are referenced but belong to no grouping form individual
        // Singleton dimensions (present / absent choice).
        $ungrouped = [];
        foreach ($referencedgroups as $gid) {
            if (!isset($groupsinagrouping[$gid])) {
                $ungrouped[] = $gid;
            }
        }

        // Build dimension list: each element is an array of candidate group ids.
        // A learner picks exactly one from each grouping-dimension, or 0/1 for
        // Ungrouped singletons.
        $dimensions = array_values($dimensionmap);
        foreach ($ungrouped as $gid) {
            $dimensions[] = [$gid];
        }

        if (empty($dimensions)) {
            return [['groupids' => [], 'groupingids' => [], 'label' => '(no groups)']];
        }

        // Step 4: Cartesian product of dimensions → valid profiles.
        $rawprofiles = $this->cartesian_product($dimensions);

        // Step 5: prepend the no-group profile.
        $profiles = [['groupids' => [], 'groupingids' => [], 'label' => '(no groups)']];

        // Build reverse lookup: groupid → [groupingid, ...].
        $groupinglookup = [];
        foreach ($rows as $row) {
            $groupinglookup[(int) $row->groupid][] = (int) $row->groupingid;
        }

        // Step 6: for each raw profile, derive groupingids and build label.
        foreach ($rawprofiles as $groupids) {
            if (count($profiles) >= $this->maxprofiles) {
                break;
            }
            $groupingids = [];
            foreach ($groupids as $gid) {
                foreach ($groupinglookup[$gid] ?? [] as $giid) {
                    $groupingids[] = $giid;
                }
            }
            $groupingids = array_values(array_unique($groupingids));
            $profiles[] = [
                'groupids'   => $groupids,
                'groupingids' => $groupingids,
                'label'      => implode('+', $groupids),
            ];
        }

        return $profiles;
    }

    /**
     * Collect all group ids referenced in any CM availability tree.
     *
     * @param cm_item[] $cms Course modules keyed by cmid.
     * @return int[] Unique group ids found in availability conditions.
     */
    private function collect_referenced_groups(array $cms): array {
        $groupids = [];
        foreach ($cms as $cm) {
            if (empty($cm->availability)) {
                continue;
            }
            $tree = json_decode($cm->availability, true);
            if (!is_array($tree)) {
                continue;
            }
            $this->collect_groups_from_node($tree, $groupids);
        }
        return array_values(array_unique($groupids));
    }

    /**
     * Recursively extract group ids from an availability condition node.
     *
     * @param array $node     Decoded availability node.
     * @param int[] $groupids Accumulator (modified in place).
     * @return void
     */
    private function collect_groups_from_node(array $node, array &$groupids): void {
        if (isset($node['type']) && $node['type'] === 'group') {
            $gid = (int) ($node['id'] ?? 0);
            if ($gid > 0) {
                $groupids[] = $gid;
            }
            return;
        }
        foreach ($node['c'] ?? [] as $child) {
            $this->collect_groups_from_node($child, $groupids);
        }
    }

    /**
     * Compute the Cartesian product of a list of dimensions.
     *
     * Each dimension is an array of group ids; the product contains one
     * combination per choice of exactly one element from each dimension.
     *
     * @param int[][] $dimensions List of dimensions, each a list of group ids.
     * @return int[][] List of group-id combinations.
     */
    private function cartesian_product(array $dimensions): array {
        $result = [[]];
        foreach ($dimensions as $dimension) {
            $newresult = [];
            foreach ($result as $existing) {
                foreach ($dimension as $gid) {
                    $newresult[] = array_merge($existing, [$gid]);
                }
            }
            $result = $newresult;
            if (count($result) >= $this->maxprofiles) {
                return array_slice($result, 0, $this->maxprofiles);
            }
        }
        return $result;
    }
}
