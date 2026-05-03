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
 * Temporal conflict detector for the Course Control Hub.
 *
 * Checks whether adapter-sourced date fields of a course module violate
 * their expected ordering (e.g. a quiz close time set before its open time).
 * Rules are defined statically per component and applied to the per-CM
 * date map produced by date_collector.
 *
 * Only adapter-sourced entries (source === 'adapter') are considered; the
 * detector does not evaluate availability-date conditions or completionexpected
 * because those are not orderable against each other in a well-defined way.
 *
 * R2 — completionexpected window: the expected-completion reminder should sit
 *       within a reasonable window before the activity's primary deadline.
 *       - completionexpected after primary deadline          → warning
 *       - gap to primary deadline > configured threshold     → notice
 *       - For multi-phase activities (workshop, studentquiz) the notice
 *         threshold applies to the last deadline only.
 *
 * R3 — ordering rules: [early_field, late_field] — early must be ≤ late.
 * R4 — coupling rules: [anchor_field, following_field, min_gap_days] —
 *       following must be ≥ anchor + min_gap_days. A gap of 0 means following
 *       must simply not be before anchor (same as an R3 rule but advisory).
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;

/**
 * Detects date-ordering conflicts and coupling violations within course modules.
 */
class temporal_conflict_detector {
    /**
     * R2: primary deadline field per component (for completionexpected window check).
     *
     * @var array<string, string>
     */
    private const R2_DEADLINE = [
        'mod_assign'       => 'duedate',
        'mod_quiz'         => 'timeclose',
        'mod_feedback'     => 'timeclose',
        'mod_forum'        => 'duedate',
        'mod_lesson'       => 'deadline',
        'mod_workshop'     => 'assessmentend',
        'mod_choice'       => 'timeclose',
        'mod_scorm'        => 'timeclose',
        'mod_capquiz'      => 'timedue',
        'mod_choicegroup'  => 'timeclose',
        'mod_questionnaire' => 'closedate',
        'mod_studentquiz'  => 'closeansweringfrom',
    ];

    /**
     * R2: components whose completionexpected notice threshold is relaxed
     * (multi-phase: workshop, studentquiz).
     *
     * @var string[]
     */
    private const R2_MULTIPHASE = ['mod_workshop', 'mod_studentquiz'];

    /**
     * R3 ordering rules per component.
     *
     * Each rule is [earlier_field, later_field].
     *
     * @var array<string, array<int, string[]>>
     */
    private const RULES = [
        'mod_assign' => [
            ['allowsubmissionsfromdate', 'duedate'],
            ['duedate', 'cutoffdate'],
            ['duedate', 'gradingduedate'],
        ],
        'mod_quiz' => [
            ['timeopen', 'timeclose'],
        ],
        'mod_feedback' => [
            ['timeopen', 'timeclose'],
        ],
        'mod_forum' => [
            // No enforced ordering between cutoffdate and duedate in Moodle.
        ],
        'mod_lesson' => [
            ['available', 'deadline'],
        ],
        'mod_workshop' => [
            ['submissionstart', 'submissionend'],
            ['assessmentstart', 'assessmentend'],
            ['submissionend', 'assessmentstart'],
        ],
        'mod_questionnaire' => [
            ['opendate', 'closedate'],
        ],
        'mod_choicegroup' => [
            ['timeopen', 'timeclose'],
        ],
        'mod_studentquiz' => [
            ['opensubmissionfrom', 'closesubmissionfrom'],
            ['openansweringfrom', 'closeansweringfrom'],
        ],
        // CAPQuiz has a single timedue field; no ordering rule applicable.
    ];

    /**
     * R4 coupling rules per component.
     *
     * Each rule is [anchor_field, following_field]: the following field
     * must be >= anchor + configured minimum gap. A gap of 0 is a soft
     * coupling (following should not precede anchor).
     *
     * @var array<string, array<int, string[]>>
     */
    private const COUPLING_RULES = [
        'mod_assign' => [
            ['duedate', 'cutoffdate'],
            ['cutoffdate', 'gradingduedate'],
        ],
        'mod_workshop' => [
            ['submissionend', 'assessmentend'],
        ],
        'mod_lesson' => [
            ['available', 'deadline'],
        ],
        'mod_quiz' => [
            ['timeopen', 'timeclose'],
        ],
    ];

    /**
     * Detect temporal conflicts for a set of CMs.
     *
     * @param cm_item[] $cms Course modules keyed by cmid.
     * @param array $datesbycm Per-CM date entries from date_collector.
     * @return array<int, array[]> cmid → list of conflict arrays.
     */
    public function detect(array $cms, array $datesbycm): array {
        $r4severity = (string)get_config('local_coursectrl', 'r4_severity');
        if ($r4severity === false || $r4severity === '') {
            $r4severity = 'notice';
        }
        $r4mingapdays = max(0, (int)get_config('local_coursectrl', 'r4_min_gap_days'));
        $r4mingapsecs = $r4mingapdays * DAYSECS;

        $r2warningoffset = 0; // Completionexpected after deadline → always warning.
        $cfgr2 = (int)get_config('local_coursectrl', 'r2_notice_offset_days');
        $r2noticeoffset = ($cfgr2 > 0 ? $cfgr2 : 3) * DAYSECS;

        $result = [];
        foreach ($cms as $cm) {
            $component = $cm->get_component();
            $fieldmap = $this->build_field_map($datesbycm[$cm->id] ?? []);

            // R3: strict ordering violations → error.
            if (array_key_exists($component, self::RULES)) {
                $conflicts = $this->apply_rules(self::RULES[$component], $fieldmap);
                foreach ($conflicts as $conflict) {
                    $result[$cm->id][] = array_merge($conflict, ['issue_class' => 'temporal_conflict']);
                }
            }

            // R4: coupling violations → configurable severity.
            if ($r4severity !== 'off' && array_key_exists($component, self::COUPLING_RULES)) {
                $couplings = $this->apply_coupling_rules(
                    self::COUPLING_RULES[$component],
                    $fieldmap,
                    $r4mingapsecs,
                    $r4severity
                );
                foreach ($couplings as $coupling) {
                    $result[$cm->id][] = array_merge($coupling, ['issue_class' => 'date_coupling']);
                }
            }

            // R2: completionexpected window check.
            if ($cm->completionexpected > 0) {
                $r2issues = $this->check_r2($cm, $fieldmap, $r2warningoffset, $r2noticeoffset);
                foreach ($r2issues as $r2issue) {
                    $result[$cm->id][] = array_merge($r2issue, ['issue_class' => 'completionexpected_window']);
                }
            }
        }
        return $result;
    }

    /**
     * R2: Check whether completionexpected sits within the allowed window.
     *
     * Rules (from rules.md):
     *   - completionexpected > primary deadline                      → warning
     *   - completionexpected < primary deadline, gap > notice_offset → notice
     *     (for multi-phase activities notice is never raised — the multi-phase
     *      span makes large gaps expected)
     *
     * @param cm_item $cm The course module being checked.
     * @param array $fieldmap See function signature.
     * @param int $warningoffset Unused (kept for symmetry; warning fires at 0).
     * @param int $noticeoffset Seconds before deadline that trigger notice.
     * @return array[] Issue arrays (empty if no issue).
     */
    private function check_r2(
        cm_item $cm,
        array $fieldmap,
        int $warningoffset,
        int $noticeoffset
    ): array {
        $component = $cm->get_component();
        $deadlinefield = self::R2_DEADLINE[$component] ?? null;
        if ($deadlinefield === null) {
            return [];
        }
        $deadline = $fieldmap[$deadlinefield] ?? 0;
        if ($deadline <= 0) {
            // No primary deadline set for this CM — R2 does not apply.
            return [];
        }
        $expected = (int) $cm->completionexpected;
        if ($expected <= 0) {
            return [];
        }

        // R2a: completionexpected is after the primary deadline → warning.
        if ($expected > $deadline) {
            return [[
                'type'              => 'completionexpected_after_deadline',
                'severity'          => 'warning',
                'field'             => 'completionexpected',
                'field_deadline'    => $deadlinefield,
                'ts_completionexpected' => $expected,
                'ts_deadline'       => $deadline,
                'message'           => 'completionexpected_after_deadline',
            ]];
        }

        // R2b: completionexpected is more than $noticeoffset before deadline → notice.
        // For multi-phase activities this notice is suppressed.
        if (!in_array($component, self::R2_MULTIPHASE, true)) {
            $gap = $deadline - $expected;
            if ($gap > $noticeoffset) {
                return [[
                    'type'              => 'completionexpected_gap_exceeds_threshold',
                    'severity'          => 'notice',
                    'field'             => 'completionexpected',
                    'field_deadline'    => $deadlinefield,
                    'ts_completionexpected' => $expected,
                    'ts_deadline'       => $deadline,
                    'gap_days'          => (int) round($gap / DAYSECS),
                    'threshold_days'    => (int) round($noticeoffset / DAYSECS),
                    'message'           => 'completionexpected_gap_exceeds_threshold',
                ]];
            }
        }

        return [];
    }

    /**
     * Build a field-name → timestamp lookup from adapter-sourced date entries.
     *
     * @param array $entries Date entries for a single CM.
     * @return array<string, int>
     */
    private function build_field_map(array $entries): array {
        $map = [];
        foreach ($entries as $entry) {
            if (($entry['source'] ?? '') === 'adapter') {
                $map[$entry['field']] = (int)$entry['timestamp'];
            }
        }
        return $map;
    }

    /**
     * Apply R3 ordering rules against a field map.
     *
     * @param array<int,string[]> $rules Ordered rule pairs: [field_a, field_b].
     * @param array<string,int> $fieldmap Field → timestamp map for this CM.
     * @return array[]
     */
    private function apply_rules(array $rules, array $fieldmap): array {
        $conflicts = [];
        foreach ($rules as [$early, $late]) {
            $tsearly = $fieldmap[$early] ?? 0;
            $tslate = $fieldmap[$late] ?? 0;
            if ($tsearly > 0 && $tslate > 0 && $tsearly > $tslate) {
                $conflicts[] = [
                    'field_early' => $early,
                    'field_late'  => $late,
                    'ts_early'    => $tsearly,
                    'ts_late'     => $tslate,
                    'severity'    => 'error',
                ];
            }
        }
        return $conflicts;
    }

    /**
     * Apply R4 coupling rules against a field map.
     *
     * A coupling rule is advisory: [anchor, following] means following
     * should be >= anchor + min_gap_seconds. Only fires when both fields
     * are set and following < anchor + min_gap_seconds.
     *
     * @param array $rules See function signature.
     * @param array $fieldmap See function signature.
     * @param int $mingapsecs Configured minimum gap in seconds.
     * @param string $severity Configured severity ('notice'|'warning').
     * @return array[]
     */
    private function apply_coupling_rules(
        array $rules,
        array $fieldmap,
        int $mingapsecs,
        string $severity
    ): array {
        $couplings = [];
        foreach ($rules as [$anchor, $following]) {
            $tsanchor = $fieldmap[$anchor] ?? 0;
            $tsfollowing = $fieldmap[$following] ?? 0;
            if ($tsanchor > 0 && $tsfollowing > 0) {
                $required = $tsanchor + $mingapsecs;
                if ($tsfollowing < $required) {
                    $couplings[] = [
                        'field_early'  => $anchor,
                        'field_late'   => $following,
                        'ts_early'     => $tsanchor,
                        'ts_late'      => $tsfollowing,
                        'min_gap_days' => (int)($mingapsecs / DAYSECS),
                        'severity'     => $severity,
                    ];
                }
            }
        }
        return $couplings;
    }
}
