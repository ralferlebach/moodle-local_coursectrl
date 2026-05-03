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
 * Next-step engine for the Course Control Hub simulation.
 *
 * From a visibility simulation result set, identifies which course modules
 * represent the learner's immediate next actions: activities that are
 * accessible right now but whose completion has not yet been assumed.
 *
 * A CM qualifies as a next-step candidate when ALL of the following hold:
 *   1. It is accessible (visibility_simulator result accessible === true).
 *   2. It has completion tracking enabled (cm_item::completion > 0).
 *   3. Its assumed completion state in learner_state is 0 (incomplete).
 *
 * This gives a deterministic, easy-to-explain set: "you can access these
 * activities but haven't marked them done yet."
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\simulation;

use local_coursectrl\local\entity\cm_item;

/**
 * Identifies the learner's next actionable steps from simulation results.
 */
class next_step_engine {
    /**
     * Find next-step candidate cmids.
     *
     * @param array $simresults Per-CM results from visibility_simulator::simulate().
     * @param cm_item[] $cms Course modules keyed by cmid.
     * @param learner_state $state The learner state used for the simulation.
     * @return int[] cmids of next-step candidates, in cmid order.
     */
    public function find_next_steps(
        array $simresults,
        array $cms,
        learner_state $state
    ): array {
        $nextsteps = [];
        foreach ($simresults as $cmid => $result) {
            if (!$result['accessible']) {
                continue;
            }
            $cm = $cms[$cmid] ?? null;
            if ($cm === null || $cm->completion === 0) {
                continue;
            }
            if ($state->get_completion($cmid) !== 0) {
                continue;
            }
            $nextsteps[] = $cmid;
        }
        sort($nextsteps);
        return $nextsteps;
    }

    /**
     * Find CMs that are inaccessible and explain why (blocked activities).
     *
     * Returns CMs where accessible === false and teacher_visible === true,
     * i.e. activities blocked by availability conditions (not hidden).
     *
     * @param array $simresults See function signature.
     * @return int[] cmids of blocked activities, in cmid order.
     */
    public function find_blocked(array $simresults): array {
        $blocked = [];
        foreach ($simresults as $cmid => $result) {
            if (!$result['accessible'] && ($result['teacher_visible'] ?? false)) {
                $blocked[] = $cmid;
            }
        }
        sort($blocked);
        return $blocked;
    }
}
