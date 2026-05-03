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
        global $DB;
        $course = $this->snapshot->course;
        $cms = $this->snapshot->cms;
        $courseid = $course->id;
        $dateformat = get_string('strftimedaydatetime', 'core_langconfig');
        $now = time();

        // Build grade-item map: gradeitemid → {cmid, grademax, gradepass}.
        // Used by condition_evaluator to resolve grade availability conditions.
        $gradeitemmap = [];
        $gradeinfobycmid = [];
        $sql = "SELECT gi.id, gi.iteminstance, gi.grademax, gi.gradepass, gi.itemmodule,
                       cm.id AS cmid
                  FROM {grade_items} gi
                  JOIN {modules} m ON m.name = gi.itemmodule
                  JOIN {course_modules} cm ON cm.module = m.id
                                          AND cm.instance = gi.iteminstance
                                          AND cm.course = gi.courseid
                 WHERE gi.courseid = :courseid AND gi.itemtype = 'mod'";
        $rows = $DB->get_records_sql($sql, ['courseid' => (int) $courseid]);
        foreach ($rows as $row) {
            $gradeitemmap[(int) $row->id] = [
                'cmid'      => (int) $row->cmid,
                'grademax'  => (float) ($row->grademax ?? 100.0),
                'gradepass' => (float) ($row->gradepass ?? 0.0),
            ];
            $gradeinfobycmid[(int) $row->cmid] = [
                'gradeitemid' => (int) $row->id,
                'grademax'    => (float) ($row->grademax ?? 100.0),
                'gradepass'   => (float) ($row->gradepass ?? 0.0),
            ];
        }

        // Pre-render tooltip strings once (cannot use {{#str}} inside HTML attributes).
        $lbl = [
            'completed'    => get_string('sim_label_completed', 'local_coursectrl'),
            'passed'       => get_string('sim_label_passed', 'local_coursectrl'),
            'grade'        => get_string('sim_label_grade_pct', 'local_coursectrl'),
            'no_complete'  => get_string('sim_col_nocompletion', 'local_coursectrl'),
            'no_passed'    => get_string('sim_col_nopassgrade', 'local_coursectrl'),
            'no_grade'     => get_string('sim_col_nograde', 'local_coursectrl'),
        ];

        // Form defaults: per-CM activity state rows.
        $cmformrows = [];
        foreach ($cms as $cm) {
            $assumed = $this->state ? $this->state->get_completion($cm->id) : 0;
            $gradeinfo = $gradeinfobycmid[$cm->id] ?? null;
            $hasgrade = $gradeinfo !== null;
            $grademax = $hasgrade ? (float) ($gradeinfo['grademax'] ?? 100.0) : 0.0;
            $gradepass = $hasgrade ? (float) ($gradeinfo['gradepass'] ?? 0.0) : 0.0;
            $haspassgrade = $hasgrade && $gradepass > 0.0;
            $gradeable = $hasgrade && $grademax > 0.0;
            $hascompletion = $cm->completion > 0;

            // Exclude activities with nothing to control.
            if (!$hascompletion && !$hasgrade) {
                continue;
            }

            $assumedgrade = $this->state ? $this->state->get_grade($cm->id) : null;
            $assumedgradestr = $assumedgrade !== null ? number_format($assumedgrade, 1) : '';

            // Completion column: enabled (active) or disabled (grayed, not configured).
            $completionenabled  = $hascompletion;
            $completiondisabled = !$hascompletion && $hasgrade;

            // Passed column: visible only when grade item exists.
            $passgradevisible  = $hasgrade;
            $passgradeenabled  = $haspassgrade;
            $passgradadisabled = $hasgrade && !$haspassgrade;

            // Grade column: enabled when grade item has a max grade; disabled when grademax=0.
            $gradevisible  = $hasgrade;
            $gradeenabled  = $gradeable;
            $gradedisabled = $hasgrade && !$gradeable;

            $cmformrows[] = [
                'cmid'    => $cm->id,
                'cmname'  => $cm->name,
                'modname' => $cm->modname,
                // Completion.
                'completion_enabled'  => $completionenabled,
                'completion_disabled' => $completiondisabled,
                // Passed.
                'passgrade_visible'   => $passgradevisible,
                'passgrade_enabled'   => $passgradeenabled,
                'passgrade_disabled'  => $passgradadisabled,
                // Grade.
                'grade_visible'       => $gradevisible,
                'grade_enabled'       => $gradeenabled,
                'grade_disabled'      => $gradedisabled,
                // Grade threshold as percentage for JS grade/pass sync.
                'gradepass_pct'       => ($grademax > 0.0 && $gradepass > 0.0)
                    ? round($gradepass / $grademax * 100.0, 1) : 0.0,
                // Assumed values.
                'assumed_complete'    => $assumed >= 1,
                'assumed_passed'      => $assumed === 2,
                'assumed_grade'       => $assumedgradestr,
                // Tooltip strings (cannot be rendered from inside Mustache attribute).
                'lbl_completed'       => $lbl['completed'],
                'lbl_passed'          => $lbl['passed'],
                'lbl_grade'           => $lbl['grade'],
                'lbl_no_complete'     => $lbl['no_complete'],
                'lbl_no_passed'       => $lbl['no_passed'],
                'lbl_no_grade'        => $lbl['no_grade'],
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
        // Build a plain id->name map for group condition labels.
        $groupnamemap = [];
        foreach (groups_get_all_groups($courseid) as $g) {
            $groupnamemap[(int) $g->id] = $g->name;
        }
        $groupingnamemap = [];
        foreach (groups_get_all_groupings($courseid) as $gg) {
            $groupingnamemap[(int) $gg->id] = $gg->name;
        }
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
        $blockedids = [];
        $nextstepids = [];

        if ($hasresults) {
            $evaluator = new condition_evaluator($gradeitemmap);
            // Pass sections so visibility_simulator can gate CMs by section availability.
            $sections = $snapshot->sections ?? [];
            $simulator = new visibility_simulator($evaluator, $sections);
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
                    $reasonrows[] = $this->format_reason(
                        $reason,
                        $dateformat,
                        $courseid,
                        $cms,
                        $groupnamemap,
                        $groupingnamemap
                    );
                }
                // Build OR/AND grouped reasons for structured display.
                $rawgroups = $evaluator->evaluate_groups(
                    $cms[$cmid]->availability ?? null,
                    $this->state
                );
                $reasongrouprows = [];
                foreach ($rawgroups as $gidx => $grp) {
                    $gconditions = [];
                    foreach ($grp as $r) {
                        $gconditions[] = $this->format_reason(
                            $r,
                            $dateformat,
                            $courseid,
                            $cms,
                            $groupnamemap,
                            $groupingnamemap
                        );
                    }
                    $reasongrouprows[] = [
                        'conditions'    => $gconditions,
                        'has_or_before' => $gidx > 0,
                    ];
                }
                // Fallback: teacher_hidden / section_blocked come from the simulator,
                // not from availability JSON, so evaluate_groups may return nothing.
                // Wrap flat reasons in a single group for display.
                if (empty($reasongrouprows) && !empty($reasonrows)) {
                    $reasongrouprows = [['conditions' => $reasonrows, 'has_or_before' => false]];
                }

                $iscomplete = $this->state !== null
                    && $this->state->get_completion($cmid) > 0;

                $cmobj = $cms[$cmid] ?? null;
                $hascompletiontracking = $cmobj !== null && ($cmobj->completion ?? 0) > 0;
                $cmvisible = $cmobj !== null && ($cmobj->visible ?? true);
                // Hidden CMs cannot be completed by learners — override iscomplete.
                $effectivecomplete = $cmvisible ? $iscomplete : false;
                // Assumed completion state: use actual form-submitted state, not access result.
                // COMPLETION_COMPLETE_PASS=2, FAIL=3, COMPLETE=1, INCOMPLETE=0.
                $completionstate = $this->state ? $this->state->get_completion($cmid) : 0;
                $haspassgrade = isset($gradeinfobycmid[$cmid]) && ($gradeinfobycmid[$cmid]['gradepass'] ?? 0) > 0;
                $resultrows[] = [
                    'cmid'    => $cmid,
                    'name'    => $result['name'],
                    'modname' => $result['modname'],
                    'accessible'      => $accessible,
                    'teacher_visible' => $cmvisible,
                    'status'          => $result['status'],
                    // Tracking column.
                    'tracking_active'  => $hascompletiontracking && $cmvisible,
                    // Show 'hidden' for any hidden CM, regardless of tracking config.
                    'tracking_hidden'  => !$cmvisible,
                    'tracking_off'     => !$hascompletiontracking && $cmvisible,
                    // Assumed completion: from learner state, not access evaluation.
                    // Hidden CMs cannot be completed, so show blank.
                    'has_completion_tracking' => $hascompletiontracking && $cmvisible,
                    'status_pass'    => $cmvisible && $hascompletiontracking
                        && ($completionstate === 2
                            || ($effectivecomplete && !$haspassgrade)),
                    'status_fail'    => $cmvisible && $hascompletiontracking
                        && $haspassgrade && $completionstate === 3,
                    'status_unknown' => $cmvisible && $hascompletiontracking
                        && $completionstate === 0 && !$effectivecomplete,
                    'has_restrictions' => $result['has_restrictions'],
                    'isnextstep' => $isnextstep,
                    'isblocked'  => $isblocked,
                    'iscomplete' => $effectivecomplete,
                    'reasons'      => $reasonrows,
                    'hasreasons'   => count($reasonrows) > 0,
                    'reason_groups' => $reasongrouprows,
                    'url' => (new \moodle_url(
                        '/mod/' . $result['modname'] . '/view.php',
                        ['id' => $cmid]
                    ))->out(false),
                ];
            }
        }

        // Build section-structured result groups for the template.
        // This organises resultrows by section (and subsections) in course order,
        // With locale-aware section names via get_section_name().
        $sectionresultgroups = [];
        if ($hasresults) {
            // Index resultrows by cmid for quick lookup.
            $resultbycmid = [];
            foreach ($resultrows as $row) {
                $resultbycmid[(int) $row['cmid']] = $row;
            }
            // Build section → child section map.
            // Strategy 1: DB component/itemid.
            // Strategy 2: cm_info->delegatesection (Moodle 4.4+).
            // Strategy 3: section name matches CM name (fallback for NULL component).
            $childsectionbysubcmid = [];
            $sections = $this->snapshot->sections ?? [];
            $subsecrows = $DB->get_records_select(
                'course_sections',
                "course = ? AND component = 'mod_subsection' AND itemid > 0",
                [$courseid],
                '',
                'id,itemid'
            );
            foreach ($subsecrows as $row) {
                $sec = $sections[(int) $row->id] ?? null;
                if ($sec !== null) {
                    $childsectionbysubcmid[(int) $row->itemid] = $sec;
                }
            }
            $sectionnamesbyid = [];
            try {
                // Get_section_name() requires a full stdClass DB course object with ->format;
                // course_item does not carry ->format, so fetch the real record here.
                $dbcourse = get_course($courseid);
                $modinfo = get_fast_modinfo($courseid);
                foreach ($modinfo->get_section_info_all() as $sinfo) {
                    $sectionnamesbyid[(int) $sinfo->id] = get_section_name($dbcourse, $sinfo);
                }
                // Strategy 2 + 3: per subsection CM.
                foreach ($modinfo->get_cms() as $cminfo) {
                    if ($cminfo->modname !== 'subsection') {
                        continue;
                    }
                    $cmid = (int) $cminfo->id;
                    if (isset($childsectionbysubcmid[$cmid])) {
                        continue; // Strategy 1 already resolved.
                    }
                    // Strategy 2: delegatesection property (Moodle 4.4+).
                    if (isset($cminfo->delegatesection) && $cminfo->delegatesection !== null) {
                        $dsid = (int) $cminfo->delegatesection->id;
                        $sec = $sections[$dsid] ?? null;
                        if ($sec !== null) {
                            $childsectionbysubcmid[$cmid] = $sec;
                        }
                        continue;
                    }
                    // Strategy 3: match section by name.
                    $cmname = (string) $cminfo->name;
                    foreach ($sections as $sec) {
                        if ((string) $sec->name === $cmname) {
                            $childsectionbysubcmid[$cmid] = $sec;
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
                debugging('local_coursectrl: subsection map failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
            // Walk sections in section-number order.
            $sortedsections = $sections;
            usort($sortedsections, fn ($a, $b) => $a->sectionnum <=> $b->sectionnum);
            // Track which sections are child subsection sections (skip as top-level).
            $subsectionsectionids = [];
            foreach ($childsectionbysubcmid as $sec) {
                $subsectionsectionids[$sec->id] = true;
            }
            foreach ($sortedsections as $section) {
                if (isset($subsectionsectionids[$section->id])) {
                    continue; // Rendered inline under the subsection CM.
                }
                $sectionname = $sectionnamesbyid[$section->id]
                    ?? ($section->name ?? get_string('section') . ' ' . $section->sectionnum);
                $sectionrows = [];
                // Walk CMs in this section in course sequence order.
                $sectioncmids = array_filter(
                    array_keys($cms),
                    fn ($cid) => (int) ($cms[$cid]->sectionid) === (int) $section->id
                );
                foreach ($sectioncmids as $cmid) {
                    $row = $resultbycmid[$cmid] ?? null;
                    if ($row === null) {
                        continue;
                    }
                    $cmobj = $cms[$cmid] ?? null;
                    $issubsection = $cmobj !== null && $cmobj->modname === 'subsection';
                    if ($issubsection) {
                        // Inline child section as a subsection group.
                        $childsec = $childsectionbysubcmid[$cmid] ?? null;
                        $childrows = [];
                        if ($childsec !== null) {
                            $childcmids = array_filter(
                                array_keys($cms),
                                fn ($cid) => (int) ($cms[$cid]->sectionid) === (int) $childsec->id
                            );
                            foreach ($childcmids as $childcmid) {
                                $childrow = $resultbycmid[$childcmid] ?? null;
                                if ($childrow !== null) {
                                    $childrow['depth'] = 2;
                                    // Subsection parent cmid for JS toggle target.
                                    $childrow['subcmid'] = $cmid;
                                    $childrows[] = $childrow;
                                }
                            }
                        }
                        $row['is_subsection_header'] = true;
                        $row['subsection_rows'] = $childrows;
                        $row['has_subsection_rows'] = !empty($childrows);
                        $row['depth'] = 1;
                    } else {
                        $row['is_subsection_header'] = false;
                        $row['subsection_rows'] = [];
                        $row['has_subsection_rows'] = false;
                        $row['depth'] = 1;
                    }
                    $sectionrows[] = $row;
                }
                // Evaluate section's own availability for the current learner state.
                $secaccessible = true;
                $secreasonrows = [];
                $secreasongroups = [];
                if ($hasresults && $section->availability) {
                    $evaluator = $evaluator ?? new condition_evaluator($gradeitemmap);
                    $seceval = $evaluator->evaluate($section->availability, $this->state);
                    $secaccessible = $seceval['accessible'];
                    foreach ($seceval['reasons'] as $reason) {
                        $secreasonrows[] = $this->format_reason(
                            $reason,
                            $dateformat,
                            $courseid,
                            $cms,
                            $groupnamemap,
                            $groupingnamemap
                        );
                    }
                    $secrawgroups = $evaluator->evaluate_groups(
                        $section->availability ?? null,
                        $this->state
                    );
                    $secreasongroups = [];
                    foreach ($secrawgroups as $sgidx => $sgrp) {
                        $sgconditions = [];
                        foreach ($sgrp as $r) {
                            $sgconditions[] = $this->format_reason(
                                $r,
                                $dateformat,
                                $courseid,
                                $cms,
                                $groupnamemap,
                                $groupingnamemap
                            );
                        }
                        $secreasongroups[] = [
                            'conditions'    => $sgconditions,
                            'has_or_before' => $sgidx > 0,
                        ];
                    }
                }
                // If a subsection header row is itself blocked, its child rows
                // are also effectively inaccessible — propagate regardless of
                // whether the parent section is blocked.
                foreach ($sectionrows as &$subsectcheckrow) {
                    if (
                        !empty($subsectcheckrow['is_subsection_header'])
                        && !$subsectcheckrow['accessible']
                        && !empty($subsectcheckrow['subsection_rows'])
                    ) {
                        $subseclabel = get_string(
                            'sim_reason_section_blocked',
                            'local_coursectrl',
                            $subsectcheckrow['name'] ?? ''
                        );
                        $subsecreason = ['label' => $subseclabel];
                        foreach ($subsectcheckrow['subsection_rows'] as &$subchild) {
                            if ($subchild['accessible']) {
                                $subchild['accessible'] = false;
                                $subchild['isblocked'] = true;
                                $subchild['isnextstep'] = false;
                                array_unshift($subchild['reasons'], $subsecreason);
                                $subchild['hasreasons'] = true;
                            }
                            // Sync reason_groups.
                            if (!empty($subchild['reason_groups'])) {
                                array_unshift($subchild['reason_groups'][0]['conditions'], $subsecreason);
                            } else {
                                $subchild['reason_groups'] = [
                                    ['conditions' => [$subsecreason], 'has_or_before' => false],
                                ];
                            }
                        }
                        unset($subchild);
                    }
                }
                unset($subsectcheckrow);
                // If section is blocked, all CMs inside are effectively blocked too.
                // Override activity accessibility for display purposes.
                if (!$secaccessible && !empty($sectionrows)) {
                    $seclabel = get_string(
                        'sim_reason_section_blocked',
                        'local_coursectrl',
                        $sectionname
                    );
                    $secreason = ['label' => $seclabel];
                    foreach ($sectionrows as &$sectrow) {
                        // Override top-level row.
                        if ($sectrow['accessible']) {
                            $sectrow['accessible'] = false;
                            $sectrow['isblocked'] = true;
                            $sectrow['isnextstep'] = false;
                            array_unshift($sectrow['reasons'], $secreason);
                            $sectrow['hasreasons'] = true;
                        }
                        // Sync reason_groups: prepend section reason as new first condition.
                        if (!empty($sectrow['reason_groups'])) {
                            array_unshift($sectrow['reason_groups'][0]['conditions'], $secreason);
                        } else {
                            $sectrow['reason_groups'] = [['conditions' => [$secreason], 'has_or_before' => false]];
                        }
                        // Override subsection child rows.
                        if (!empty($sectrow['subsection_rows'])) {
                            foreach ($sectrow['subsection_rows'] as &$childrow) {
                                if ($childrow['accessible']) {
                                    $childrow['accessible'] = false;
                                    $childrow['isblocked'] = true;
                                    $childrow['isnextstep'] = false;
                                    array_unshift($childrow['reasons'], $secreason);
                                    $childrow['hasreasons'] = true;
                                }
                            }
                            unset($childrow);
                        }
                    }
                    unset($sectrow);
                }
                $sectionurl = (new \moodle_url(
                    '/course/editsection.php',
                    ['id' => $section->id, 'sr' => 1]
                ))->out(false);
                $sectionresultgroups[] = [
                    'id'               => $section->id,
                    'collapse_id'      => 'ccsec' . $section->id,
                    'name'             => $sectionname,
                    'sectionurl'       => $sectionurl,
                    'sectionnum'       => $section->sectionnum,
                    'rows'             => $sectionrows,
                    'hasrows'          => !empty($sectionrows),
                    'sec_accessible'   => $secaccessible,
                    'sec_blocked'      => !$secaccessible,
                    'sec_has_avail'    => !empty($section->availability),
                    'sec_reasons'        => $secreasonrows,
                    'sec_has_reasons'    => !empty($secreasonrows),
                    'sec_reason_groups'  => $secreasongroups ?? [],
                ];
            }
        }

        // Re-derive nextsteprows and blockedrows AFTER the section loop so that
        // section-blocking propagation (isblocked=true, isnextstep=false applied
        // to $sectionrows) is reflected.  Also build nextstepformrows for the
        // quick re-run form below the next-step list.
        $cmformindex = [];
        foreach ($cmformrows as $formrow) {
            $cmformindex[(int) $formrow['cmid']] = $formrow;
        }
        $nextstepformrows = [];
        $seenblocked = [];
        if ($hasresults) {
            foreach ($sectionresultgroups as $sgroup) {
                foreach ($sgroup['rows'] as $srow) {
                    $rowcmid = (int) ($srow['cmid'] ?? 0);
                    // Collect next-step and blocked from top-level row.
                    if (!empty($srow['is_subsection_header'])) {
                        // Subsection header: check blocked only.
                        if (
                            $srow['isblocked'] && !isset($seenblocked[$rowcmid])
                            && ($srow['teacher_visible'] ?? true)
                        ) {
                            $seenblocked[$rowcmid] = true;
                            $blockedcount++;
                            $blockedrows[] = [
                                'cmid'    => $rowcmid,
                                'name'    => $cms[$rowcmid]->name ?? 'cmid ' . $rowcmid,
                                'cmname'  => $cms[$rowcmid]->name ?? 'cmid ' . $rowcmid,
                                'modname' => $cms[$rowcmid]->modname ?? '',
                                'cmurl'   => (new \moodle_url(
                                    '/mod/' . ($cms[$rowcmid]->modname ?? 'assign') . '/view.php',
                                    ['id' => $rowcmid]
                                ))->out(false),
                            ];
                        }
                        // Check subsection children.
                        foreach ($srow['subsection_rows'] ?? [] as $subrow) {
                            $subcmid = (int) ($subrow['cmid'] ?? 0);
                            if ($subrow['isnextstep']) {
                                $nextsteprows[] = $this->build_nextstep_row(
                                    $subcmid,
                                    $cms,
                                    $cmformindex
                                );
                                if (isset($cmformindex[$subcmid])) {
                                    $nextstepformrows[] = $cmformindex[$subcmid];
                                }
                            }
                            if (
                                $subrow['isblocked'] && !isset($seenblocked[$subcmid])
                                && ($subrow['teacher_visible'] ?? true)
                            ) {
                                $seenblocked[$subcmid] = true;
                                $blockedcount++;
                                $blockedrows[] = [
                                    'cmid'    => $subcmid,
                                    'name'    => $cms[$subcmid]->name ?? 'cmid ' . $subcmid,
                                    'cmname'  => $cms[$subcmid]->name ?? 'cmid ' . $subcmid,
                                    'modname' => $cms[$subcmid]->modname ?? '',
                                    'cmurl'   => (new \moodle_url(
                                        '/mod/' . ($cms[$subcmid]->modname ?? 'assign') . '/view.php',
                                        ['id' => $subcmid]
                                    ))->out(false),
                                ];
                            }
                        }
                        continue;
                    }
                    // Regular CM row.
                    if ($srow['isnextstep']) {
                        $nextsteprows[] = $this->build_nextstep_row($rowcmid, $cms, $cmformindex);
                        if (isset($cmformindex[$rowcmid])) {
                            $nextstepformrows[] = $cmformindex[$rowcmid];
                        }
                    }
                    if (
                        $srow['isblocked'] && !isset($seenblocked[$rowcmid])
                        && ($srow['teacher_visible'] ?? true)
                    ) {
                        $seenblocked[$rowcmid] = true;
                        $blockedcount++;
                        $blockedrows[] = [
                            'cmid'    => $rowcmid,
                            'name'    => $cms[$rowcmid]->name ?? 'cmid ' . $rowcmid,
                            'cmname'  => $cms[$rowcmid]->name ?? 'cmid ' . $rowcmid,
                            'modname' => $cms[$rowcmid]->modname ?? '',
                            'cmurl'   => (new \moodle_url(
                                '/mod/' . ($cms[$rowcmid]->modname ?? 'assign') . '/view.php',
                                ['id' => $rowcmid]
                            ))->out(false),
                        ];
                    }
                }
            }
            $nextstepids = array_column($nextsteprows, 'cmid');
            $blockedids  = array_map(fn($r) => $r['cmid'], $blockedrows);
        }

        return [
            'courseid' => $courseid,
            'coursefullname' => format_string($course->fullname),
            'sesskey' => sesskey(),
            'hasresults' => $hasresults,
            'cmformrows' => $cmformrows,
            'hascmformrows' => count($cmformrows) > 0,
            // Top-level label strings for the table header (cannot use {{#str}} in attributes).
            'sim_lbl_completed' => $lbl['completed'],
            'sim_lbl_passed'    => $lbl['passed'],
            'sim_lbl_grade'     => $lbl['grade'],
            'simdate' => $simdate,
            'simtime' => $simtime,
            'simts' => $simts,
            'coursegroups' => $coursegroups,
            'hascoursegroups' => count($coursegroups) > 0,
            'coursegroupings' => $coursegroupings,
            'hascoursegroupings' => count($coursegroupings) > 0,
            'resultrows' => $resultrows,
            'hasresultrows' => count($resultrows) > 0,
            'sectionresultgroups' => $sectionresultgroups,
            'hassectionresultgroups' => !empty($sectionresultgroups),
            'nextsteprows' => $nextsteprows,
            'hasnextsteps' => count($nextstepformrows) > 0,
            'nextstepcount' => count($nextstepformrows),
            'nextstepformrows' => $nextstepformrows,
            'hasnextstepformrows' => count($nextstepformrows) > 0,
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
                '/local/coursectrl/dependencies.php',
                ['courseid' => $courseid]
            ))->out(false),
            'graphurl_sim' => $this->build_sim_graph_url($courseid, $blockedids, $nextstepids),
            'hasgraphurl_sim' => $this->state !== null,
            'selfurl' => (new \moodle_url(
                '/local/coursectrl/checks.php',
                ['courseid' => $courseid, 'tab' => 'simulation']
            ))->out(false),
        ];
    }

    /**
     * Build the simulation overlay URL for the dependency graph.
     *
     * Array parameters (blockedids, nextstepids, groupids) are appended
     * via moodle_url::param() to keep URL construction fully Moodle-native.
     *
     * @param int   $courseid    Course id.
     * @param int[] $blockedids  CM ids that are blocked in the simulation.
     * @param int[] $nextstepids CM ids that are the next recommended steps.
     * @return string|null URL string, or null if no state is set.
     */
    private function build_sim_graph_url(
        int $courseid,
        array $blockedids,
        array $nextstepids
    ): ?string {
        if (!$this->state) {
            return null;
        }
        $url = new \moodle_url(
            '/local/coursectrl/dependencies.php',
            ['courseid' => $courseid]
        );
        foreach (array_values($blockedids) as $id) {
            $url->param('blockedids[]', (int) $id);
        }
        foreach (array_values($nextstepids) as $id) {
            $url->param('nextstepids[]', (int) $id);
        }
        if (!empty($this->state->groupids)) {
            $url->param('filterbygroup', 1);
            foreach (array_values($this->state->groupids) as $id) {
                $url->param('groupids[]', (int) $id);
            }
        }
        if (empty($blockedids) && empty($nextstepids) && empty($this->state->groupids)) {
            return null;
        }
        return $url->out(false);
    }

    /**
     * Build a nextstep-list row array for the given cmid.
     *
     * @param int   $cmid        Course module id.
     * @param array $cms         CM items keyed by cmid.
     * @param array $formindex   cmformrows indexed by cmid.
     * @return array Template row.
     */
    private function build_nextstep_row(int $cmid, array $cms, array $formindex): array {
        $modname = $cms[$cmid]->modname ?? 'assign';
        $url = (new \moodle_url(
            '/mod/' . $modname . '/view.php',
            ['id' => $cmid]
        ))->out(false);
        return [
            'cmid'    => $cmid,
            'name'    => $cms[$cmid]->name ?? 'cmid ' . $cmid,
            'cmname'  => $cms[$cmid]->name ?? 'cmid ' . $cmid,
            'modname' => $modname,
            'url'     => $url,
            'cmurl'   => $url,
        ];
    }

    /**
     * Format a condition_evaluator reason array for Mustache.
     *
     * @param array $reason Raw reason from condition_evaluator.
     * @param string $dateformat Moodle date format string.
     * @param int $courseid See function signature.
     * @param array $cms See function signature.
     * @param array $groups See function signature.
     * @param array $groupings See function signature.
     * @return array Template-ready reason array.
     */
    private function format_reason(
        array $reason,
        string $dateformat,
        int $courseid,
        array $cms = [],
        array $groups = [],
        array $groupings = []
    ): array {
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
            // Format_string() with the CM context applies activity-specific text
            // filters; s() escapes the result for safe interpolation into the
            // HTML link produced by the sim_reason_completion lang string.
            $depctx = \context_module::instance($depcmid, IGNORE_MISSING);
            $depname = $depcm
                ? s(format_string($depcm->name, true, ['context' => $depctx ?: \context_course::instance($courseid)]))
                : 'cmid ' . (int) $depcmid;
            $depmodname = $depcm ? $depcm->modname : '';
            $depurl = $depcm
                ? (new \moodle_url('/mod/' . $depmodname . '/view.php', ['id' => $depcmid]))->out(false)
                : '';
            // Translate Moodle completion state integer to a readable string.
            // 0 = not complete, 1 = complete, 2 = passed, 3 = failed.
            $expintmap = [
                0 => get_string('sim_completion_none', 'local_coursectrl'),
                1 => get_string('sim_completion_complete', 'local_coursectrl'),
                2 => get_string('sim_completion_pass', 'local_coursectrl'),
                3 => get_string('sim_completion_fail', 'local_coursectrl'),
            ];
            $expstr = $expintmap[(int) ($reason['expected'] ?? 1)] ?? (string) ($reason['expected'] ?? 1);
            $base['label'] = get_string(
                'sim_reason_completion',
                'local_coursectrl',
                (object)['cmname' => $depname, 'cmurl' => $depurl, 'expected' => $expstr]
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
            $gid = (int) ($reason['groupid'] ?? 0);
            $gname = $groups[$gid] ?? null;
            $base['label'] = get_string(
                $gname !== null ? 'sim_reason_group_named' : 'sim_reason_group',
                'local_coursectrl',
                (object)['groupid' => $gid, 'groupname' => $gname !== null ? s($gname) : null]
            );
        } else if ($type === 'grouping') {
            $ggid = (int) ($reason['groupingid'] ?? 0);
            $ggname = $groupings[$ggid] ?? null;
            $base['label'] = get_string(
                $ggname !== null ? 'sim_reason_grouping_named' : 'sim_reason_grouping',
                'local_coursectrl',
                (object)['groupingid' => $ggid, 'groupingname' => $ggname !== null ? s($ggname) : null]
            );
        } else if ($type === 'grade') {
            $gradecmid = (int) ($reason['cmid'] ?? 0);
            $gradecm = $gradecmid ? ($cms[$gradecmid] ?? null) : null;
            if ($gradecm && isset($reason['grade'])) {
                $base['label'] = get_string(
                    'sim_reason_grade_simulated',
                    'local_coursectrl',
                    (object)[
                        'cmname'    => s(format_string(
                            $gradecm->name,
                            true,
                            ['context' => \context_module::instance($gradecmid, IGNORE_MISSING)
                                ?: \context_course::instance($courseid)]
                        )),
                        'grade'     => round((float) $reason['grade'], 1),
                        'direction' => isset($reason['min']) ? '>=' : '<',
                        'threshold' => round((float) ($reason['min'] ?? $reason['max'] ?? 0), 1),
                    ]
                );
            } else if ($gradecm) {
                $dir    = isset($reason['min']) ? '>=' : (isset($reason['max']) ? '<' : '?');
                $thresh = round((float) ($reason['min'] ?? $reason['max'] ?? 0), 1);
                $base['label'] = get_string(
                    'sim_reason_grade_named',
                    'local_coursectrl',
                    (object)['cmname' => s(format_string($gradecm->name)), 'direction' => $dir, 'threshold' => $thresh]
                );
            } else {
                $base['label'] = get_string('sim_reason_grade', 'local_coursectrl');
            }
        } else if ($type === 'teacher_hidden') {
            $base['label'] = get_string('sim_reason_hidden', 'local_coursectrl');
        } else {
            $base['label'] = get_string('sim_reason_unknown', 'local_coursectrl');
        }

        return $base;
    }
}
