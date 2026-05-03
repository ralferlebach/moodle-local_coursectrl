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
 * Learner state DTO for the Course Control Hub simulation engine.
 *
 * Encapsulates all hypothetical state attributes used during a single
 * simulation run. Instances are immutable; create a new one to represent
 * a different scenario.
 *
 * Completion states follow Moodle's COMPLETION_COMPLETE_* constants:
 *   0 = COMPLETION_INCOMPLETE
 *   1 = COMPLETION_COMPLETE
 *   2 = COMPLETION_COMPLETE_PASS
 *   3 = COMPLETION_COMPLETE_FAIL
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\simulation;

/**
 * Immutable DTO carrying all simulated attributes of a learner in one scenario.
 */
final class learner_state {
    /** @var int Simulated "current" unix timestamp. */
    public readonly int $timestamp;

    /** @var array<int, int> Assumed completion state per cmid (0/1/2/3). */
    public readonly array $completions;

    /** @var array<int, float> Assumed grade percentage (0–100) per cmid. */
    public readonly array $grades;

    /** @var int[] Group ids the simulated learner belongs to. */
    public readonly array $groupids;

* @param int $timestamp Simulated unix timestamp.
* @param array<int,int> $completions Cmid → completion state map.
* @param int[] $groupids Group ids the learner is assumed to be in.
* @param int[] $groupingids Grouping ids the learner is assumed to be in.
* @param array<int,float> $grades Cmid → grade percentage map.
    /** @var int[] Grouping ids the simulated learner belongs to. */
    public readonly array $groupingids;

    /**
     * Constructor.
     *
     * @param int            $timestamp   Simulated unix timestamp (default: now).
     * @param array<int,int> $completions cmid → completion state (0=incomplete, 1=complete,
     *                                    2=complete with pass, 3=complete with fail).
     * @param int[]          $groupids    Group ids the learner is assumed to be in.
     * @param int[]          $groupingids Grouping ids the learner is assumed to be in.
     * @param array<int,float> $grades    cmid → grade percentage (0.0–100.0).
     */
    public function __construct(
        int $timestamp = 0,
        array $completions = [],
        array $groupids = [],
        array $groupingids = [],
        array $grades = []
    ) {
        $this->timestamp = $timestamp > 0 ? $timestamp : time();
        $this->completions = $completions;
        $this->grades = array_map('floatval', $grades);
        $this->groupids = array_values($groupids);
        $this->groupingids = array_values($groupingids);
    }

    /**
     * Return the assumed completion state for the given cmid.
     *
     * @param int $cmid Course module id.
     * @return int Completion state (0 = incomplete, 1 = complete, 2 = pass, 3 = fail).
     */
    public function get_completion(int $cmid): int {
        return $this->completions[$cmid] ?? 0;
    }

    /**
     * Return the assumed grade percentage for the given cmid, or null if not set.
     *
     * @param int $cmid Course module id.
     * @return float|null Grade percentage (0.0–100.0), or null when not specified.
     */
    public function get_grade(int $cmid): ?float {
        return isset($this->grades[$cmid]) ? (float) $this->grades[$cmid] : null;
    }

    /**
     * Whether the learner is assumed to be in the given group.
     *
     * @param int $groupid Moodle group id.
     * @return bool
     */
    public function is_in_group(int $groupid): bool {
        return in_array($groupid, $this->groupids, true);
    }

    /**
     * Whether the learner is assumed to be in the given grouping.
     *
     * @param int $groupingid Moodle grouping id.
     * @return bool
     */
    public function is_in_grouping(int $groupingid): bool {
        return in_array($groupingid, $this->groupingids, true);
    }

    /**
     * Return a plain-array representation suitable for form re-population.
     *
     * @param array<string,mixed> $data Serialised state produced by to_array().
     * @return array<string,mixed>
     */
    public function to_array(): array {
        return [
            'timestamp' => $this->timestamp,
            'completions' => $this->completions,
            'groupids' => $this->groupids,
            'groupingids' => $this->groupingids,
            'grades' => $this->grades,
        ];
    }

    /**
     * Reconstruct a learner_state from its array representation.
     *
     * @param array<string,mixed> $data Serialised state.
     * @return self
     */
    public static function from_array(array $data): self {
        return new self(
            (int) ($data['timestamp'] ?? 0),
            array_map('intval', (array) ($data['completions'] ?? [])),
            array_map('intval', (array) ($data['groupids'] ?? [])),
            array_map('intval', (array) ($data['groupingids'] ?? [])),
            array_map('floatval', (array) ($data['grades'] ?? []))
        );
    }
}
