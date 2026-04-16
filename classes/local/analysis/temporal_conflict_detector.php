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
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;

/**
 * Detects date-ordering conflicts within course modules.
 */
class temporal_conflict_detector {
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
     * Detect temporal conflicts for a set of CMs.
     *
     * @param cm_item[] $cms      Course modules keyed by cmid.
     * @param array     $datesbycm Per-CM date entries from date_collector::collect_grouped_by_cm().
     *                             Keyed by cmid; each value is an array of date entries with
     *                             at least 'field', 'timestamp', and 'source' keys.
     * @return array<int, array[]> cmid → list of conflict arrays. Each conflict:
     *                             ['field_early' => string, 'field_late' => string,
     *                              'ts_early' => int, 'ts_late' => int]
     */
    public function detect(array $cms, array $datesbycm): array {
        $result = [];
        foreach ($cms as $cm) {
            $component = $cm->get_component();
            if (!array_key_exists($component, self::RULES)) {
                continue;
            }
            $fieldmap = $this->build_field_map($datesbycm[$cm->id] ?? []);
            $conflicts = $this->apply_rules(self::RULES[$component], $fieldmap);
            if (!empty($conflicts)) {
                $result[$cm->id] = $conflicts;
            }
        }
        return $result;
    }

    /**
     * Build a field-name → timestamp lookup from adapter-sourced date entries.
     *
     * @param array $entries Date entries for a single CM.
     * @return array<string, int> field name → unix timestamp.
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
     * @return array[] List of conflict arrays.
     */
    private function apply_rules(array $rules, array $fieldmap): array {
        $conflicts = [];
        foreach ($rules as [$early, $late]) {
            $tsearly = $fieldmap[$early] ?? 0;
            $tslate = $fieldmap[$late] ?? 0;
            if ($tsearly > 0 && $tslate > 0 && $tsearly > $tslate) {
                $conflicts[] = [
                    'field_early' => $early,
                    'field_late' => $late,
                    'ts_early' => $tsearly,
                    'ts_late' => $tslate,
                ];
            }
        }
        return $conflicts;
    }
}
