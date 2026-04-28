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
use local_coursectrl\local\inventory\course_item;
use local_coursectrl\local\analysis\accessibility_checker;

/**
 * Orchestrates all transient consistency checks for a course.
 */
class consistency_runner {
    /** @var temporal_conflict_detector */
    private temporal_conflict_detector $conflictdetector;

    /** @var reachability_analyzer */
    private reachability_analyzer $reachabilityanalyzer;

    /** @var course_frame_checker */
    private course_frame_checker $framechecker;

    /** @var accessibility_checker */
    private accessibility_checker $accessibilitychecker;

    /**
     * Constructor.
     *
     * @param temporal_conflict_detector|null $conflictdetector   Optional override.
     * @param reachability_analyzer|null      $reachabilityanalyzer Optional override.
     */
    public function __construct(
        ?temporal_conflict_detector $conflictdetector = null,
        ?reachability_analyzer $reachabilityanalyzer = null,
        ?course_frame_checker $framechecker = null
    ) {
        $this->conflictdetector = $conflictdetector ?? new temporal_conflict_detector();
        $this->reachabilityanalyzer = $reachabilityanalyzer ?? new reachability_analyzer();
        $this->framechecker = $framechecker ?? new course_frame_checker();
        $this->accessibilitychecker = new accessibility_checker();
    }

    /**
     * Completion criteria type constant for activity-based criteria.
     * Matches core completion_criteria::COMPLETION_CRITERIA_TYPE_ACTIVITY.
     *
     * @var int
     */
    private const CRITERIA_TYPE_ACTIVITY = 4;

    /**
     * Run all checks and return a per-CM issue map.
     *
     * When $course is provided, this method also:
     *   - loads activity-based course completion criteria from the DB, and
     *   - escalates the severity of any issue affecting a completion-critical
     *     activity by one level (notice → warning, warning → error).
     *
     * @param cm_item[]           $cms       Course modules keyed by cmid.
     * @param dependency_index    $depindex  Prebuilt dependency index.
     * @param array               $datesbycm Per-CM date entries from
     *                                        date_collector::collect_grouped_by_cm().
     * @param group_resolver|null $groups    Optional resolver for group existence checks.
     *                                        Pass null to skip group validation.
     * @param object|null         $course    Course record; used for R0 checks and
     *                                        completion-criteria severity escalation.
     * @return array<int, array[]> cmid → list of issue arrays.
     */
    public function get_warnings(
        array $cms,
        dependency_index $depindex,
        array $datesbycm,
        ?group_resolver $groups = null,
        ?object $course = null
    ): array {
        global $DB;
        $warnings = [];

        // Load activity-based course completion criteria when a course is provided.
        $critcmids = [];
        if ($course !== null) {
            $rows = $DB->get_fieldset_select(
                'course_completion_criteria',
                'moduleinstance',
                'course = :course AND criteriatype = :type',
                ['course' => (int) $course->id, 'type' => self::CRITERIA_TYPE_ACTIVITY]
            );
            $critcmids = array_map('intval', $rows);
        }

        // R0: course-frame plausibility (before any other checks).
        if ($course !== null) {
            foreach ($this->framechecker->check($cms, $datesbycm, $course, $critcmids) as $cmid => $r0issues) {
                foreach ($r0issues as $issue) {
                    $warnings[$cmid][] = $issue;
                }
            }
        }

        // Reachability (structural dependency checks) must run before R1 so that
        // Dangling_dep / impossible_dep issues appear first. R1 is suppressed for
        // CMs that already carry a structural dependency issue, since those make
        // ... the R1 result misleading (the CM may be inaccessible solely because
        // ... the prerequisite no longer exists, not because of a policy decision).
        $structuralcmids = [];
        foreach ($this->reachabilityanalyzer->analyze($cms, $depindex, $groups) as $cmid => $issues) {
            foreach ($issues as $issue) {
                $warnings[$cmid][] = array_merge(['type' => $issue['issuetype']], $issue);
            }
            $structuralcmids[$cmid] = true;
        }

        // R1: accessibility check (mode-dependent, see r1_mode admin setting).
        // Skip CMs already flagged with a structural dependency issue.
        foreach ($this->accessibilitychecker->check($cms) as $cmid => $r1issues) {
            if (isset($structuralcmids[$cmid])) {
                continue;
            }
            foreach ($r1issues as $issue) {
                $warnings[$cmid][] = $issue;
            }
        }

        foreach ($this->conflictdetector->detect($cms, $datesbycm) as $cmid => $conflicts) {
            foreach ($conflicts as $conflict) {
                $issueclass = $conflict['issue_class'] ?? 'temporal_conflict';
                $warnings[$cmid][] = [
                    'type'        => $issueclass,
                    'severity'    => $conflict['severity'] ?? 'error',
                    'field_early' => $conflict['field_early'] ?? '',
                    'field_late'  => $conflict['field_late'] ?? '',
                    'ts_early'    => $conflict['ts_early'] ?? 0,
                    'ts_late'     => $conflict['ts_late'] ?? 0,
                    'min_gap_days' => $conflict['min_gap_days'] ?? 0,
                ];
            }
        }

        // Escalate severity for issues affecting completion-critical activities
        // R0 already escalated via framechecker — this covers R1, R2, R3, R4, R7.
        if (!empty($critcmids)) {
            $critset = array_flip($critcmids);
            foreach ($warnings as $cmid => &$cmwarnings) {
                if (!isset($critset[$cmid])) {
                    continue;
                }
                foreach ($cmwarnings as &$warning) {
                    if (isset($warning['completion_escalated'])) {
                        continue; // Already escalated by frame_checker.
                    }
                    if (($warning['severity'] ?? '') === 'warning') {
                        $warning['severity'] = 'error';
                        $warning['completion_escalated'] = true;
                    } else if (($warning['severity'] ?? '') === 'notice') {
                        $warning['severity'] = 'warning';
                        $warning['completion_escalated'] = true;
                    }
                }
                unset($warning);
            }
            unset($cmwarnings);
        }

        return $warnings;
    }
}
