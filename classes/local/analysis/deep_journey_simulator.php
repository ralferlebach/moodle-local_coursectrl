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
 * Deep journey simulator for the Course Control Hub.
 *
 * Performs dynamic reachability analysis by systematically simulating
 * possible learner journeys through a course. Unlike the static
 * dead_end_detector, this class exercises Moodle's availability condition
 * logic at each step so that group memberships, time restrictions,
 * completion states, and grade thresholds all affect reachability.
 *
 * Algorithm overview
 * ------------------
 *  For each group-combination scenario (up to max_group_combinations):
 *    For each grade scenario (all-pass / all-fail):
 *      BFS: start from all initially accessible activities.
 *      At each step: "complete" the front activity, advance simulated
 *      time by min_activity_minutes, re-evaluate all pending activities,
 *      enqueue newly accessible ones.
 *      Stop when queue is empty.
 *      Unreachable activities → produce findings with full journey log
 *      and a deep-link into the simulation tab.
 *
 * Settings
 * --------
 *  local_coursectrl/risk_min_activity_minutes  (default 30)
 *  local_coursectrl/risk_max_group_combinations (default 32)
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;
use local_coursectrl\local\entity\section_item;
use local_coursectrl\local\simulation\condition_evaluator;
use local_coursectrl\local\simulation\learner_state;

/**
 * Simulates all plausible learner journeys to detect unreachable activities.
 */
class deep_journey_simulator {
    /** @var int Minutes assumed per activity for time advancement. */
    private int $minactivityminutes;

    /** @var int Maximum number of group-combination scenarios to simulate. */
    private int $maxgroupcombinations;

    /**
     * Constructor.
     *
     * @param int|null $minactivityminutes   Override minutes per activity (null = read config).
     * @param int|null $maxgroupcombinations Override max group combos (null = read config).
     */
    public function __construct(
        ?int $minactivityminutes = null,
        ?int $maxgroupcombinations = null
    ) {
        $this->minactivityminutes = $minactivityminutes
            ?? max(1, (int)(get_config('local_coursectrl', 'risk_min_activity_minutes') ?: 30));
        $this->maxgroupcombinations = $maxgroupcombinations
            ?? max(1, (int)(get_config('local_coursectrl', 'risk_max_group_combinations') ?: 32));
    }

    /**
     * Run the deep journey simulation for a course.
     *
     * @param cm_item[]   $cms            Course modules keyed by cmid.
     * @param array       $groupprofiles  Profiles from group_profile_extractor::extract().
     *                                    Each profile: ['groupids'=>int[],
     *                                    'groupingids'=>int[], 'label'=>string].
     *                                    If empty the no-group profile is used.
     * @param array<int,array> $gradeinfobycmid cmid → {gradepass, grademax}.
     * @param array<int,int>   $gradeitemmap    grade_items.id → cmid.
     * @param int[]       $critcmids       Cmids required for course completion.
     * @param int         $startts         Simulation start timestamp (default: now).
     * @param int         $courseid        Course id (used in deep-link URLs).
     * @param array<int,int>   $maxattemptsbycmid  cmid → max attempts (0=unlimited).
     * @return array[] Findings: one entry per (scenario, unreachable-cmid) pair.
     */
    public function simulate(
        array $cms,
        array $groupprofiles,
        array $gradeinfobycmid = [],
        array $gradeitemmap = [],
        array $critcmids = [],
        int $startts = 0,
        int $courseid = 0,
        array $maxattemptsbycmid = [],
        array $sections = []
    ): array {
        if (empty($cms)) {
            return [];
        }

        $startts = $startts > 0 ? $startts : time();
        $critset = array_flip($critcmids);
        $evaluator = new condition_evaluator($gradeitemmap);

        // Use provided profiles; fall back to no-group profile when none supplied.
        if (empty($groupprofiles)) {
            $groupprofiles = [['groupids' => [], 'groupingids' => [], 'label' => '(no groups)']];
        }

        $allfindings = [];
        $seenkeys = []; // Deduplicate: same cmid + same scenario already reported.

        foreach ($groupprofiles as $profile) {
            $groupids = (array) ($profile['groupids'] ?? []);
            $groupingids = (array) ($profile['groupingids'] ?? []);
            // Simulate optimistic (all-pass) and pessimistic (all-fail) grade scenarios.
            foreach (['pass', 'fail'] as $grademode) {
                $result = $this->simulate_journey(
                    $cms,
                    $evaluator,
                    $gradeinfobycmid,
                    $groupids,
                    $groupingids,
                    $grademode,
                    $startts,
                    $maxattemptsbycmid,
                    $sections
                );

                foreach ($result['unreachable'] as $cmid) {
                    $key = $cmid . '|' . $grademode . '|' . implode(',', $groupids);
                    if (isset($seenkeys[$key])) {
                        continue;
                    }
                    $seenkeys[$key] = true;

                    $iscritmatch = isset($critset[$cmid]);
                    $severity = $grademode === 'fail' ? 'notice' : 'warning';
                    if ($iscritmatch) {
                        $severity = $grademode === 'fail' ? 'warning' : 'error';
                    }

                    $simlink = $this->build_sim_link(
                        $cms[$cmid]->get_component(),
                        $cms[$cmid]->id,
                        $groupids,
                        $grademode,
                        $result['steps'],
                        $startts,
                        $gradeinfobycmid,
                        $courseid
                    );

                    $allfindings[] = [
                        'type'             => 'journey_unreachable',
                        'severity'         => $severity,
                        'probability'      => 1.0,
                        'cmids'            => [$cmid],
                        'grademode'        => $grademode,
                        'groupids'         => $groupids,
                        'journey_steps'    => $result['steps'],
                        'has_steps'        => !empty($result['steps']),
                        'completion_block' => $iscritmatch,
                        'simlink'          => $simlink,
                        'has_escape'       => false,
                        'escape_type'      => 'none',
                        'cascade_cmids'    => [],
                        'cascade_count'    => 0,
                        'message_key'      => 'risk_journey_unreachable',
                        'message_params'   => ['grademode' => $grademode],
                        'affected_count'   => 1,
                    ];
                }
            }
        }

        return $allfindings;
    }

    /**
     * Simulate a single BFS learner journey.
     *
     * @param cm_item[]        $cms             Course modules keyed by cmid.
     * @param condition_evaluator $evaluator    Availability evaluator.
     * @param array            $gradeinfobycmid cmid → {gradepass, grademax}.
     * @param int[]            $groupids        Group ids the learner is in.
     * @param int[]            $groupingids     Grouping ids derived from the learner profile.
     * @param string           $grademode       'pass' or 'fail'.
     * @param int              $startts         Start timestamp.
     * @param array<int,int>   $maxattemptsbycmid cmid → max attempts (0=unlimited).
     * @return array{reachable: int[], unreachable: int[], steps: array[]}
     */
    public function simulate_journey(
        array $cms,
        condition_evaluator $evaluator,
        array $gradeinfobycmid,
        array $groupids,
        array $groupingids,
        string $grademode,
        int $startts,
        array $maxattemptsbycmid = [],
        array $sections = []
    ): array {
        $now = $startts;
        $completions = [];
        $grades = [];
        $visited = [];
        $steps = [];

        // BFS queue: cmids currently accessible and not yet visited.
        $queue = [];

        // Seed queue with initially accessible activities.
        // Visibility_simulator applies section gating before CM-level evaluation.
        $state = new learner_state($now, $completions, $groupids, $groupingids, $grades);
        $visiblesim = new \local_coursectrl\local\simulation\visibility_simulator(
            $evaluator,
            $sections
        );
        foreach ($cms as $cmid => $cm) {
            if (!$cm->visible) {
                continue;
            }
            $initresult = $visiblesim->simulate([$cmid => $cm], $state);
            if (!empty($initresult[$cmid]['accessible'])) {
                $queue[] = $cmid;
            }
        }

        $maxsteps = count($cms) + 1; // Guard against infinite loops.
        $iteration = 0;

        while (!empty($queue) && $iteration < $maxsteps) {
            $iteration++;
            $cmid = array_shift($queue);

            if (isset($visited[$cmid])) {
                continue;
            }
            $visited[$cmid] = true;

            // Determine completion outcome for this activity.
            $gradeinfo = $gradeinfobycmid[$cmid] ?? null;
            $gradepass = $gradeinfo ? (float)($gradeinfo['gradepass'] ?? 0.0) : 0.0;
            $haspassgrade = $gradepass > 0.0;
            // Any CM with a grade item (even gradepass=0) gets a simulated grade so that
            // Grade-based availability conditions on other CMs can be evaluated correctly.
            $hasgradeable = $gradeinfo !== null;
            $maxattempts = (int)($maxattemptsbycmid[$cmid] ?? 0);
            $attemptsexhausted = false;

            if ($haspassgrade) {
                if ($grademode === 'pass') {
                    $completionstate = 2; // COMPLETION_COMPLETE_PASS.
                    $grades[$cmid] = 100.0;
                } else {
                    $completionstate = 3; // COMPLETION_COMPLETE_FAIL.
                    $grades[$cmid] = 0.0;
                    $attemptsexhausted = $maxattempts > 0;
                }
            } else if ($hasgradeable) {
                // Grade item present but no pass threshold — simulate grade only.
                $completionstate = 1; // COMPLETION_COMPLETE.
                $grades[$cmid] = $grademode === 'pass' ? 100.0 : 0.0;
            } else {
                $completionstate = 1; // COMPLETION_COMPLETE.
            }

            // Only record completion state for CMs with completion tracking enabled.
            // CMs with completion=0 are visited but cannot fulfil completion-based
            // Prerequisites, and should not appear as 'completed' in the journey.
            $hascompletiontracking = ($cms[$cmid]->completion ?? 0) > 0;
            if ($hascompletiontracking) {
                $completions[$cmid] = $completionstate;
            }
            $now += $this->minactivityminutes * 60;

            $steps[] = [
                'cmid'                => $cmid,
                'cmname'              => $cms[$cmid]->name ?? '',
                'modname'             => $cms[$cmid]->modname ?? '',
                'outcome'             => $hascompletiontracking ? $completionstate : 0,
                'attempts_exhausted'  => $attemptsexhausted,
                'ts'                  => $now,
                'completion_tracking' => $hascompletiontracking ? 1 : 0,
            ];

            // Re-evaluate all unvisited activities with updated state.
            $state = new learner_state($now, $completions, $groupids, $groupingids, $grades);
            foreach ($cms as $candcmid => $candcm) {
                if (isset($visited[$candcmid]) || in_array($candcmid, $queue, true)) {
                    continue;
                }
                if (!$candcm->visible) {
                    continue;
                }
                // Rebuild visiblesim with updated state to re-evaluate sections.
                $visiblesim = new \local_coursectrl\local\simulation\visibility_simulator(
                    $evaluator,
                    $sections
                );
                $candresult = $visiblesim->simulate([$candcmid => $candcm], $state);
                if (!empty($candresult[$candcmid]['accessible'])) {
                    $queue[] = $candcmid;
                }
            }
        }

        $allcmids = array_keys($cms);
        $reachable = array_keys($visited);
        $unreachable = array_values(array_diff($allcmids, $reachable));

        // Filter unreachable: exclude permanently hidden activities (teacher-hidden).
        $unreachable = array_values(array_filter(
            $unreachable,
            fn ($cid) => !empty($cms[$cid]) && $cms[$cid]->visible
        ));

        return [
            'reachable'   => $reachable,
            'unreachable' => $unreachable,
            'steps'       => $steps,
        ];
    }

    /**
     * Generate group-combination scenarios up to max_group_combinations.
     *
     * Returns the empty-set scenario first (no group filter), then subsets
     * of increasing size until the limit is reached.
     *
     * @param array $coursegroups Group records from groups_get_all_groups().
     * @return int[][] List of group-id arrays (one per scenario).
     */
    public function build_group_combinations(array $coursegroups): array {
        $groupids = array_map(fn ($g) => (int)$g->id, array_values($coursegroups));
        $n = count($groupids);

        // Always include the no-group scenario.
        $combos = [[]];

        if ($n === 0) {
            return $combos;
        }

        // Generate power-set ordered by size, stop at limit.
        for ($size = 1; $size <= $n; $size++) {
            foreach ($this->combinations($groupids, $size) as $combo) {
                $combos[] = $combo;
                if (count($combos) >= $this->maxgroupcombinations) {
                    return $combos;
                }
            }
        }

        return $combos;
    }

    /**
     * Build a simulation deep-link URL for a given scenario.
     *
     * @param string  $component       Moodle component of the blocked activity.
     * @param int     $targetcmid      The blocked cmid.
     * @param int[]   $groupids        Group ids in this scenario.
     * @param string  $grademode       'pass' or 'fail'.
     * @param array[] $steps           Journey steps up to the blockade.
     * @param int     $startts         Start timestamp of the scenario.
     * @param array   $gradeinfobycmid Grade info per cmid.
     * @param int     $courseid        Course id for the URL.
     * @return string Absolute URL string with array params for group and grade state.
     */
    private function build_sim_link(
        string $component,
        int $targetcmid,
        array $groupids,
        string $grademode,
        array $steps,
        int $startts,
        array $gradeinfobycmid,
        int $courseid = 0
    ): string {
        // Reconstruct completion states and grades from the journey steps.
        $completeparams = [];
        $passedparams = [];
        $gradeparams = [];

        foreach ($steps as $step) {
            $cmid = (int)$step['cmid'];
            $outcome = (int)$step['outcome'];
            $hastrack = !empty($step['completion_tracking']);
            if ($hastrack && $outcome >= 1) {
                $completeparams[$cmid] = 1;
            }
            if ($hastrack && $outcome === 2) {
                $passedparams[$cmid] = 1;
            }
            $gradeinfo = $gradeinfobycmid[$cmid] ?? null;
            if ($gradeinfo) {
                $gradeparams[$cmid] = $grademode === 'pass' ? 100 : 0;
            }
        }

        // Use the last step timestamp as the simulation time.
        $simts = !empty($steps) ? (int)end($steps)['ts'] : $startts;
        $params = [
            'courseid' => $courseid,
            'tab'      => 'simulation',
            'run'      => 1,
            'simdate'  => date('Y-m-d', $simts),
            'simtime'  => date('H:i', $simts),
        ];

        // Build the base URL via moodle_url — this handles wwwroot, subdirectory
        // Installs, and config-driven URL rewriting automatically.
        // Moodle_url does not support PHP array-style parameters natively, so
        // The scalar params are encoded via moodle_url and the array params
        // (groupids[], sim_complete[N], etc.) are appended to the query string
        // Afterwards using the same percent-encoded bracket notation that
        // Moodle's own core uses for such parameters.
        $baseurl = new \moodle_url('/local/coursectrl/checks.php', $params);
        $base = $baseurl->out_omit_querystring();
        $scalarqs = substr($baseurl->out(false), strlen($base) + 1);
        $arrayqs = '';
        foreach ($groupids as $gid) {
            $arrayqs .= '&groupids%5B%5D=' . (int)$gid;
        }
        foreach ($completeparams as $cmid => $v) {
            $arrayqs .= '&sim_complete%5B' . $cmid . '%5D=1';
        }
        foreach ($passedparams as $cmid => $v) {
            $arrayqs .= '&sim_passed%5B' . $cmid . '%5D=1';
        }
        foreach ($gradeparams as $cmid => $pct) {
            $arrayqs .= '&sim_grade%5B' . $cmid . '%5D=' . $pct;
        }
        return $base . '?' . $scalarqs . $arrayqs;
    }

    /**
     * Generate all combinations of $size elements from $items.
     *
     * @param int[] $items Source array.
     * @param int   $size  Combination size.
     * @return int[][] List of combinations.
     */
    private function combinations(array $items, int $size): array {
        $n = count($items);
        if ($size > $n || $size <= 0) {
            return [];
        }
        if ($size === $n) {
            return [$items];
        }
        if ($size === 1) {
            return array_map(fn ($v) => [$v], $items);
        }
        $result = [];
        $items = array_values($items);
        for ($i = 0; $i <= $n - $size; $i++) {
            $head = [$items[$i]];
            $tail = array_slice($items, $i + 1);
            foreach ($this->combinations($tail, $size - 1) as $sub) {
                $result[] = array_merge($head, $sub);
            }
        }
        return $result;
    }
}
