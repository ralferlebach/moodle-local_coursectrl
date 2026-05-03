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
 * Course-frame plausibility checker (Rule R0) for the Course Control Hub.
 *
 * Checks whether activity dates fall within the course start/end window,
 * or into the past. These are checked first (R0 = rule zero) because they
 * indicate fundamental scheduling problems regardless of other conditions.
 *
 * R0a: Any activity date after course end               → error
 * R0b: Any activity date before course start            → error
 * R0c: Primary deadline in the past (completion active) → warning
 *
 * R0a and R0b only fire when the course has a defined start or end date.
 * R0c only fires when the CM has completion tracking enabled.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;

/**
 * Checks whether activity dates fall within the course time frame.
 */
class course_frame_checker {
    /**
     * Deadline fields per component — the primary date that represents
     * when learners must complete the activity.
     *
     * @var array<string, string>
     */
    private const DEADLINE_FIELD = [
        'mod_assign'       => 'duedate',
        'mod_choice'       => 'timeclose',
        'mod_feedback'     => 'timeclose',
        'mod_forum'        => 'duedate',
        'mod_glossary'     => 'assesstimefinish',
        'mod_lesson'       => 'deadline',
        'mod_quiz'         => 'timeclose',
        'mod_scorm'        => 'timeclose',
        'mod_workshop'     => 'assessmentend',
        'mod_capquiz'      => 'timedue',
        'mod_choicegroup'  => 'timeclose',
        'mod_questionnaire' => 'closedate',
        'mod_studentquiz'  => 'closeansweringfrom',
    ];

    /**
     * Completion criteria type constant for activity-based criteria.
     * Matches core completion_criteria::COMPLETION_CRITERIA_TYPE_ACTIVITY.
     *
     * @var int
     */
    private const CRITERIA_TYPE_ACTIVITY = 4;

    /**
     * Check all CMs against the course time frame.
     *
     * @param cm_item[] $cms        Course modules keyed by cmid.
     * @param array     $datesbycm  Per-CM date entries from date_collector.
     * @param object    $course     Course record (needs startdate, enddate).
     * @param int[]     $critcmids  Cmids required for course completion.
     *                              When an issue affects one of these, its
     *                              severity is escalated one level:
     *                              notice → warning, warning → error.
     * @return array<int, array[]> cmid → list of R0 issues.
     */
    public function check(array $cms, array $datesbycm, object $course, array $critcmids = []): array {
        $coursestart = (int)($course->startdate ?? 0);
        $courseend = (int)($course->enddate ?? 0);
        $now = time();
        $issues = [];

        foreach ($cms as $cm) {
            $entries = $datesbycm[$cm->id] ?? [];
            if (empty($entries)) {
                continue;
            }

            // R0a: Any date after course end (only when course end is set).
            if ($courseend > 0) {
                foreach ($entries as $entry) {
                    $ts = (int)($entry['timestamp'] ?? 0);
                    if ($ts > 0 && $ts > $courseend) {
                        $issues[$cm->id][] = [
                            'type'        => 'r0_after_course_end',
                            'severity'    => 'error',
                            'field'       => $entry['field'] ?? '',
                            'ts_field'    => $ts,
                            'ts_boundary' => $courseend,
                        ];
                        break;
                    }
                }
            }

            // R0b: Any date before course start (only when course start is set).
            if ($coursestart > 0) {
                foreach ($entries as $entry) {
                    $ts = (int)($entry['timestamp'] ?? 0);
                    if ($ts > 0 && $ts < $coursestart) {
                        $issues[$cm->id][] = [
                            'type'        => 'r0_before_course_start',
                            'severity'    => 'error',
                            'field'       => $entry['field'] ?? '',
                            'ts_field'    => $ts,
                            'ts_boundary' => $coursestart,
                        ];
                        break;
                    }
                }
            }

            // R0c: Primary deadline in the past, only when completion is active.
            if ($cm->completion > 0) {
                $component = $cm->get_component();
                $deadlinefield = self::DEADLINE_FIELD[$component] ?? null;
                if ($deadlinefield !== null) {
                    foreach ($entries as $entry) {
                        if (
                            ($entry['field'] ?? '') === $deadlinefield
                            && ($entry['source'] ?? '') === 'adapter'
                            && ($entry['timestamp'] ?? 0) > 0
                            && $entry['timestamp'] < $now
                        ) {
                            $issues[$cm->id][] = [
                                'type'     => 'r0_deadline_in_past',
                                'severity' => 'warning',
                                'field'    => $deadlinefield,
                                'ts_field' => (int)$entry['timestamp'],
                            ];
                            break;
                        }
                    }
                }
            }
        }

        // Escalate severity for activities required for course completion.
        if (!empty($critcmids)) {
            $critset = array_flip($critcmids);
            foreach ($issues as $cmid => &$cmissues) {
                if (!isset($critset[$cmid])) {
                    continue;
                }
                foreach ($cmissues as &$issue) {
                    if (($issue['severity'] ?? '') === 'warning') {
                        $issue['severity'] = 'error';
                        $issue['completion_escalated'] = true;
                    } else if (($issue['severity'] ?? '') === 'notice') {
                        $issue['severity'] = 'warning';
                        $issue['completion_escalated'] = true;
                    }
                }
                unset($issue);
            }
            unset($cmissues);
        }

        return $issues;
    }
}
