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
use local_coursectrl\local\field_label_resolver;
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
        $deepanalysisurl = (new \moodle_url(
            '/local/coursectrl/checks.php',
            ['courseid' => $courseid, 'tab' => 'risks', 'run' => '1']
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
            'deepanalysisurl'   => $deepanalysisurl,
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
                $snapshot->cms,
                array_values($snapshot->sections ?? [])
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

        $dateformat = get_string('strftimerecent', 'langconfig');

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
        } else if ($type === 'r1_hidden') {
            $headline     = get_string('consistency_headline_r1_hidden', 'local_coursectrl');
            $detail       = get_string('consistency_detail_r1_hidden', 'local_coursectrl');
            $consequence  = get_string('consistency_consequence_r1_hidden', 'local_coursectrl');
            $action       = get_string('consistency_action_r1_hidden', 'local_coursectrl');
        } else if ($type === 'r1_not_accessible') {
            $headline     = get_string('consistency_headline_r1_not_accessible', 'local_coursectrl');
            $detail       = get_string('consistency_detail_r1_not_accessible', 'local_coursectrl');
            $consequence  = get_string('consistency_consequence_r1_not_accessible', 'local_coursectrl');
            $action       = get_string('consistency_action_r1_not_accessible', 'local_coursectrl');
        } else if (
            $type === 'completionexpected_window'
            || $type === 'completionexpected_after_deadline'
            || $type === 'completionexpected_gap_exceeds_threshold'
        ) {
            $tsexp      = (int)($issue['ts_completionexpected'] ?? 0);
            $tsdeadline = (int)($issue['ts_deadline'] ?? 0);
            $fielddeadline = $issue['field_deadline'] ?? '';
            $dlabel = $fielddeadline !== '' ? $this->field_label($fielddeadline, $cm) : '—';
            // Strftimerecent uses %d (zero-padded day) in all Moodle locales.
            $fmtrecent = get_string('strftimerecent', 'langconfig');
            $headline = get_string('consistency_headline_completionexpected_window', 'local_coursectrl');
            $detail   = get_string(
                'consistency_detail_completionexpected_window',
                'local_coursectrl',
                (object)[
                    'date_expected' => $tsexp > 0 ? userdate($tsexp, $fmtrecent) : '—',
                    'field_deadline' => $dlabel,
                    'date_deadline'  => $tsdeadline > 0 ? userdate($tsdeadline, $fmtrecent) : '—',
                ]
            );
            $consequence = get_string(
                'consistency_consequence_completionexpected_window',
                'local_coursectrl'
            );
            $action = get_string(
                'consistency_action_completionexpected_window',
                'local_coursectrl'
            );
        } else if ($type === 'circular' || $type === 'warning_circular_dep') {
            $headline    = get_string('consistency_headline_circular_dep', 'local_coursectrl');
            $detail      = get_string('consistency_detail_circular_dep', 'local_coursectrl');
            $consequence = get_string('consistency_consequence_circular_dep', 'local_coursectrl');
            $action      = get_string('consistency_action_circular_dep', 'local_coursectrl');
        } else {
            // Adapter-specific or unknown type — use the message if provided.
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
        // Extract the Moodle module name (e.g. 'assign') for resolution.
        $modname = '';
        if ($cm !== null && method_exists($cm, 'get_component')) {
            $modname = str_replace('mod_', '', $cm->get_component());
        } else if ($cm !== null && isset($cm->modname)) {
            $modname = (string) $cm->modname;
        }
        return field_label_resolver::resolve($field, $modname, 'cm');
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
        array $cmobjects = [],
        array $sections = []
    ): array {
        if ($this->freshrun) {
            $runner = new risk_assessment_runner();
            $items = $runner->run($cms, $depindex, $datesbycm, $courseid, $sections);
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
        $dateformat = get_string('strftimerecent', 'langconfig');

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

            // All grouped CMs for journey_unreachable_group — listed in accordion.
            $groupedcmslinked = [];
            $subsectionchildlinked = [];
            $subsectionchildcount = 0;
            $allcmids = $item['cmids'] ?? [];
            if ($type === 'journey_unreachable_group' && !empty($allcmids)) {
                // Build a reverse map: section_id → [cmids] for quick child lookup.
                $cmsbysection = [];
                foreach ($cms as $lookcmid => $lookcm) {
                    $cmsbysection[(int) $lookcm->sectionid][] = (int) $lookcmid;
                }
                // Build sectionmap from snapshot sections for subsection resolution.
                $snapshotsections = $item['_sections'] ?? [];
                foreach ($allcmids as $gcmid) {
                    $gcmid = (int) $gcmid;
                    $gcm = $cms[$gcmid] ?? null;
                    $groupedcmslinked[] = [
                        'cmid'          => $gcmid,
                        'name'          => $cmnames[$gcmid] ?? 'ID ' . $gcmid,
                        'url'           => $cmurls[$gcmid] ?? '#',
                        'modname'       => $gcm !== null ? $gcm->modname : '',
                        'is_subsection' => $gcm !== null && $gcm->modname === 'subsection',
                        'is_derived'    => false,
                    ];
                    // When this CM is a subsection, list its child activities.
                    if ($gcm !== null && $gcm->modname === 'subsection') {
                        // Find the section whose component = mod_subsection and
                        // itemid = this CM's instance id (= cmid for subsections).
                        // Simpler heuristic: the section that only contains CMs
                        // accessible through this subsection CM.
                        // We use cm_item::sectionid of CMs not in $allcmids
                        // to find the child section.
                        foreach ($item['subsection_children'] ?? [] as $childcmid) {
                            $childcmid = (int) $childcmid;
                            $childcm = $cms[$childcmid] ?? null;
                            $subsectionchildlinked[] = [
                                'cmid'    => $childcmid,
                                'name'    => $cmnames[$childcmid] ?? 'ID ' . $childcmid,
                                'url'     => $cmurls[$childcmid] ?? '#',
                                'modname' => $childcm !== null ? $childcm->modname : '',
                                'parent_name' => $cmnames[$gcmid] ?? '',
                            ];
                        }
                        $subsectionchildcount += count($item['subsection_children'] ?? []);
                    }
                }
                // Fold cascade (derived) CMs into the grouped list with a derived flag.
                // This removes the confusing separate cascade line on group cards.
                foreach ($item['cascade_cmids'] ?? [] as $dercmid) {
                    $dercmid = (int) $dercmid;
                    $dercm = $cms[$dercmid] ?? null;
                    $groupedcmslinked[] = [
                        'cmid'          => $dercmid,
                        'name'          => $cmnames[$dercmid] ?? 'ID ' . $dercmid,
                        'url'           => $cmurls[$dercmid] ?? '#',
                        'modname'       => $dercm !== null ? $dercm->modname : '',
                        'is_subsection' => false,
                        'is_derived'    => true,
                    ];
                }
            }

            // Type label.
            $typelabelkey = 'risk_type_' . $type;
            $typelabel = get_string($typelabelkey, 'local_coursectrl', null, true) ?: $type;

            // Build problem description and action for this specific type.
            [$problem, $action] = $this->risk_type_texts($type, $item, $cmname, $relatedlinked, $dateformat, $modname);

            // Simulation link: use pre-built deep-link for journey findings,
            // ... otherwise generate a generic link with the relevant timestamp.
            $prebuiltlink = $item['simlink'] ?? '';
            if ($prebuiltlink !== '' && in_array($type, ['journey_unreachable', 'journey_unreachable_group'], true)) {
                $simurl = $prebuiltlink;
            } else {
                $simts = (int)($item['ts_field'] ?? $item['ts_early'] ?? 0);
                $simparams = ['courseid' => (int)$this->course->id, 'tab' => 'simulation'];
                if ($simts > 0) {
                    $simparams['simdate'] = date('Y-m-d', $simts);
                    $simparams['simtime'] = date('H:i', $simts);
                }
                $simurl = (new \moodle_url('/local/coursectrl/checks.php', $simparams))->out(false);
            }

            // Format journey steps for template display.
            $journeyrows = [];
            foreach ($item['journey_steps'] ?? [] as $step) {
                $outcome = (int)($step['outcome'] ?? 1);
                $exhausted = !empty($step['attempts_exhausted']);
                $hastrack = !empty($step['completion_tracking']);
                if (!$hastrack) {
                    // CM has no completion tracking — show as visited, not completed.
                    $outcomekey = 'visited';
                } else if ($exhausted) {
                    $outcomekey = 'fail_exhausted';
                } else {
                    $outcomekey = match ($outcome) {
                        2 => 'pass',
                        3 => 'fail',
                        default => 'complete',
                    };
                }
                $journeyrows[] = [
                    'cmname'       => $step['cmname'] ?? '',
                    'cmurl'        => $cmurls[$step['cmid'] ?? 0] ?? '#',
                    'steptime'     => $step['ts'] > 0
                        ? userdate($step['ts'], $dateformat) : '',
                    'outcome_key'  => $outcomekey,
                    'outcome_label' => get_string(
                        'risk_journey_outcome_' . $outcomekey,
                        'local_coursectrl'
                    ),
                    'is_pass'      => $outcomekey === 'pass',
                    'is_fail'      => str_starts_with($outcomekey, 'fail'),
                    'is_visited'   => $outcomekey === 'visited',
                ];
            }

            // Grade scenario badge for journey findings.
            $grademode = $item['grademode'] ?? '';
            $grademodelabel = $grademode !== ''
                ? get_string('risk_journey_scenario_' . $grademode, 'local_coursectrl', null, true)
                : '';

            // For dep_on_hidden all related hidden prereqs must be unhidden.
            // Pass them as cmids[] so fix_action.php can process all in one request.
            $fixtargetcmid = $primarycmid;
            $fixextraparams = [];
            if ($type === 'dep_on_hidden' && !empty($item['related_cmids'])) {
                $fixtargetcmid = (int) reset($item['related_cmids']);
                $fixextraparams = ['cmids' => $item['related_cmids']];
            }
            $rows[] = [
                'type'             => $type,
                'typelabel'        => $typelabel,
                'severity'         => $severity,
                'icon'             => $severityicon[$severity] ?? '⚠️',
                // Boost score when group includes subsection CMs with child activities.
                'score'            => ($item['score'] ?? 0) + ($subsectionchildcount > 0 ? 15 : 0),
                'cmid'             => $primarycmid,
                'cmname'           => $cmname,
                'cmurl'            => $cmurl,
                'modname'          => $modname,
                'problem'          => $problem,
                'hasproblem'       => $problem !== '',
                'action'        => $action,
                'hasaction'     => $action !== '',
                'related'       => $relatedlinked,
                'hasrelated'    => !empty($relatedlinked),
                // Cascade is folded into grouped_cms for group cards.
                'cascade'               => $type === 'journey_unreachable_group' ? [] : $cascadelinked,
                'hascascade'            => $type !== 'journey_unreachable_group' && !empty($cascadelinked),
                'cascade_count'         => $type === 'journey_unreachable_group' ? 0 : count($cascadelinked),
                'grouped_cms'           => $groupedcmslinked,
                'has_grouped_cms'       => !empty($groupedcmslinked),
                // Count direct (non-derived) CMs only for the accordion header.
                'grouped_cms_count'     => count(array_filter($groupedcmslinked, fn ($g) => !$g['is_derived'])),
                'subsection_children'   => $subsectionchildlinked,
                'has_subsection_children' => !empty($subsectionchildlinked),
                'subsection_child_count' => $subsectionchildcount,
                'simurl'        => $simurl,
                'fix_type'      => $this->fix_type_for($type),
                'fix_url'       => $this->fix_url_for(
                    $type,
                    $fixtargetcmid,
                    $cms[$fixtargetcmid] ?? $cm,
                    $courseid,
                    'risks',
                    $fixextraparams
                ),
                'fix_label'     => $this->fix_label_for($type),
                'has_fix'       => $this->fix_type_for($type) !== '',
                'escape_message' => get_string(
                    'checks_escape_' . ($item['escape_type'] ?? 'none'),
                    'local_coursectrl',
                    null,
                    true
                ) ?: '',
                'has_escape'       => !empty($item['has_escape']),
                'journey_steps'    => $journeyrows,
                'hasjourneysteps'  => !empty($journeyrows),
                'grademode_label'  => $grademodelabel,
                'hasgrademode'     => $grademodelabel !== '',
                'completion_block' => !empty($item['completion_block']),
                'affected_scenarios' => (int) ($item['affected_scenarios'] ?? 1),
                'affected_profiles'  => (int) ($item['affected_profiles'] ?? 1),
                'affected_count'     => (int) ($item['affected_count'] ?? 1),
                'hasaffected'        => (($item['affected_count'] ?? 1) > 1 ||
                    ($item['affected_scenarios'] ?? 1) > 1),
                'section_cause'      => !empty($item['section_cause']),
                'section_id'         => (int) ($item['section_id'] ?? 0),
                'section_name'       => $item['section_name'] ?? '',
                'section_num'        => (int) ($item['section_num'] ?? 0),
                'section_edit_url'   => (int) ($item['section_id'] ?? 0) > 0
                    ? (new \moodle_url(
                        '/course/editsection.php',
                        ['id' => (int) ($item['section_id'] ?? 0)]
                    ))->out(false)
                    : '',
                'has_section_edit'   => (int) ($item['section_id'] ?? 0) > 0,
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
        string $dateformat,
        string $modname = ''
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
        if ($type === 'completion_unreachable') {
            $a = new \stdClass();
            $a->failing = $item['message_params']['failing'] ?? 0;
            $a->total   = $item['message_params']['total'] ?? 1;
            return [
                get_string('risk_completion_unreachable', 'local_coursectrl', $a),
                get_string('consistency_action_r1_not_accessible', 'local_coursectrl'),
            ];
        }

        if ($type === 'completion_reachable') {
            $a = new \stdClass();
            $a->profiles = $item['message_params']['profiles'] ?? 1;
            return [
                get_string('risk_completion_reachable', 'local_coursectrl', $a),
                '',
            ];
        }

        if ($type === 'remedial_path_available') {
            return [
                get_string('risk_remedial_path_available', 'local_coursectrl'),
                '',
            ];
        }

        if ($type === 'journey_unreachable_group') {
            $a = new \stdClass();
            $a->count = count($item['cmids'] ?? []);
            return [
                get_string('risk_problem_journey_unreachable_group', 'local_coursectrl', $a),
                get_string('risk_action_journey_unreachable_group', 'local_coursectrl', $a),
            ];
        }

        if ($type === 'journey_unreachable') {
            $grademode = $item['grademode'] ?? 'pass';
            $a->grademode = get_string(
                'risk_journey_scenario_' . $grademode,
                'local_coursectrl',
                null,
                true
            ) ?: $grademode;
            if (!empty($item['section_cause']) && (int)($item['section_id'] ?? 0) > 0) {
                $sectionname = $item['section_name'] ?? '';
                $snum = (int)($item['section_num'] ?? 0);
                $a->section = $sectionname !== '' ? $sectionname
                    : get_string('section') . ' ' . $snum;
                $a->count = 1; // Single CM; count used in shared string.
                return [
                    get_string('risk_problem_journey_section_blocked', 'local_coursectrl', $a),
                    get_string('risk_action_journey_section_blocked', 'local_coursectrl', $a),
                ];
            }
            return [
                get_string('risk_problem_journey_unreachable', 'local_coursectrl', $a),
                get_string('risk_action_journey_unreachable', 'local_coursectrl', $a),
            ];
        }
        if ($type === 'long_dep_chain') {
            return [
                get_string('risk_problem_long_dep_chain', 'local_coursectrl', $a),
                get_string('risk_action_long_dep_chain', 'local_coursectrl'),
            ];
        }
        if ($type === 'r0_after_course_end' || $type === 'r0_before_course_start') {
            $rawfield = (string)($item['field'] ?? '');
            $a->field = $rawfield !== ''
                ? field_label_resolver::resolve($rawfield, $modname, 'cm') : '–';
            $a->date = isset($item['ts_field']) && $item['ts_field'] > 0
                ? userdate((int)$item['ts_field'], $dateformat) : '–';
            $a->boundary = isset($item['ts_boundary']) && $item['ts_boundary'] > 0
                ? userdate((int)$item['ts_boundary'], $dateformat) : '–';
            $key = $type === 'r0_after_course_end'
                ? 'risk_problem_r0_after_course_end'
                : 'risk_problem_r0_before_course_start';
            return [
                get_string($key, 'local_coursectrl', $a),
                get_string('risk_action_r0_date', 'local_coursectrl'),
            ];
        }
        if ($type === 'r0_deadline_in_past') {
            $rawfield = (string)($item['field'] ?? '');
            $a->field = $rawfield !== ''
                ? field_label_resolver::resolve($rawfield, $modname, 'cm') : '–';
            $a->date = isset($item['ts_field']) && $item['ts_field'] > 0
                ? userdate((int)$item['ts_field'], $dateformat) : '–';
            return [
                get_string('risk_problem_r0_deadline_in_past', 'local_coursectrl', $a),
                get_string('risk_action_r0_date', 'local_coursectrl'),
            ];
        }
        if ($type === 'r1_hidden') {
            return [
                get_string('risk_problem_r1_hidden', 'local_coursectrl', $a),
                get_string('risk_action_r1_hidden', 'local_coursectrl'),
            ];
        }
        if ($type === 'r1_not_accessible') {
            return [
                get_string('risk_problem_r1_not_accessible', 'local_coursectrl', $a),
                get_string('risk_action_r1_not_accessible', 'local_coursectrl'),
            ];
        }
        if ($type === 'completionexpected_window') {
            $tsexpected = (int)($item['ts_completionexpected'] ?? 0);
            $tsdeadline = (int)($item['ts_deadline'] ?? 0);
            $rawdeadlinefield = (string)($item['field_deadline'] ?? '');
            $a->date_expected = $tsexpected > 0 ? userdate($tsexpected, $dateformat) : '–';
            $a->date_start = $tsdeadline > 0 ? userdate($tsdeadline, $dateformat) : '–';
            $a->date_end = $rawdeadlinefield !== ''
                ? field_label_resolver::resolve($rawdeadlinefield, $modname, 'cm') : '–';
            return [
                get_string('risk_problem_completionexpected_window', 'local_coursectrl', $a),
                get_string('risk_action_completionexpected_window', 'local_coursectrl'),
            ];
        }
        if ($type === 'dangling_dep' || $type === 'dangling_group' || $type === 'dangling_grouping') {
            return [
                get_string('risk_problem_dangling_dep', 'local_coursectrl', $a),
                get_string('risk_action_dangling_dep', 'local_coursectrl'),
            ];
        }
        if ($type === 'impossible_dep') {
            return [
                get_string('risk_problem_impossible_dep', 'local_coursectrl', $a),
                get_string('risk_action_impossible_dep', 'local_coursectrl'),
            ];
        }
        if ($type === 'date_coupling') {
            $a->field_early = $item['field_early'] ?? '–';
            $a->field_late = $item['field_late'] ?? '–';
            return [
                get_string('risk_problem_date_coupling', 'local_coursectrl', $a),
                get_string('risk_action_date_coupling', 'local_coursectrl'),
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
            'dep_on_hidden'                   => 'unhide_cm',
            'hidden_with_dependents'          => 'unhide_cm',
            'r1_hidden'                       => 'unhide_cm',
            'dangling_dep'                    => 'modedit_availability',
            'impossible_dep'                  => 'modedit_availability',
            'dangling_group'                  => 'modedit_availability',
            'dangling_grouping'               => 'modedit_availability',
            'completion_required_no_tracking' => 'modedit_completion',
            'completion_no_tracking'          => 'modedit_completion',
            'temporal_conflict'               => 'timeline',
            'date_coupling'                   => 'timeline',
            'r0_after_course_end'             => 'timeline',
            'r0_before_course_start'          => 'timeline',
            'r0_deadline_in_past'             => 'timeline',
            'deadline_before_dep_window'      => 'timeline',
            'circular_dep'                    => 'dependencies',
            'circular_dep_transitive'         => 'dependencies',
            'long_dep_chain'                  => 'dependencies',
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
    private function fix_url_for(string $type, int $cmid, $cm, int $courseid, string $tab, array $extraparams = []): string {
        $fixtype = $this->fix_type_for($type);
        if ($fixtype === '') {
            return '';
        }
        if ($fixtype === 'unhide_cm') {
            $urlparams = [
                'courseid' => $courseid,
                'action'   => 'unhide_cm',
                'tab'      => $tab,
                'sesskey'  => sesskey(),
            ];
            // When multiple prereq cmids exist (dep_on_hidden), encode them all.
            // Moodle_url does not support array-style params, so append manually.
            if (!empty($extraparams['cmids'])) {
                $basestr = (new \moodle_url('/local/coursectrl/fix_action.php', $urlparams))->out(false);
                foreach ($extraparams['cmids'] as $ecmid) {
                    $basestr .= '&cmids%5B%5D=' . (int)$ecmid;
                }
                return $basestr;
            }
            $urlparams['cmid'] = $cmid;
            return (new \moodle_url('/local/coursectrl/fix_action.php', $urlparams))->out(false);
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
        if ($fixtype === 'modedit_completion') {
            return (new \moodle_url(
                '/course/modedit.php',
                ['update' => $cmid, 'return' => 1]
            ))->out(false) . '#id_completion';
        }
        if ($fixtype === 'dependencies') {
            return (new \moodle_url(
                '/local/coursectrl/dependencies.php',
                ['courseid' => $courseid]
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
        // Render simulation template as pre-rendered HTML for checks.mustache.
        $html = $renderer->render_simulation_page($simpage);
        return ['simulationhtml' => $html];
    }
}
