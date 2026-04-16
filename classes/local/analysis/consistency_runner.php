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
 * Consistency runner for the Course Control Hub.
 *
 * Orchestrates all light-check passes and merges their results into a single
 * per-CM issue map. Results are purely transient (not persisted).
 *
 * Each issue entry carries only domain-level data; the calling presentation
 * layer is responsible for converting issue types to localised warning strings
 * so this class stays free of Moodle i18n calls and remains easily testable.
 *
 * Supported issue types returned by get_warnings():
 *
 *   temporal_conflict  — Two date fields within the same CM have an inverted
 *                        ordering.
 *                        Extra keys: field_early, field_late, ts_early, ts_late.
 *
 *   dangling_dep       — An availability condition references a cmid that no
 *                        longer exists in the course inventory.
 *                        Extra keys: depcmid (int).
 *
 *   impossible_dep     — An availability condition requires completion of an
 *                        activity that has completion tracking disabled.
 *                        Extra keys: depcmid (int), depname (string).
 *
 *   dangling_group     — An availability condition references a group id that
 *                        does not exist in the course.
 *                        Extra keys: groupid (int).
 *
 *   dangling_grouping  — An availability condition references a grouping id
 *                        that does not exist in the course.
 *                        Extra keys: groupingid (int).
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;

/**
 * Orchestrates all transient consistency checks for a course.
 */
class consistency_runner {
    /** @var temporal_conflict_detector */
    private temporal_conflict_detector $conflictdetector;

    /** @var reachability_analyzer */
    private reachability_analyzer $reachabilityanalyzer;

    /**
     * Constructor.
     *
     * @param temporal_conflict_detector|null $conflictdetector   Optional override.
     * @param reachability_analyzer|null      $reachabilityanalyzer Optional override.
     */
    public function __construct(
        ?temporal_conflict_detector $conflictdetector = null,
        ?reachability_analyzer $reachabilityanalyzer = null
    ) {
        $this->conflictdetector = $conflictdetector ?? new temporal_conflict_detector();
        $this->reachabilityanalyzer = $reachabilityanalyzer ?? new reachability_analyzer();
    }

    /**
     * Run all checks and return a per-CM issue map.
     *
     * @param cm_item[]           $cms       Course modules keyed by cmid.
     * @param dependency_index    $depindex  Prebuilt dependency index.
     * @param array               $datesbycm Per-CM date entries from
     *                                        date_collector::collect_grouped_by_cm().
     * @param group_resolver|null $groups    Optional resolver for group existence checks.
     *                                        Pass null to skip group validation.
     * @return array<int, array[]> cmid → list of issue arrays.
     */
    public function get_warnings(
        array $cms,
        dependency_index $depindex,
        array $datesbycm,
        ?group_resolver $groups = null
    ): array {
        $warnings = [];

        foreach ($this->conflictdetector->detect($cms, $datesbycm) as $cmid => $conflicts) {
            foreach ($conflicts as $conflict) {
                $warnings[$cmid][] = [
                    'type' => 'temporal_conflict',
                    'field_early' => $conflict['field_early'],
                    'field_late' => $conflict['field_late'],
                    'ts_early' => $conflict['ts_early'],
                    'ts_late' => $conflict['ts_late'],
                ];
            }
        }

        foreach ($this->reachabilityanalyzer->analyze($cms, $depindex, $groups) as $cmid => $issues) {
            foreach ($issues as $issue) {
                $warnings[$cmid][] = array_merge(['type' => $issue['issuetype']], $issue);
            }
        }

        return $warnings;
    }
}
