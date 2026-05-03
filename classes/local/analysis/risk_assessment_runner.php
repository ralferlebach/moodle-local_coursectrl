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
 * Risk assessment runner for the Course Control Hub.
 *
 * Orchestrates the full risk pipeline:
 *   1. dead_end_detector   — find structural dead-ends
 *   2. escape_path_checker — determine fix availability and cascade impact
 *   3. risk_prioritizer    — score and sort findings
 *   4. Persist to local_coursectrl_risk (replaces prior results for course)
 *
 * The runner also merges findings from consistency_runner (temporal conflicts,
 * dangling deps) into the scored output so the risk page has a unified view.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;
use local_coursectrl\local\inventory\inventory_service;
use local_coursectrl\local\analysis\group_profile_extractor;
use local_coursectrl\local\analysis\cascade_analyzer;
use local_coursectrl\local\analysis\finding_deduplicator;
use local_coursectrl\local\analysis\completion_reachability_checker;

/**
 * Orchestrates the full risk assessment pipeline for a course.
 */
class risk_assessment_runner {
    /** @var dead_end_detector */
    private dead_end_detector $deadenddetector;

    /** @var escape_path_checker */
    private escape_path_checker $escapechecker;

    /** @var risk_prioritizer */
    private risk_prioritizer $prioritizer;

    /** @var consistency_runner */
    private consistency_runner $consistencyrunner;

    /** @var deep_journey_simulator */
    private deep_journey_simulator $journeysimulator;

    /** @var group_profile_extractor */
    private group_profile_extractor $profileextractor;

    /** @var cascade_analyzer */
    private cascade_analyzer $cascadeanalyzer;

    /** @var finding_deduplicator */
    private finding_deduplicator $deduplicator;

    /** @var completion_reachability_checker */
    private completion_reachability_checker $completionchecker;

    /**
     * Constructor.
     *
     * @param dead_end_detector|null      $deadenddetector   Optional override.
     * @param escape_path_checker|null    $escapechecker     Optional override.
     * @param risk_prioritizer|null       $prioritizer       Optional override.
     * @param consistency_runner|null     $consistencyrunner Optional override.
     * @param deep_journey_simulator|null $journeysimulator  Optional override.
     * @param group_profile_extractor|null  $profileextractor  Optional override.
     * @param cascade_analyzer|null         $cascadeanalyzer   Optional override.
     * @param finding_deduplicator|null          $deduplicator       Optional override.
     * @param completion_reachability_checker|null $completionchecker Optional override.
     */
    public function __construct(
        ?dead_end_detector $deadenddetector = null,
        ?escape_path_checker $escapechecker = null,
        ?risk_prioritizer $prioritizer = null,
        ?consistency_runner $consistencyrunner = null,
        ?deep_journey_simulator $journeysimulator = null,
        ?group_profile_extractor $profileextractor = null,
        ?cascade_analyzer $cascadeanalyzer = null,
        ?finding_deduplicator $deduplicator = null,
        ?completion_reachability_checker $completionchecker = null
    ) {
        $maxdepth = (int)(get_config('local_coursectrl', 'risk_maxdepth') ?: 10);
        $this->deadenddetector = $deadenddetector ?? new dead_end_detector($maxdepth);
        $this->escapechecker = $escapechecker ?? new escape_path_checker();
        $this->prioritizer = $prioritizer ?? new risk_prioritizer();
        $this->consistencyrunner = $consistencyrunner ?? new consistency_runner();
        $this->journeysimulator = $journeysimulator ?? new deep_journey_simulator();
        $this->profileextractor = $profileextractor ?? new group_profile_extractor();
        $this->cascadeanalyzer = $cascadeanalyzer ?? new cascade_analyzer();
        $this->deduplicator = $deduplicator ?? new finding_deduplicator();
        $this->completionchecker = $completionchecker ?? new completion_reachability_checker();
    }

    /**
     * Run the full risk assessment for a course and persist the results.
     *
     * @param cm_item[]        $cms      Course modules keyed by cmid.
     * @param dependency_index $depindex Pre-built dependency index.
     * @param array            $datesbycm Date entries from date_collector.
     * @param int              $courseid Course id (for persistence).
     * @param array            $sections Section items for section availability gating.
     * @return array[] Scored, sorted risk items.
     */
    public function run(
        array $cms,
        dependency_index $depindex,
        array $datesbycm,
        int $courseid,
        array $sections = []
    ): array {
        global $DB;

        // Structural dead-end detection.
        $findings = $this->deadenddetector->detect($cms, $depindex);

        // Dynamic journey simulation (reachability across group/grade combinations).
        // Simulate learner journeys across all group combinations and both grade
        // Scenarios (all-pass / all-fail) detect activities unreachable at runtime
        // ... that static analysis alone cannot find.
        $course = $DB->get_record('course', ['id' => $courseid]) ?: null;

        $gradeitemmap = [];
        $gradeinfobycmid = [];
        $gradequeryrows = $DB->get_records_sql(
            "SELECT gi.id, gi.gradepass, gi.grademax, cm.id AS cmid
               FROM {grade_items} gi
               JOIN {modules} m ON m.name = gi.itemmodule
               JOIN {course_modules} cm ON cm.module = m.id
                                       AND cm.instance = gi.iteminstance
                                       AND cm.course = gi.courseid
              WHERE gi.courseid = :courseid AND gi.itemtype = 'mod'",
            ['courseid' => $courseid]
        );
        foreach ($gradequeryrows as $row) {
            $gradeitemmap[(int) $row->id] = [
                'cmid'     => (int) $row->cmid,
                'grademax' => (float) ($row->grademax ?? 100.0),
            ];
            $gradeinfobycmid[(int) $row->cmid] = [
                'gradepass' => (float) ($row->gradepass ?? 0.0),
                'grademax'  => (float) ($row->grademax ?? 100.0),
            ];
        }

        // Derive structured learner group profiles from grouping dimensions.
        // This replaces the naive power-set approach with a semantically correct
        // Cartesian product of grouping-based choice dimensions.
        $groupprofiles = $this->profileextractor->extract($courseid, $cms);
        $critcmids = array_map(
            'intval',
            $DB->get_fieldset_select(
                'course_completion_criteria',
                'moduleinstance',
                'course = :course AND criteriatype = :type',
                ['course' => $courseid, 'type' => 4]
            )
        );

        // Query trial limits for activity types that support them (quiz, lesson).
        $maxattemptsbycmid = [];
        $trialrows = $DB->get_records_sql(
            "SELECT cm.id AS cmid, q.attempts AS maxattempts
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
               JOIN {quiz} q ON q.id = cm.instance
              WHERE cm.course = :courseid AND q.attempts > 0",
            ['courseid' => $courseid]
        );
        foreach ($trialrows as $row) {
            $maxattemptsbycmid[(int) $row->cmid] = (int) $row->maxattempts;
        }
        // Lesson: maxattempts 0 = unlimited, > 0 = limited.
        $lessonrows = $DB->get_records_sql(
            "SELECT cm.id AS cmid, l.maxattempts AS maxattempts
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'lesson'
               JOIN {lesson} l ON l.id = cm.instance
              WHERE cm.course = :courseid AND l.maxattempts > 0",
            ['courseid' => $courseid]
        );
        foreach ($lessonrows as $row) {
            $maxattemptsbycmid[(int) $row->cmid] = (int) $row->maxattempts;
        }

        $journeyfindings = $this->journeysimulator->simulate(
            $cms,
            $groupprofiles,
            $gradeinfobycmid,
            $gradeitemmap,
            $critcmids,
            0,
            $courseid,
            $maxattemptsbycmid,
            array_values($sections)
        );

        // Annotate findings with section cause when the parent section
        // Is blocked — this allows checks_page to show a targeted message.
        $journeyfindings = $this->annotate_section_cause(
            $journeyfindings,
            $cms,
            $sections,
            $courseid
        );

        // Score journey findings (not processed by risk_prioritizer).
        $scorebymode = ['pass' => 60, 'fail' => 25];
        foreach ($journeyfindings as &$jf) {
            $base = $scorebymode[$jf['grademode']] ?? 25;
            if ($jf['completion_block']) {
                $base += 30;
            }
            $jf['score'] = $base;
        }
        unset($jf);

        // Escape path analysis (static findings only).
        $escapepaths = $this->escapechecker->analyse($findings, $cms, $depindex);

        // Score and sort static findings by priority.
        // Prioritizer now receives grade maps so it can apply the remedial-pattern filter.
        $this->prioritizer = new \local_coursectrl\local\analysis\risk_prioritizer(
            $gradeitemmap,
            $gradeinfobycmid
        );
        $items = $this->prioritizer->score_and_sort($findings, $depindex);

        // Merge consistency-runner findings into the ranked list.
        $consistencywarnings = $this->consistencyrunner->get_warnings(
            $cms,
            $depindex,
            $datesbycm,
            null,
            $course
        );
        $items = array_merge($items, $this->convert_consistency_warnings($consistencywarnings, $cms));

        // Merge journey simulation findings.
        $items = array_merge($items, $journeyfindings);

        // Classify journey findings as PRIMARY or DERIVED; fold derived into primary.
        $items = $this->cascadeanalyzer->classify($items, $depindex);

        // Collapse per-scenario duplicates and aggregate structurally identical cards.
        $items = $this->deduplicator->deduplicate($items);

        // Check whether course completion criteria are reachable across all profiles.
        $completionfindings = $this->completionchecker->check(
            $courseid,
            $cms,
            $groupprofiles,
            $gradeitemmap,
            $gradeinfobycmid
        );
        $items = array_merge($items, $completionfindings);

        // Re-sort after merge.
        usort($items, fn ($a, $b) => ($b['score'] ?? 0) - ($a['score'] ?? 0));

        // Persist results, replacing any prior analysis for this course.
        $this->persist($courseid, $items);

        return $items;
    }

    /**
     * Load the most recent persisted risk assessment for a course.
     *
     * Returns an empty array if no assessment has been run yet.
     *
     * @param int $courseid
     * @return array[] Risk items as stored, or [].
     */
    public static function load_last(int $courseid): array {
        global $DB;
        $rows = $DB->get_records(
            'local_coursectrl_risk',
            ['courseid' => $courseid],
            'id ASC'
        );
        if (empty($rows)) {
            return [];
        }
        $items = [];
        foreach ($rows as $row) {
            $detail = json_decode($row->detailsjson, true);
            if (!is_array($detail)) {
                continue;
            }
            $items[] = $detail;
        }
        return $items;
    }

    /**
     * Return the timestamp of the last assessment for a course, or 0 if none.
     *
     * @param int $courseid
     * @return int Unix timestamp or 0.
     */
    public static function last_run_time(int $courseid): int {
        global $DB;
        $row = $DB->get_record_sql(
            'SELECT MAX(timecreated) AS ts FROM {local_coursectrl_risk} WHERE courseid = ?',
            [$courseid]
        );
        return $row ? (int)($row->ts ?? 0) : 0;
    }

    /**
     * Persist a set of scored risk items, replacing all prior rows for the course.
     *
     * Each item is stored as one row in local_coursectrl_risk. All prior rows
     * for this course are deleted first so the table always reflects the latest run.
     *
     * @param int     $courseid
     * @param array[] $items
     * @param array<int,array[]> $warnings Per-cmid issue arrays from consistency_runner.
     * @param cm_item[] $cms Course modules keyed by cmid.
     * @return void
     */
    private function persist(int $courseid, array $items): void {
        global $DB;
        $now = time();
        $DB->delete_records('local_coursectrl_risk', ['courseid' => $courseid]);
        foreach ($items as $item) {
            $severity = $item['severity'] ?? 'notice';
            // Map our severity to the DB column's legacy values.
            $dbseverity = match ($severity) {
                'error'   => 'critical',
                'warning' => 'warning',
                default   => 'info',
            };
            $entityid = (int)($item['cmids'][0] ?? 0);
            $DB->insert_record('local_coursectrl_risk', (object)[
                'courseid'    => $courseid,
                'risktype'    => $item['type'] ?? 'unknown',
                'severity'    => $dbseverity,
                'entitytype'  => 'cm',
                'entityid'    => $entityid,
                'detailsjson' => json_encode($item),
                'timecreated' => $now,
            ]);
        }
    }

    /**
     * Convert consistency_runner per-CM warnings into flat risk items.
     *
     * Assigns a probability of 1.0 and a score derived from severity.
     * Cascade analysis is not applied (consistency issues are atomic).
     *
     * @param array<int, array[]> $warnings Per-cmid issue arrays.
     * @param cm_item[]           $cms
     * @param array[] $findings Raw journey findings from deep_journey_simulator.
     * @param array $sections Section items keyed by section db id.
     * @param int $courseid Course id for context resolution.
     * @return array[]
     */
    private function convert_consistency_warnings(array $warnings, array $cms): array {
        $scorebase = ['error' => 70, 'warning' => 40, 'notice' => 15];
        $items = [];
        foreach ($warnings as $cmid => $issues) {
            foreach ($issues as $issue) {
                $severity = $issue['severity'] ?? 'warning';
                // Merge the full issue array first so type-specific extra fields
                // Field_early, field_late, ts_early, ts_late, field, ts_field …
                // ... are preserved for display in the risk tab UI.
                $items[] = array_merge($issue, [
                    'type'          => $issue['type'] ?? 'consistency',
                    'cmids'         => [$cmid],
                    'probability'   => 1.0,
                    'severity'      => $severity,
                    'has_escape'    => true,
                    'escape_type'   => 'fix_dates',
                    'cascade_cmids' => [],
                    'cascade_count' => 0,
                    'score'         => $scorebase[$severity] ?? 15,
                    'score_detail'  => [
                        'severity_base'      => $scorebase[$severity] ?? 15,
                        'probability_points' => 20,
                        'cascade_bonus'      => 0,
                        'overlap_penalty'    => 0,
                    ],
                    'message'       => $issue['message'] ?? '',
                ]);
            }
        }
        return $items;
    }

    /**
     * Annotate journey_unreachable findings with section-cause information.
     *
     * When a CM is unreachable and its parent section has availability conditions,
     * the finding is tagged with section_cause=true, section_id, and section_name
     * so the UI can render a targeted message and link to edit the section.
     *
     * @param array[]          $findings Raw journey findings.
     * @param cm_item[]        $cms      Course modules keyed by cmid.
     * @param array            $sections Section items keyed by section db id.
     * @return array[] Annotated findings.
     */
    private function annotate_section_cause(
        array $findings,
        array $cms,
        array $sections,
        int $courseid = 0
    ): array {
        if (empty($sections)) {
            return $findings;
        }
        // Build a lookup map keyed by section DB id regardless of how the
        // $sections array was indexed — array_values() may have stripped keys.
        $sectionmap = [];
        foreach ($sections as $sec) {
            $sectionmap[(int) $sec->id] = $sec;
        }
        // Pre-build locale-aware section names using get_section_name().
        // This returns 'Allgemeines'/'General' for section 0 and similar
        // Format-specific names instead of the raw empty name field.
        $sectionnamesbysid = [];
        if ($courseid > 0) {
            try {
                $modinfo = get_fast_modinfo($courseid);
                $course = get_course($courseid);
                foreach ($modinfo->get_section_info_all() as $sinfo) {
                    $sname = get_section_name($course, $sinfo);
                    $sectionnamesbysid[(int) $sinfo->id] = $sname;
                }
            } catch (\Throwable $e) {
                // Non-fatal: fall back to raw name or section number.
                $sectionnamesbysid = [];
            }
        }
        foreach ($findings as &$finding) {
            if (($finding['type'] ?? '') !== 'journey_unreachable') {
                continue;
            }
            $cmid = (int) (($finding['cmids'] ?? [])[0] ?? 0);
            $cm = $cms[$cmid] ?? null;
            if ($cm === null) {
                continue;
            }
            $sectionid = (int) ($cm->sectionid ?? 0);
            $section = $sectionmap[$sectionid] ?? null;
            if ($section === null || empty($section->availability)) {
                continue;
            }
            // Section has availability conditions — mark as section-caused.
            $finding['section_cause'] = true;
            $finding['section_id'] = $sectionid;
            // Use locale-aware name (e.g. 'Allgemeines' / 'General') when available.
            $finding['section_name'] = $sectionnamesbysid[$sectionid]
                ?? ($section->name ?? '');
            $finding['section_num'] = $section->sectionnum ?? 0;
            // Detect subsection CMs: find child section CMs blocked by this finding.
            if (($cm->modname ?? '') === 'subsection') {
                $finding['subsection_children'] = $this->find_subsection_children(
                    $cmid,
                    $cms,
                    $sectionmap
                );
            }
        }
        unset($finding);
        return $findings;
    }

    /**
     * Find CMs that are children of a subsection CM.
     *
     * In Moodle, a subsection CM (modname=subsection) corresponds to a
     * course_section whose component='mod_subsection' and itemid=cmid.
     * We identify the child section by matching section.itemid to the
     * subsection CM's instance (stored as section_item->itemid when set).
     * Fallback: find sections whose sequence contains only CMs not in
     * the parent section's direct members.
     *
     * @param int          $subsectioncmid The subsection CM id.
     * @param cm_item[]    $cms            All course modules keyed by cmid.
     * @param array        $sectionmap     DB-id-keyed section_item map.
     * @return int[] Child CM ids found inside the subsection.
     */
    private function find_subsection_children(
        int $subsectioncmid,
        array $cms,
        array $sectionmap
    ): array {
        // Find all section_items whose itemid matches this subsection cmid.
        // The itemid on a subsection section_item is the subsection CM's
        // module instance id — which equals the CM id for this modtype.
        $childsectionid = 0;
        foreach ($sectionmap as $secid => $sec) {
            if ((int) ($sec->itemid ?? 0) === $subsectioncmid) {
                $childsectionid = $secid;
                break;
            }
        }
        if ($childsectionid === 0) {
            return [];
        }
        // Return all CMs that belong to the child section.
        $children = [];
        foreach ($cms as $cmid => $cm) {
            if ((int) $cm->sectionid === $childsectionid) {
                $children[] = (int) $cmid;
            }
        }
        return $children;
    }
}
