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
     * @param object             $course    Course record.
     * @param string             $activetab Active tab identifier.
     * @param bool               $freshrun  True to trigger a fresh risk assessment run.
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
            'risks'             => $this->build_risks_tab(
                $snapshot->cms,
                $depindex,
                $datesbycm,
                $cmnames,
                $cmurls,
                $courseid,
                $snapshot->cms
            ),
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

        $items = [];
        $errorcount = 0;
        $warningcount = 0;
        $noticecount = 0;

        foreach ($warnings as $cmid => $issues) {
            $cmname = $cmnames[$cmid] ?? 'ID ' . $cmid;
            $cmurl = $cmurls[$cmid] ?? '#';
            foreach ($issues as $issue) {
                $item = $this->format_consistency_item(
                    $issue,
                    (int)$cmid,
                    $cmname,
                    $cmurl,
                    $cms[$cmid] ?? null,
                    $cmnames,
                    $cmurls
                );
                $items[] = $item;
                $severity = $item['severity'];
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
     * Format a single consistency issue into a rich, user-facing item array.
     *
     * Every item contains:
     *   severity      string   'error'|'warning'|'notice'
     *   icon          string   Emoji icon
     *   cmid          int      Course module id
     *   cmname        string   Activity name
     *   cmurl         string   Link to activity
     *   headline      string   One-line problem summary
     *   detail        string   Full explanation with field names and dates
     *   consequence   string   What happens if this is not fixed
     *   action        string   Concrete suggestion what to do
     *   simurl        string   Link to simulation with this CM's course
     *   hasaction     bool
     *
     * @param array       $issue    Raw issue from consistency_runner.
     * @param int         $cmid     Course module id.
     * @param string      $cmname   Activity display name.
     * @param string      $cmurl    Activity URL.
     * @param mixed       $cm       CM item object or null.
     * @param array       $cmnames  cmid → name lookup.
     * @param array       $cmurls   cmid → url lookup.
     * @return array
     */
    private function format_consistency_item(
        array $issue,
        int $cmid,
        string $cmname,
        string $cmurl,
        $cm,
        array $cmnames,
        array $cmurls
    ): array {
        $type = $issue['type'] ?? 'unknown';
        $severity = $issue['severity'] ?? 'warning';
        $severityicon = ['error' => '❗', 'warning' => '⚠️', 'notice' => 'ℹ️'];
        $icon = $severityicon[$severity] ?? '⚠️';
        $courseid = (int)$this->course->id;

        $simurl = (new \moodle_url(
            '/local/coursectrl/checks.php',
            ['courseid' => $courseid, 'tab' => 'simulation']
        ))->out(false);

        $headline = '';
        $detail = '';
        $consequence = '';
        $action = '';

        $dateformat = get_string('strftimedatetimeshort', 'langconfig');

        if ($type === 'temporal_conflict') {
            $fearly = $issue['field_early'] ?? '';
            $flate = $issue['field_late'] ?? '';
            $tsearly = (int)($issue['ts_early'] ?? 0);
            $tslate = (int)($issue['ts_late'] ?? 0);
            $learly = $this->field_label($fearly, $cm);
            $llate = $this->field_label($flate, $cm);
            $headline = get_string('consistency_headline_temporal_conflict', 'local_coursectrl');
            $detail = get_string(
                'consistency_detail_temporal_conflict',
                'local_coursectrl',
                (object)[
                    'field_early'  => $learly,
                    'date_early'   => $tsearly > 0 ? userdate($tsearly, $dateformat) : '–',
                    'field_late'   => $llate,
                    'date_late'    => $tslate > 0 ? userdate($tslate, $dateformat) : '–',
                ]
            );
            $consequence = get_string('consistency_consequence_temporal_conflict', 'local_coursectrl');
            $action = get_string('consistency_action_temporal_conflict', 'local_coursectrl');
        } else if ($type === 'r0_after_course_end') {
            $courseend = (int)($this->course->enddate ?? 0);
            $fieldname = $issue['field'] ?? '';
            $tsfieldval = (int)($issue['ts_field'] ?? 0);
            $fieldlabel = $this->field_label($fieldname, $cm);
            $headline = get_string('consistency_headline_r0_after_end', 'local_coursectrl');
            $detail = get_string(
                'consistency_detail_r0_after_end_field',
                'local_coursectrl',
                (object)[
                    'fieldlabel' => $fieldlabel,
                    'fielddate'  => $tsfieldval > 0 ? userdate($tsfieldval, $dateformat) : '–',
                    'courseend'  => $courseend > 0 ? userdate($courseend, $dateformat) : '–',
                ]
            );
            $consequence = get_string('consistency_consequence_r0_after_end', 'local_coursectrl');
            $action = get_string('consistency_action_r0_after_end', 'local_coursectrl');
        } else if ($type === 'r0_before_course_start') {
            $coursestart = (int)($this->course->startdate ?? 0);
            $fieldname = $issue['field'] ?? '';
            $tsfieldval = (int)($issue['ts_field'] ?? 0);
            $fieldlabel = $this->field_label($fieldname, $cm);
            $headline = get_string('consistency_headline_r0_before_start', 'local_coursectrl');
            $detail = get_string(
                'consistency_detail_r0_before_start_field',
                'local_coursectrl',
                (object)[
                    'fieldlabel'  => $fieldlabel,
                    'fielddate'   => $tsfieldval > 0 ? userdate($tsfieldval, $dateformat) : '–',
                    'coursestart' => $coursestart > 0 ? userdate($coursestart, $dateformat) : '–',
                ]
            );
            $consequence = get_string('consistency_consequence_r0_before_start', 'local_coursectrl');
            $action = get_string('consistency_action_r0_before_start', 'local_coursectrl');
        } else if ($type === 'r0_deadline_in_past') {
            $fieldname = $issue['field'] ?? '';
            $tsfieldval = (int)($issue['ts_field'] ?? 0);
            $fieldlabel = $this->field_label($fieldname, $cm);
            $headline = get_string('consistency_headline_r0_deadline_past', 'local_coursectrl');
            $detail = get_string(
                'consistency_detail_r0_deadline_past_field',
                'local_coursectrl',
                (object)[
                    'fieldlabel' => $fieldlabel,
                    'fielddate'  => $tsfieldval > 0 ? userdate($tsfieldval, $dateformat) : '–',
                ]
            );
            $consequence = get_string('consistency_consequence_r0_deadline_past', 'local_coursectrl');
            $action = get_string('consistency_action_r0_deadline_past', 'local_coursectrl');
        } else if ($type === 'dangling_dep') {
            $depcmid = (int)($issue['depcmid'] ?? 0);
            $headline = get_string('consistency_headline_dangling_dep', 'local_coursectrl');
            $detail = get_string(
                'consistency_detail_dangling_dep',
                'local_coursectrl',
                (object)['cmid' => $depcmid]
            );
            $consequence = get_string('consistency_consequence_dangling_dep', 'local_coursectrl');
            $action = get_string('consistency_action_dangling_dep', 'local_coursectrl');
        } else if ($type === 'impossible_dep') {
            $depname = $issue['depname'] ?? '';
            $depcmid = (int)($issue['depcmid'] ?? 0);
            $depurl = $cmurls[$depcmid] ?? '#';
            $headline = get_string('consistency_headline_impossible_dep', 'local_coursectrl');
            $detail = get_string(
                'consistency_detail_impossible_dep',
                'local_coursectrl',
                (object)['name' => $depname]
            );
            $consequence = get_string('consistency_consequence_impossible_dep', 'local_coursectrl');
            $action = get_string('consistency_action_impossible_dep', 'local_coursectrl');
        } else if ($type === 'dangling_group' || $type === 'dangling_grouping') {
            $headline = get_string('consistency_headline_dangling_group', 'local_coursectrl');
            $detail = get_string('consistency_detail_dangling_group', 'local_coursectrl');
            $consequence = get_string('consistency_consequence_dangling_group', 'local_coursectrl');
            $action = get_string('consistency_action_dangling_group', 'local_coursectrl');
        } else {
            // Adapter R3/R7 checks or unknown type — use the message directly.
            $headline = $issue['message'] ?? $type;
            $detail = '';
            $consequence = '';
            $action = '';
        }

        return [
            'severity'       => $severity,
            'icon'           => $icon,
            'cmid'           => $cmid,
            'cmname'         => $cmname,
            'cmurl'          => $cmurl,
            'modname'        => $cm !== null ? $cm->modname : '',
            'type'           => $type,
            'headline'       => $headline,
            'detail'         => $detail,
            'hasdetail'      => $detail !== '',
            'consequence'    => $consequence,
            'hasconsequence' => $consequence !== '',
            'action'         => $action,
            'hasaction'      => $action !== '',
            'simurl'         => $simurl,
            'fix_type'       => $this->fix_type_for($type),
            'fix_url'        => $this->fix_url_for($type, $cmid, $cm, $courseid, 'consistency'),
            'fix_label'      => $this->fix_label_for($type),
            'has_fix'        => $this->fix_type_for($type) !== '',
        ];
    }

    /**
     * Return a human-readable label for a date field name.
     *
     * Tries the subplugin lang file first (field_{name}), falls back to
     * the field name itself.
     *
     * @param string $field Field identifier (e.g. 'duedate').
     * @param mixed  $cm    CM item (used to find the subplugin component).
     * @return string
     */
    private function field_label(string $field, $cm): string {
        if ($cm !== null) {
            $component = $cm->get_component();
            // Subplugin component: 'mod_assign' → 'coursectrlmod_assign'.
            $subplugin = str_replace('mod_', 'coursectrlmod_', $component);
            $label = get_string('field_' . $field, $subplugin, null, true);
            if ($label !== false && $label !== '') {
                return $label;
            }
        }
        $label = get_string('field_' . $field, 'local_coursectrl', null, true);
        if ($label !== false && $label !== '') {
            return $label;
        }
        return $field;
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
        int $courseid,
        array $cmobjects = []
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

        $groups = $this->group_risk_items($items, $cmnames, $cmurls, $cmobjects, $courseid);

        return [
            'hasresults'   => !empty($items),
            'haslastrun'   => $lastrun > 0,
            'lastrundate'  => $lastrun > 0 ? userdate($lastrun) : '',
            'totalcount'   => count($items),
            'errorcount'   => $errorcount,
            'warningcount' => $warningcount,
            'noticecount'  => $noticecount,
            'rows'         => $groups,
            'hasrows'      => !empty($groups),
        ];
    }

    /**
     * Build a flat list of scored risk items enriched for the UI.
     *
     * Instead of grouping by type (which loses the per-activity context),
     * each finding is a self-contained row with: activity icon, name, link,
     * problem description, consequence, and concrete action.
     *
     * @param array[] $items
     * @param array   $cmnames
     * @param array   $cmurls
     * @param array   $cms      Full cm_item objects keyed by cmid.
     * @param int     $courseid Course id (required for fix URLs).
     * @return array[]
     */
    private function group_risk_items(
        array $items,
        array $cmnames,
        array $cmurls,
        array $cms = [],
        int $courseid = 0
    ): array {
        $severityicon = ['error' => '❗', 'warning' => '⚠️', 'notice' => 'ℹ️'];
        $dateformat = get_string('strftimedatetimeshort', 'langconfig');

        $rows = [];
        foreach ($items as $item) {
            $type = $item['type'] ?? 'unknown';
            $severity = $item['severity'] ?? 'notice';

            // Primary CM.
            $primarycmid = (int)(($item['cmids'] ?? [])[0] ?? 0);
            $cmname = $cmnames[$primarycmid] ?? 'ID ' . $primarycmid;
            $cmurl = $cmurls[$primarycmid] ?? '#';
            $cm = $cms[$primarycmid] ?? null;
            $modname = $cm !== null ? $cm->modname : '';

            // Related CMs (e.g. hidden prereqs, dependents).
            $relatedlinked = [];
            foreach ($item['related_cmids'] ?? [] as $rcmid) {
                $relatedlinked[] = [
                    'cmid'    => $rcmid,
                    'name'    => $cmnames[$rcmid] ?? 'ID ' . $rcmid,
                    'url'     => $cmurls[$rcmid] ?? '#',
                    'modname' => isset($cms[$rcmid]) ? $cms[$rcmid]->modname : '',
                ];
            }

            // Cascade CMs.
            $cascadelinked = [];
            foreach ($item['cascade_cmids'] ?? [] as $ccmid) {
                $cascadelinked[] = [
                    'cmid'    => $ccmid,
                    'name'    => $cmnames[$ccmid] ?? 'ID ' . $ccmid,
                    'url'     => $cmurls[$ccmid] ?? '#',
                    'modname' => isset($cms[$ccmid]) ? $cms[$ccmid]->modname : '',
                ];
            }

            // Type label.
            $typelabelkey = 'risk_type_' . $type;
            $typelabel = get_string($typelabelkey, 'local_coursectrl', null, true) ?: $type;

            // Build problem description and action for this specific type.
            [$problem, $action] = $this->risk_type_texts($type, $item, $cmname, $relatedlinked, $dateformat);

            // Simulation link pre-filled with the relevant timestamp when available.
            $simts = (int)($item['ts_field'] ?? $item['ts_early'] ?? 0);
            $simparams = ['courseid' => (int)$this->course->id, 'tab' => 'simulation'];
            if ($simts > 0) {
                $simparams['simdate'] = date('Y-m-d', $simts);
                $simparams['simtime'] = date('H:i', $simts);
            }
            $simurl = (new \moodle_url('/local/coursectrl/checks.php', $simparams))->out(false);

            $rows[] = [
                'type'          => $type,
                'typelabel'     => $typelabel,
                'severity'      => $severity,
                'icon'          => $severityicon[$severity] ?? '⚠️',
                'score'         => $item['score'] ?? 0,
                'cmid'          => $primarycmid,
                'cmname'        => $cmname,
                'cmurl'         => $cmurl,
                'modname'       => $modname,
                'problem'       => $problem,
                'hasproblem'    => $problem !== '',
                'action'        => $action,
                'hasaction'     => $action !== '',
                'related'       => $relatedlinked,
                'hasrelated'    => !empty($relatedlinked),
                'cascade'       => $cascadelinked,
                'hascascade'    => !empty($cascadelinked),
                'cascade_count' => count($cascadelinked),
                'simurl'        => $simurl,
                'fix_type'      => $this->fix_type_for($type),
                'fix_url'       => $this->fix_url_for($type, $primarycmid, $cm, $courseid, 'risks'),
                'fix_label'     => $this->fix_label_for($type),
                'has_fix'       => $this->fix_type_for($type) !== '',
                'escape_message' => get_string(
                    'checks_escape_' . ($item['escape_type'] ?? 'none'),
                    'local_coursectrl',
                    null,
                    true
                ) ?: '',
                'has_escape'    => !empty($item['has_escape']),
            ];
        }
        return $rows;
    }

    /**
     * Return [problem_description, action_text] for a risk type.
     *
     * @param string  $type
     * @param array   $item
     * @param string  $cmname
     * @param array[] $relatedlinked
     * @param string  $dateformat
     * @return array{0: string, 1: string}
     */
    private function risk_type_texts(
        string $type,
        array $item,
        string $cmname,
        array $relatedlinked,
        string $dateformat
    ): array {
        $relatednames = implode(', ', array_column($relatedlinked, 'name'));
        $a = (object)[
            'cmname'       => $cmname,
            'related'      => $relatednames ?: '–',
            'count'        => count($relatedlinked),
        ];

        if ($type === 'dep_on_hidden') {
            return [
                get_string('risk_problem_dep_on_hidden', 'local_coursectrl', $a),
                get_string('risk_action_dep_on_hidden', 'local_coursectrl', $a),
            ];
        }
        if ($type === 'hidden_with_dependents') {
            return [
                get_string('risk_problem_hidden_with_dependents', 'local_coursectrl', $a),
                get_string('risk_action_hidden_with_dependents', 'local_coursectrl', $a),
            ];
        }
        if ($type === 'circular_dep' || $type === 'circular_dep_transitive') {
            return [
                get_string('risk_problem_circular_dep', 'local_coursectrl', $a),
                get_string('risk_action_circular_dep', 'local_coursectrl'),
            ];
        }
        if ($type === 'completion_required_no_tracking' || $type === 'completion_no_tracking') {
            return [
                get_string('risk_problem_completion_no_tracking', 'local_coursectrl', $a),
                get_string('risk_action_completion_no_tracking', 'local_coursectrl', $a),
            ];
        }
        if ($type === 'temporal_conflict') {
            $fearly = $item['field_early'] ?? '';
            $flate = $item['field_late'] ?? '';
            $tsearly = (int)($item['ts_early'] ?? 0);
            $tslate = (int)($item['ts_late'] ?? 0);
            $a->field_early = $fearly;
            $a->date_early = $tsearly > 0 ? userdate($tsearly, $dateformat) : '–';
            $a->field_late = $flate;
            $a->date_late = $tslate > 0 ? userdate($tslate, $dateformat) : '–';
            return [
                get_string('risk_problem_temporal_conflict', 'local_coursectrl', $a),
                get_string('risk_action_temporal_conflict', 'local_coursectrl'),
            ];
        }
        if ($type === 'deadline_before_dep_window') {
            return [
                get_string('risk_problem_deadline_before_dep_window', 'local_coursectrl', $a),
                get_string('risk_action_deadline_before_dep_window', 'local_coursectrl'),
            ];
        }
        if ($type === 'long_dep_chain') {
            return [
                get_string('risk_problem_long_dep_chain', 'local_coursectrl', $a),
                get_string('risk_action_long_dep_chain', 'local_coursectrl'),
            ];
        }
        // Fallback: use stored message if available.
        $msg = $item['message'] ?? '';
        return [$msg, ''];
    }

    /**
     * Return the fix type code for a given issue type, or '' if no one-click fix exists.
     *
     * @param string $type Issue type.
     * @return string 'unhide_cm' | 'modedit_availability' | 'timeline' | 'dependencies' | ''
     */
    private function fix_type_for(string $type): string {
        $map = [
            'dep_on_hidden'           => 'unhide_cm',
            'hidden_with_dependents'  => 'unhide_cm',
            'r1_hidden'               => 'unhide_cm',
            'dangling_dep'            => 'modedit_availability',
            'impossible_dep'          => 'modedit_availability',
            'dangling_group'          => 'modedit_availability',
            'dangling_grouping'       => 'modedit_availability',
            'temporal_conflict'       => 'timeline',
            'date_coupling'           => 'timeline',
            'r0_after_course_end'     => 'timeline',
            'r0_before_course_start'  => 'timeline',
            'r0_deadline_in_past'     => 'timeline',
            'circular_dep'            => 'modedit_availability',
            'circular_dep_transitive' => 'modedit_availability',
        ];
        return $map[$type] ?? '';
    }

    /**
     * Build the fix action URL for a given issue type and CM.
     *
     * @param string   $type      Issue type.
     * @param int      $cmid      Primary CM id.
     * @param mixed    $cm        CM item object or null.
     * @param int      $courseid  Course id.
     * @param string   $tab       Checks tab to return to after fix.
     * @return string URL or empty string.
     */
    private function fix_url_for(string $type, int $cmid, $cm, int $courseid, string $tab): string {
        $fixtype = $this->fix_type_for($type);
        if ($fixtype === '') {
            return '';
        }
        if ($fixtype === 'unhide_cm') {
            return (new \moodle_url(
                '/local/coursectrl/fix_action.php',
                [
                    'courseid' => $courseid,
                    'action'   => 'unhide_cm',
                    'cmid'     => $cmid,
                    'tab'      => $tab,
                    'sesskey'  => sesskey(),
                ]
            ))->out(false);
        }
        if ($fixtype === 'modedit_availability') {
            return (new \moodle_url(
                '/course/modedit.php',
                ['update' => $cmid, 'return' => 1]
            ))->out(false) . '#id_availabilityconditionsjson';
        }
        if ($fixtype === 'timeline') {
            return (new \moodle_url(
                '/local/coursectrl/timeline.php',
                ['courseid' => $courseid, 'focus' => $cmid]
            ))->out(false);
        }
        return '';
    }

    /**
     * Return the label for the fix button for a given issue type.
     *
     * @param string $type Issue type.
     * @return string Localised label, or empty string.
     */
    private function fix_label_for(string $type): string {
        $fixtype = $this->fix_type_for($type);
        $key = 'fix_label_' . $fixtype;
        if ($fixtype === '' || $key === 'fix_label_') {
            return '';
        }
        return get_string($key, 'local_coursectrl', null, true) ?: $fixtype;
    }

    /**
     * Build the template context array for the simulation tab.
     *
     * @param \local_coursectrl\local\inventory\inventory_snapshot $snapshot Current inventory snapshot.
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
