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

    /**
     * Constructor.
     *
     * @param dead_end_detector|null      $deadenddetector   Optional override.
     * @param escape_path_checker|null    $escapechecker     Optional override.
     * @param risk_prioritizer|null       $prioritizer       Optional override.
     * @param consistency_runner|null     $consistencyrunner Optional override.
     * @param deep_journey_simulator|null $journeysimulator  Optional override.
     */
    public function __construct(
        ?dead_end_detector $deadenddetector = null,
        ?escape_path_checker $escapechecker = null,
        ?risk_prioritizer $prioritizer = null,
        ?consistency_runner $consistencyrunner = null,
        ?deep_journey_simulator $journeysimulator = null
    ) {
        $maxdepth = (int)(get_config('local_coursectrl', 'risk_maxdepth') ?: 10);
        $this->deadenddetector = $deadenddetector ?? new dead_end_detector($maxdepth);
        $this->escapechecker = $escapechecker ?? new escape_path_checker();
        $this->prioritizer = $prioritizer ?? new risk_prioritizer();
        $this->consistencyrunner = $consistencyrunner ?? new consistency_runner();
        $this->journeysimulator = $journeysimulator ?? new deep_journey_simulator();
    }

    /**
     * Run the full risk assessment for a course and persist the results.
     *
     * @param cm_item[]        $cms      Course modules keyed by cmid.
     * @param dependency_index $depindex Pre-built dependency index.
     * @param array            $datesbycm Date entries from date_collector.
     * @param int              $courseid Course id (for persistence).
     * @return array[] Scored, sorted risk items.
     */
    public function run(
        array $cms,
        dependency_index $depindex,
        array $datesbycm,
        int $courseid
    ): array {
        global $DB;

        // Phase 1: structural dead-ends.
        $findings = $this->deadenddetector->detect($cms, $depindex);

        // Phase 1.5: dynamic deep journey simulation.
        // Simulate learner journeys across all group combinations and both grade
        // Scenarios (all-pass / all-fail) detect activities unreachable at runtime
        // that static analysis alone cannot find.
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
            $gradeitemmap[(int) $row->id] = (int) $row->cmid;
            $gradeinfobycmid[(int) $row->cmid] = [
                'gradepass' => (float) ($row->gradepass ?? 0.0),
                'grademax'  => (float) ($row->grademax ?? 100.0),
            ];
        }

        $coursegroups = groups_get_all_groups($courseid);
        $critcmids = array_map(
            'intval',
            $DB->get_fieldset_select(
                'course_completion_criteria',
                'moduleinstance',
                'course = :course AND criteriatype = :type',
                ['course' => $courseid, 'type' => 4]
            )
        );

        $journeyfindings = $this->journeysimulator->simulate(
            $cms,
            array_values($coursegroups),
            $gradeinfobycmid,
            $gradeitemmap,
            $critcmids
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

        // Phase 2: escape paths (static findings only).
        $escapepaths = $this->escapechecker->analyse($findings, $cms, $depindex);

        // Phase 3: score and sort static findings.
        $items = $this->prioritizer->score_and_sort($findings, $depindex);

        // Phase 4: merge consistency_runner findings.
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

        // Re-sort after merge.
        usort($items, fn ($a, $b) => ($b['score'] ?? 0) - ($a['score'] ?? 0));

        // Phase 5: persist (replace prior results for this course).
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
}
