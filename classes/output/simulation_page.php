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
 * Renderable for the Course Control Hub learner simulation page.
 *
 * Builds the complete template context for simulation.mustache, including
 * the form data needed to specify the scenario and, when a state has been
 * submitted, the per-CM simulation results and the next-step list.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

use local_coursectrl\local\analysis\group_resolver;
use local_coursectrl\local\inventory\inventory_snapshot;
use local_coursectrl\local\simulation\condition_evaluator;
use local_coursectrl\local\simulation\learner_state;
use local_coursectrl\local\simulation\next_step_engine;
use local_coursectrl\local\simulation\visibility_simulator;
use renderable;
use renderer_base;
use templatable;

/**
 * Simulation page renderable: scenario form + optional results.
 */
class simulation_page implements renderable, templatable {
    /** @var inventory_snapshot */
    protected inventory_snapshot $snapshot;

    /** @var learner_state|null Submitted state, or null when form not yet submitted. */
    protected ?learner_state $state;

    /**
     * Constructor.
     *
     * @param inventory_snapshot $snapshot Course inventory snapshot.
     * @param learner_state|null $state    Learner state from submitted form, or null.
     */
    public function __construct(inventory_snapshot $snapshot, ?learner_state $state = null) {
        $this->snapshot = $snapshot;
        $this->state = $state;
    }

    /**
     * Build template context for templates/simulation.mustache.
     *
     * @param renderer_base $output Renderer (unused, required by interface).
     * @return array<string,mixed>
     */
    public function export_for_template(renderer_base $output): array {
        $course = $this->snapshot->course;
        $cms = $this->snapshot->cms;
        $courseid = $course->id;
        $dateformat = get_string('strftimedaydatetime', 'core_langconfig');
        $now = time();

        // Form defaults: list all CMs with checkbox + completion selector.
        $cmformrows = [];
        foreach ($cms as $cm) {
            $completionlabel = '';
            if ($cm->completion === 1) {
                $completionlabel = get_string('dashboard_completion_manual', 'local_coursectrl');
            } else if ($cm->completion === 2) {
                $completionlabel = get_string('dashboard_completion_auto', 'local_coursectrl');
            }
            $assumed = $this->state ? $this->state->get_completion($cm->id) : 0;
            $cmformrows[] = [
                'cmid' => $cm->id,
                'name' => $cm->name,
                'cmname' => $cm->name,
                'modname' => $cm->modname,
                'modicon' => $cm->modname,
                'hascompletion' => $cm->completion > 0,
                'completionlabel' => $completionlabel,
                'assumed_complete' => $assumed >= 1,
                'assumed_pass' => $assumed === 2,
                'assumed_fail' => $assumed === 3,
                'iscomplete' => $assumed >= 1,
            ];
        }

        // Simulated timestamp defaults.
        $simts = $this->state ? $this->state->timestamp : $now;
        $simdate = date('Y-m-d', $simts);
        $simtime = date('H:i', $simts);

        // Group / grouping selections for the scenario form.
        $resolver = new group_resolver($courseid);
        $coursegroups = array_map(function (array $group): array {
            $group['selected'] = $this->state ? in_array((int) $group['id'], $this->state->groupids, true) : false;
            return $group;
        }, $resolver->get_groups_for_template());
        $coursegroupings = array_map(function (array $grouping): array {
            $grouping['selected'] = $this->state ? in_array((int) $grouping['id'], $this->state->groupingids, true) : false;
            return $grouping;
        }, $resolver->get_groupings_for_template());

        // Run simulation if state provided.
        $hasresults = $this->state !== null;
        $resultrows = [];
        $nextsteprows = [];
        $blockedrows = [];
        $accessiblecount = 0;
        $blockedcount = 0;

        if ($hasresults) {
            $simulator = new visibility_simulator();
            $simresults = $simulator->simulate($cms, $this->state);
            $engine = new next_step_engine();
            $nextstepids = $engine->find_next_steps($simresults, $cms, $this->state);
            $blockedids = $engine->find_blocked($simresults);

            foreach ($simresults as $cmid => $result) {
                $cm = $cms[$cmid] ?? null;
                if ($cm === null) {
                    continue;
                }
                $accessible = $result['accessible'];
                if ($accessible) {
                    $accessiblecount++;
                }
                $isnextstep = in_array($cmid, $nextstepids, true);
                $isblocked = in_array($cmid, $blockedids, true);

                $reasonrows = [];
                foreach ($result['reasons'] as $reason) {
                    $reasonrows[] = $this->format_reason($reason, $dateformat, $cms);
                }

                $iscomplete = $this->state !== null
                    && $this->state->get_completion($cmid) > 0;

                $resultrows[] = [
                    'cmid' => $cmid,
                    'name' => $result['name'],
                    'modname' => $result['modname'],
                    'teacher_visible' => $result['teacher_visible'],
                    'accessible' => $accessible,
                    'status' => $result['status'],
                    'status_pass' => $result['status'] === condition_evaluator::STATUS_PASS,
                    'status_fail' => $result['status'] === condition_evaluator::STATUS_FAIL,
                    'status_unknown' => $result['status'] === condition_evaluator::STATUS_UNKNOWN,
                    'has_restrictions' => $result['has_restrictions'],
                    'isnextstep' => $isnextstep,
                    'isblocked' => $isblocked,
                    'iscomplete' => $iscomplete,
                    'reasons' => $reasonrows,
                    'hasreasons' => count($reasonrows) > 0,
                    'url' => (new \moodle_url(
                        '/mod/' . $result['modname'] . '/view.php',
                        ['id' => $cmid]
                    ))->out(false),
                ];
            }

            foreach ($nextstepids as $cmid) {
                $nextsteprows[] = [
                    'cmid' => $cmid,
                    'name' => $cms[$cmid]->name ?? 'cmid ' . $cmid,
                    'cmname' => $cms[$cmid]->name ?? 'cmid ' . $cmid,
                    'modname' => $cms[$cmid]->modname ?? '',
                    'url' => (new \moodle_url(
                        '/mod/' . ($cms[$cmid]->modname ?? 'assign') . '/view.php',
                        ['id' => $cmid]
                    ))->out(false),
                    'cmurl' => (new \moodle_url(
                        '/mod/' . ($cms[$cmid]->modname ?? 'assign') . '/view.php',
                        ['id' => $cmid]
                    ))->out(false),
                ];
            }

            foreach ($blockedids as $cmid) {
                $blockedcount++;
                $blockedrows[] = [
                    'cmid' => $cmid,
                    'name' => $cms[$cmid]->name ?? 'cmid ' . $cmid,
                    'cmname' => $cms[$cmid]->name ?? 'cmid ' . $cmid,
                    'cmurl' => (new \moodle_url(
                        '/mod/' . ($cms[$cmid]->modname ?? 'assign') . '/view.php',
                        ['id' => $cmid]
                    ))->out(false),
                ];
            }
        }

        return [
            'courseid' => $courseid,
            'coursefullname' => format_string($course->fullname),
            'sesskey' => sesskey(),
            'hasresults' => $hasresults,
            'cmformrows' => $cmformrows,
            'hascmformrows' => count($cmformrows) > 0,
            'simdate' => $simdate,
            'simtime' => $simtime,
            'simts' => $simts,
            'coursegroups' => $coursegroups,
            'hascoursegroups' => count($coursegroups) > 0,
            'coursegroupings' => $coursegroupings,
            'hascoursegroupings' => count($coursegroupings) > 0,
            'resultrows' => $resultrows,
            'hasresultrows' => count($resultrows) > 0,
            'nextsteprows' => $nextsteprows,
            'hasnextsteps' => count($nextsteprows) > 0,
            'nextstepcount' => count($nextsteprows),
            'blockedrows' => $blockedrows,
            'hasblockedrows' => count($blockedrows) > 0,
            'blockedcount' => $blockedcount,
            'accessiblecount' => $accessiblecount,
            'totalcmcount' => count($cms),
            'dashboardurl' => (new \moodle_url(
                '/local/coursectrl/index.php',
                ['courseid' => $courseid]
            ))->out(false),
            'graphurl' => (new \moodle_url(
                '/local/coursectrl/graph.php',
                ['courseid' => $courseid]
            ))->out(false),
            'selfurl' => (new \moodle_url(
                '/local/coursectrl/simulation.php',
                ['courseid' => $courseid]
            ))->out(false),
        ];
    }

    /**
     * Format a condition_evaluator reason array for Mustache.
     *
     * @param array  $reason     Raw reason from condition_evaluator.
     * @param string $dateformat Moodle date format string.
     * @return array Template-ready reason array.
     */
    private function format_reason(array $reason, string $dateformat, array $cms = []): array {
        $type = $reason['type'] ?? '';
        $status = $reason['status'] ?? condition_evaluator::STATUS_UNKNOWN;
        $base = [
            'type' => $type,
            'status' => $status,
            'pass' => $status === condition_evaluator::STATUS_PASS,
            'fail' => $status === condition_evaluator::STATUS_FAIL,
            'unknown' => $status === condition_evaluator::STATUS_UNKNOWN,
            'label' => '',
        ];

        if ($type === 'completion') {
            $depcmid = (int) ($reason['cmid'] ?? 0);
            $depcm = $cms[$depcmid] ?? null;
            $depname = $depcm ? $depcm->name : 'cmid ' . $depcmid;
            $depmodname = $depcm ? $depcm->modname : '';
            $depurl = $depcm
                ? (new \moodle_url('/mod/' . $depmodname . '/view.php', ['id' => $depcmid]))->out(false)
                : '';
            $base['label'] = get_string(
                'sim_reason_completion',
                'local_coursectrl',
                (object)['cmname' => $depname, 'cmurl' => $depurl, 'expected' => $reason['expected']]
            );
        } else if ($type === 'date') {
            $base['label'] = get_string(
                'sim_reason_date',
                'local_coursectrl',
                (object)[
                    'direction' => $reason['direction'],
                    'threshold' => userdate($reason['threshold'], $dateformat),
                ]
            );
        } else if ($type === 'group') {
            $base['label'] = get_string(
                'sim_reason_group',
                'local_coursectrl',
                (object)['groupid' => $reason['groupid']]
            );
        } else if ($type === 'grouping') {
            $base['label'] = get_string(
                'sim_reason_grouping',
                'local_coursectrl',
                (object)['groupingid' => $reason['groupingid']]
            );
        } else if ($type === 'grade') {
            $base['label'] = get_string('sim_reason_grade', 'local_coursectrl');
        } else if ($type === 'teacher_hidden') {
            $base['label'] = get_string('sim_reason_hidden', 'local_coursectrl');
        } else {
            $base['label'] = get_string('sim_reason_unknown', 'local_coursectrl');
        }

        return $base;
    }
}
