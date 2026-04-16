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
 * Validates completion-based availability dependencies against the actual
 * inventory snapshot. Two classes of problems are detected:
 *
 *   dangling_dep   — The availability JSON of a CM references a cmid that does
 *                    not exist in the current course inventory. This happens
 *                    when an activity has been deleted while an availability
 *                    condition referencing it was left in place.
 *
 *   impossible_dep — The availability JSON references a CM that exists but has
 *                    completion tracking disabled (completion === 0). Because
 *                    Moodle will never record a completion event for that CM,
 *                    the condition can never be met and the depending activity
 *                    is permanently inaccessible.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;

/**
 * Checks completion dependencies for dangling references and impossible conditions.
 */
class reachability_analyzer {
    /**
     * Analyse all CMs for reachability issues.
     *
     * @param cm_item[]        $cms      Course modules keyed by cmid.
     * @param dependency_index $depindex Prebuilt dependency index for the course.
     * @return array<int, array[]> cmid → list of issue arrays. Each issue:
     *                             ['issuetype' => 'dangling_dep'|'impossible_dep',
     *                              'depcmid' => int, 'depname' => string|null]
     */
    public function analyze(array $cms, dependency_index $depindex): array {
        $result = [];
        foreach ($cms as $cm) {
            $issues = $this->check_cm($cm, $cms, $depindex);
            if (!empty($issues)) {
                $result[$cm->id] = $issues;
            }
        }
        return $result;
    }

    /**
     * Check a single CM for reachability issues.
     *
     * @param cm_item          $cm       The CM being checked.
     * @param cm_item[]        $cms      All CMs in the course, keyed by cmid.
     * @param dependency_index $depindex Prebuilt dependency index.
     * @return array[] List of issue arrays (may be empty).
     */
    private function check_cm(
        cm_item $cm,
        array $cms,
        dependency_index $depindex
    ): array {
        $issues = [];
        foreach ($depindex->get_prerequisites($cm->id) as $depcmid) {
            if (!array_key_exists($depcmid, $cms)) {
                // Referenced cmid is not in the course inventory.
                $issues[] = [
                    'issuetype' => 'dangling_dep',
                    'depcmid' => $depcmid,
                    'depname' => null,
                ];
            } else if ($cms[$depcmid]->completion === 0) {
                // The prerequisite CM has completion tracking disabled.
                $issues[] = [
                    'issuetype' => 'impossible_dep',
                    'depcmid' => $depcmid,
                    'depname' => $cms[$depcmid]->name,
                ];
            }
        }
        return $issues;
    }
}
