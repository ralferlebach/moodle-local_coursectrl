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
 * Only adapter-sourced entries (source === 'adapter') are considered for
 * ordering conflicts. The completionexpected field from course_modules is
 * evaluated separately to produce notice-level hints.
 *
 * Severity levels:
 *   error  — logically invalid ordering that Moodle itself would reject.
 *   warning — ordering that is unusual and likely unintentional.
 *   notice  — advisory hint, e.g. completionexpected far before the due date.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;

/**
 * Detects date-ordering conflicts and advisory notices within course modules.
 */
class temporal_conflict_detector {
    /**
     * Number of seconds in one week, used for completionexpected notice threshold.
     *
     * @var int
     */
    private const WEEK_SECONDS = 604800;

    /**
     * Ordering rules per component.
     *
     * Each rule is a two-element array [earlier_field, later_field], meaning
     * earlier_field must have a timestamp <= later_field. Both fields must be
     * set (> 0) for the rule to be evaluated; if either is zero the module has
     * intentionally left that field unset and no conflict is raised.
     *
     * @var array<string, array<int, string[]>>
     */
    private const RULES = [
        'mod_assign' => [
            ['allowsubmissionsfromdate', 'duedate'],
            ['duedate', 'cutoffdate'],
            ['duedate', 'gradingduedate'],
        ],
        'mod_choice' => [
            ['timeopen', 'timeclose'],
        ],
        'mod_feedback' => [
            ['timeopen', 'timeclose'],
        ],
        'mod_forum' => [
            // No enforced ordering between cutoffdate and duedate in Moodle.
            ['assesstimestart', 'assesstimefinish'],
        ],
        'mod_glossary' => [
            ['assesstimestart', 'assesstimefinish'],
        ],
        'mod_lesson' => [
            ['available', 'deadline'],
        ],
        'mod_quiz' => [
            ['timeopen', 'timeclose'],
        ],
        'mod_scorm' => [
            ['timeopen', 'timeclose'],
        ],
        'mod_workshop' => [
            ['submissionstart', 'submissionend'],
            ['assessmentstart', 'assessmentend'],
            ['submissionend', 'assessmentstart'],
        ],
        'mod_choicegroup' => [
            ['timeopen', 'timeclose'],
        ],
        'mod_questionnaire' => [
            ['opendate', 'closedate'],
        ],
        'mod_studentquiz' => [
            ['opensubmissionfrom', 'closesubmissionfrom'],
            ['openansweringfrom', 'closeansweringfrom'],
        ],
        // CAPQuiz has a single timedue field; no ordering rule applicable.
    ];

    /**
     * The primary deadline field per component, used for completionexpected checks.
     *
     * When a CM has a completionexpected timestamp and this deadline field is set,
     * the detector checks whether completionexpected is more than one week before
     * the deadline (notice) or after the deadline (warning).
     *
     * @var array<string, string>
     */
    private const DEADLINE_FIELD = [
        'mod_assign'      => 'duedate',
        'mod_choice'      => 'timeclose',
        'mod_feedback'    => 'timeclose',
        'mod_forum'       => 'duedate',
        'mod_glossary'    => 'assesstimefinish',
        'mod_lesson'      => 'deadline',
        'mod_quiz'        => 'timeclose',
        'mod_scorm'       => 'timeclose',
        'mod_workshop'    => 'assessmentend',
        'mod_capquiz'     => 'timedue',
        'mod_choicegroup' => 'timeclose',
        'mod_questionnaire' => 'closedate',
        'mod_studentquiz' => 'closeansweringfrom',
    ];

    /**
     * Detect temporal conflicts and advisory notices for a set of CMs.
     *
     * @param cm_item[] $cms       Course modules keyed by cmid.
     * @param array     $datesbycm Per-CM date entries from date_collector::collect_grouped_by_cm().
     * @return array<int, array[]> cmid → list of conflict/notice arrays.
     */
    public function detect(array $cms, array $datesbycm): array {
        $result = [];
        foreach ($cms as $cm) {
            $component = $cm->get_component();
            $fieldmap = $this->build_field_map($datesbycm[$cm->id] ?? []);
            $issues = [];

            // Ordering conflict rules.
            if (array_key_exists($component, self::RULES)) {
                $issues = array_merge(
                    $issues,
                    $this->apply_rules(self::RULES[$component], $fieldmap)
                );
            }

            // completionexpected advisory checks.
            $compexp = $cm->completionexpected;
            if ($compexp > 0 && array_key_exists($component, self::DEADLINE_FIELD)) {
                $deadlinefield = self::DEADLINE_FIELD[$component];
                $deadline = $fieldmap[$deadlinefield] ?? 0;
                if ($deadline > 0) {
                    if ($compexp > $deadline) {
                        // Completion expected after the module deadline is a warning.
                        $issues[] = [
                            'field_early'  => $deadlinefield,
                            'field_late'   => 'completionexpected',
                            'ts_early'     => $deadline,
                            'ts_late'      => $compexp,
                            'severity'     => 'warning',
                            'type_override' => 'completionexpected_after_deadline',
                        ];
                    } else if (($deadline - $compexp) > self::WEEK_SECONDS) {
                        // Completion expected more than one week before deadline is a notice.
                        $issues[] = [
                            'field_early'  => 'completionexpected',
                            'field_late'   => $deadlinefield,
                            'ts_early'     => $compexp,
                            'ts_late'      => $deadline,
                            'severity'     => 'notice',
                            'type_override' => 'completionexpected_early',
                        ];
                    }
                }
            }

            if (!empty($issues)) {
                $result[$cm->id] = $issues;
            }
        }
        return $result;
    }

    /**
     * Build a field-name → timestamp lookup from adapter-sourced date entries.
     *
     * @param array $entries Date entries for a single CM.
     * @return array<string, int> Field name → unix timestamp.
     */
    private function build_field_map(array $entries): array {
        $map = [];
        foreach ($entries as $entry) {
            if (($entry['source'] ?? '') === 'adapter') {
                $map[$entry['field']] = (int) $entry['timestamp'];
            }
        }
        return $map;
    }

    /**
     * Apply ordering rules against a field map.
     *
     * @param array<int, string[]> $rules    Rules for this component.
     * @param array<string, int>   $fieldmap Field → timestamp.
     * @return array[] List of conflict arrays with severity 'error'.
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
}
