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
 * Reachability analyzer for the Course Control Hub.
 *
 * Checks completion dependencies and group/grouping conditions in
 * availability JSON against the actual course inventory and group setup.
 *
 * Issue types returned by analyze():
 *
 *   dangling_dep      — prerequisite cmid not in course inventory.
 *   impossible_dep    — prerequisite CM has completion tracking disabled.
 *   dangling_group    — availability requires a group that does not exist
 *                       in the course.
 *   dangling_grouping — availability requires a grouping that does not exist
 *                       in the course.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;

/**
 * Checks completion dependencies and group conditions for reachability issues.
 */
class reachability_analyzer {
    /**
     * Analyse all CMs for reachability issues.
     *
     * @param cm_item[]         $cms      Course modules keyed by cmid.
     * @param dependency_index  $depindex Prebuilt dependency index.
     * @param group_resolver|null $groups Optional resolver for group existence checks.
     * @return array<int, array[]> cmid → list of issue arrays.
     */
    public function analyze(
        array $cms,
        dependency_index $depindex,
        ?group_resolver $groups = null
    ): array {
        $result = [];
        foreach ($cms as $cm) {
            $issues = $this->check_cm($cm, $cms, $depindex, $groups);
            if (!empty($issues)) {
                $result[$cm->id] = $issues;
            }
        }
        return $result;
    }

    /**
     * Check a single CM for reachability issues.
     *
     * @param cm_item             $cm       The CM being checked.
     * @param cm_item[]           $cms      All CMs in the course, keyed by cmid.
     * @param dependency_index    $depindex Prebuilt dependency index.
     * @param group_resolver|null $groups   Optional group resolver.
     * @return array[] List of issue arrays (may be empty).
     */
    private function check_cm(
        cm_item $cm,
        array $cms,
        dependency_index $depindex,
        ?group_resolver $groups
    ): array {
        $issues = [];

        // Completion dependency checks.
        foreach ($depindex->get_prerequisites($cm->id) as $depcmid) {
            if (!array_key_exists($depcmid, $cms)) {
                $issues[] = [
                    'issuetype' => 'dangling_dep',
                    'depcmid' => $depcmid,
                    'depname' => null,
                ];
            } else if ($cms[$depcmid]->completion === 0) {
                $issues[] = [
                    'issuetype' => 'impossible_dep',
                    'depcmid' => $depcmid,
                    'depname' => $cms[$depcmid]->name,
                ];
            }
        }

        // Group / grouping condition checks (only when a resolver is provided).
        if ($groups !== null && $cm->availability !== null && $cm->availability !== '') {
            $parsed = $depindex->get_parsed_availability($cm->id);

            foreach ($parsed['groupconditions'] ?? [] as $cond) {
                $id = (int) ($cond['id'] ?? 0);
                if ($id === 0) {
                    continue;
                }
                if ($cond['type'] === 'group' && !$groups->group_exists($id)) {
                    $issues[] = [
                        'issuetype' => 'dangling_group',
                        'groupid' => $id,
                        'groupname' => null,
                    ];
                } else if ($cond['type'] === 'grouping' && !$groups->grouping_exists($id)) {
                    $issues[] = [
                        'issuetype' => 'dangling_grouping',
                        'groupingid' => $id,
                        'groupingname' => null,
                    ];
                }
            }
        }

        return $issues;
    }
}
