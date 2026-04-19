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
 * Renderable for the unified checks page.
 *
 * Two tabs:
 *   consistency — Plausibility and collision checks (temporal conflicts,
 *                 dangling deps, impossible deps, R3/R7 adapter checks).
 *                 Runs transiently on every page load (fast).
 *
 *   risks       — Structural risk assessment (dead-ends, circular deps,
 *                 escape paths, priority scores).
 *                 Uses the last persisted assessment; run on demand via ?run=1.
 *
 *   simulation  — Learner simulation (visibility and accessibility from a
 *                 defined learner perspective). Delegates to simulation_page.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

use renderable;
use renderer_base;
use templatable;
use local_coursectrl\local\analysis\consistency_runner;
use local_coursectrl\local\analysis\date_collector;
use local_coursectrl\local\analysis\dependency_index;
use local_coursectrl\local\analysis\risk_assessment_runner;
use local_coursectrl\local\inventory\inventory_service;
use local_coursectrl\local\simulation\learner_state;
use local_coursectrl\manager\registry;

/**
 * Renderable for the unified checks page (consistency + risk assessment).
 */
class checks_page implements renderable, templatable {
    /** @var string Active tab: 'consistency' or 'risks'. */
    private string $activetab;

    /** @var bool True when a fresh risk assessment run was requested. */
    private bool $freshrun;

    /** @var learner_state|null Simulation state from submitted form. */
    private ?learner_state $simstate;

    /** @var object Course record. */
    private object $course;

    /**
     * Constructor.
     *
     * @param object $course    Course record.
     * @param string $activetab Active tab identifier ('consistency'|'risks').
     * @param bool   $freshrun  True to trigger a fresh risk assessment run.
     */
    /**
     * Constructor.
     *
     * @param object            $course    Course record.
     * @param string            $activetab Active tab identifier.
     * @param bool              $freshrun  True to trigger a fresh risk assessment run.
     * @param learner_state|null $simstate  Learner state for simulation tab, or null.
     */
    public function __construct(
        object $course,
        string $activetab = 'consistency',
        bool $freshrun = false,
        ?learner_state $simstate = null
    ) {
        $this->course = $course;
        $this->activetab = in_array($activetab, ['consistency', 'risks', 'simulation']) ? $activetab : 'consistency';
        $this->freshrun = $freshrun;
        $this->simstate = $simstate;
    }

    /**
     * Build the full template context for checks.mustache.
     *
     * @param renderer_base $output
     * @return array<string, mixed>
     */
    public function export_for_template(renderer_base $output): array {
        $courseid = (int)$this->course->id;
        $checksurl = (new \moodle_url(
            '/local/coursectrl/checks.php',
            ['courseid' => $courseid]
        ))->out(false);

        $svc = new inventory_service();
        $snapshot = $svc->build_for_course($courseid);
        $depindex = new dependency_index($snapshot->cms);
        $datecollector = new date_collector();
        $datesbycm = $datecollector->collect_grouped_by_cm($snapshot->cms);

        // Build CM name + URL lookup (shared by both tabs).
        $cmnames = [];
        $cmurls = [];
        foreach ($snapshot->cms as $cm) {
            $cmnames[$cm->id] = $cm->name;
            $cmurls[$cm->id] = (new \moodle_url(
                '/mod/' . $cm->modname . '/view.php',
                ['id' => $cm->id]
            ))->out(false);
        }

        return [
            'courseid'          => $courseid,
            'coursefullname'    => $this->course->fullname,
            'checksurl'         => $checksurl,
            'tab_consistency'   => $this->activetab === 'consistency',
            'tab_risks'         => $this->activetab === 'risks',
            'tab_simulation'    => $this->activetab === 'simulation',
            'consistency'       => $this->build_consistency_tab($snapshot->cms, $depindex, $datesbycm, $cmnames, $cmurls),
            'risks'             => $this->build_risks_tab($snapshot->cms, $depindex, $datesbycm, $cmnames, $cmurls, $courseid),
            'simulation'        => $this->build_simulation_tab($snapshot),
            'runurl'            => (new \moodle_url(
                '/local/coursectrl/checks.php',
                ['courseid' => $courseid, 'tab' => 'risks', 'run' => 1]
            ))->out(false),
        ];
    }

    /**
     * Build the consistency tab context (transient, runs every page load).
     *
     * @param array            $cms
     * @param dependency_index $depindex
     * @param array            $datesbycm
     * @param array            $cmnames
     * @param array            $cmurls
     * @return array
     */
    private function build_consistency_tab(
        array $cms,
        dependency_index $depindex,
        array $datesbycm,
        array $cmnames,
        array $cmurls
    ): array {
        $runner = new consistency_runner();
        $warnings = $runner->get_warnings($cms, $depindex, $datesbycm, null, $this->course);

        // Merge adapter-specific R3/R7 check results into the consistency list.
        $adapterreg = new registry();
        foreach ($cms as $cm) {
            $adapter = $adapterreg->get_for_component($cm->get_component());
            if ($adapter === null) {
                continue;
            }
            foreach ($adapter->run_checks([$cm->id]) as $result) {
                $severity = $result['severity'] ?? 'warning';
                $warnings[$cm->id][] = [
                    'type'     => $result['code'] ?? 'adapter_check',
                    'severity' => $severity,
                    'message'  => $result['message'] ?? '',
                ];
            }
        }

        $severityicon = ['error' => '❗', 'warning' => '⚠️', 'notice' => 'ℹ️'];
        $items = [];
        $errorcount = 0;
        $warningcount = 0;
        $noticecount = 0;

        foreach ($warnings as $cmid => $issues) {
            foreach ($issues as $issue) {
                $severity = $issue['severity'] ?? 'warning';
                $items[] = [
                    'icon'       => $severityicon[$severity] ?? '⚠️',
                    'severity'   => $severity,
                    'cmid'       => $cmid,
                    'cmname'     => $cmnames[$cmid] ?? 'ID ' . $cmid,
                    'cmurl'      => $cmurls[$cmid] ?? '#',
                    'message'    => $issue['message'] ?? '',
                    'type'       => $issue['type'] ?? '',
                ];
                if ($severity === 'error') {
                    $errorcount++;
                } else if ($severity === 'warning') {
                    $warningcount++;
                } else {
                    $noticecount++;
                }
            }
        }

        // Sort: errors first, then warnings, then notices.
        usort($items, function ($a, $b) {
            $order = ['error' => 0, 'warning' => 1, 'notice' => 2];
            return ($order[$a['severity']] ?? 3) - ($order[$b['severity']] ?? 3);
        });

        return [
            'items'        => $items,
            'hasitems'     => !empty($items),
            'errorcount'   => $errorcount,
            'warningcount' => $warningcount,
            'noticecount'  => $noticecount,
            'totalcount'   => count($items),
        ];
    }

    /**
     * Build the risk assessment tab context.
     *
     * Uses the last persisted assessment unless a fresh run was requested.
     *
     * @param array            $cms
     * @param dependency_index $depindex
     * @param array            $datesbycm
     * @param array            $cmnames
     * @param array            $cmurls
     * @param int              $courseid
     * @return array
     */
    private function build_risks_tab(
        array $cms,
        dependency_index $depindex,
        array $datesbycm,
        array $cmnames,
        array $cmurls,
        int $courseid
    ): array {
        if ($this->freshrun) {
            $runner = new risk_assessment_runner();
            $items = $runner->run($cms, $depindex, $datesbycm, $courseid);
            $lastrun = time();
        } else {
            $items = risk_assessment_runner::load_last($courseid);
            $lastrun = risk_assessment_runner::last_run_time($courseid);
        }

        $errorcount = count(array_filter($items, fn ($i) => ($i['severity'] ?? '') === 'error'));
        $warningcount = count(array_filter($items, fn ($i) => ($i['severity'] ?? '') === 'warning'));
        $noticecount = count(array_filter($items, fn ($i) => ($i['severity'] ?? '') === 'notice'));

        $groups = $this->group_risk_items($items, $cmnames, $cmurls);

        return [
            'hasresults'   => !empty($items),
            'haslastrun'   => $lastrun > 0,
            'lastrundate'  => $lastrun > 0 ? userdate($lastrun) : '',
            'totalcount'   => count($items),
            'errorcount'   => $errorcount,
            'warningcount' => $warningcount,
            'noticecount'  => $noticecount,
            'groups'       => $groups,
            'hasgroups'    => !empty($groups),
        ];
    }

    /**
     * Group scored risk items by type for the UI.
     *
     * @param array[] $items
     * @param array   $cmnames
     * @param array   $cmurls
     * @return array[]
     */
    private function group_risk_items(array $items, array $cmnames, array $cmurls): array {
        $bytype = [];
        $severityicon = ['error' => '❗', 'warning' => '⚠️', 'notice' => 'ℹ️'];

        foreach ($items as $item) {
            $type = $item['type'] ?? 'unknown';
            if (!isset($bytype[$type])) {
                $bytype[$type] = [
                    'type'         => $type,
                    'severity'     => $item['severity'] ?? 'notice',
                    'score'        => $item['score'] ?? 0,
                    'findings'     => [],
                ];
            }
            if (($item['score'] ?? 0) > $bytype[$type]['score']) {
                $bytype[$type]['score'] = $item['score'];
                $bytype[$type]['severity'] = $item['severity'] ?? 'notice';
            }

            $linkedcms = [];
            foreach ($item['cmids'] ?? [] as $cmid) {
                $linkedcms[] = [
                    'cmid' => $cmid,
                    'name' => $cmnames[$cmid] ?? 'ID ' . $cmid,
                    'url'  => $cmurls[$cmid] ?? '#',
                ];
            }
            $cascadelinked = [];
            foreach ($item['cascade_cmids'] ?? [] as $cmid) {
                $cascadelinked[] = [
                    'cmid' => $cmid,
                    'name' => $cmnames[$cmid] ?? 'ID ' . $cmid,
                    'url'  => $cmurls[$cmid] ?? '#',
                ];
            }

            $bytype[$type]['findings'][] = [
                'cmids'         => $linkedcms,
                'hascmids'      => !empty($linkedcms),
                'cascade_cmids' => $cascadelinked,
                'hascascade'    => !empty($cascadelinked),
                'cascade_count' => $item['cascade_count'] ?? 0,
                'score'         => $item['score'] ?? 0,
                'severity'      => $item['severity'] ?? 'notice',
                'probability'   => $item['probability'] ?? 1.0,
                'has_escape'    => !empty($item['has_escape']),
                'escape_type'   => $item['escape_type'] ?? 'none',
                'escape_message' => get_string(
                    'checks_escape_' . ($item['escape_type'] ?? 'none'),
                    'local_coursectrl',
                    null,
                    true
                ) ?: ($item['escape_type'] ?? 'none'),
                'message'       => $item['message'] ?? '',
                'hasmessage'    => !empty($item['message']),
            ];
        }

        uasort($bytype, fn ($a, $b) => $b['score'] - $a['score']);

        $result = [];
        foreach ($bytype as $type => $group) {
            $labelkey = 'risk_type_' . $type;
            $label = get_string($labelkey, 'local_coursectrl', null, true) ?: $type;
            $group['label'] = $label;
            $group['icon'] = $severityicon[$group['severity']] ?? 'ℹ️';
            $group['findingcount'] = count($group['findings']);
            $result[] = $group;
        }
        return $result;
    }

    /**
     * Build the simulation tab context by delegating to simulation_page.
     *
     * @param \local_coursectrl\local\inventory\inventory_snapshot $snapshot
     * @return array Template context from simulation_page::export_for_template().
     */
    private function build_simulation_tab(
        \local_coursectrl\local\inventory\inventory_snapshot $snapshot
    ): array {
        global $OUTPUT, $PAGE;
        $simpage = new simulation_page($snapshot, $this->simstate);
        $renderer = $PAGE->get_renderer('local_coursectrl');
        // Render the simulation template and pass as pre-rendered HTML so
        // checks.mustache can include it without a nested template call.
        $html = $renderer->render_simulation_page($simpage);
        return ['simulationhtml' => $html];
    }
}
