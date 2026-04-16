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
 * Visibility simulator for the Course Control Hub.
 *
 * Evaluates all course modules in an inventory snapshot against a given
 * learner_state and produces a per-CM result map.
 *
 * Each CM result contains:
 *   cmid         — int
 *   name         — string
 *   modname      — string
 *   teacher_visible — bool  (visible flag set by teacher)
 *   accessible   — bool  (availability conditions satisfied for this learner)
 *   status       — string (pass | fail | unknown)
 *   has_restrictions — bool
 *   reasons      — array of per-condition result arrays from condition_evaluator
 *
 * A CM that is teacher-hidden is reported as accessible=false regardless of
 * availability conditions, because even a passing availability tree cannot
 * override a hidden CM.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\simulation;

use local_coursectrl\local\entity\cm_item;

/**
 * Evaluates CM accessibility for a simulated learner.
 */
class visibility_simulator {
    /** @var condition_evaluator */
    private condition_evaluator $evaluator;

    /**
     * Constructor.
     *
     * @param condition_evaluator|null $evaluator Optional override for DI.
     */
    public function __construct(?condition_evaluator $evaluator = null) {
        $this->evaluator = $evaluator ?? new condition_evaluator();
    }

    /**
     * Simulate accessibility for all CMs in the given collection.
     *
     * @param cm_item[]     $cms   Course modules keyed by cmid.
     * @param learner_state $state Hypothetical learner state.
     * @return array<int, array> cmid → result array (see class doc).
     */
    public function simulate(array $cms, learner_state $state): array {
        $results = [];
        foreach ($cms as $cm) {
            $results[$cm->id] = $this->evaluate_cm($cm, $state);
        }
        return $results;
    }

    /**
     * Evaluate a single CM.
     *
     * @param cm_item       $cm    The CM to evaluate.
     * @param learner_state $state Learner state.
     * @return array Result array.
     */
    private function evaluate_cm(cm_item $cm, learner_state $state): array {
        if (!$cm->visible) {
            return [
                'cmid' => $cm->id,
                'name' => $cm->name,
                'modname' => $cm->modname,
                'teacher_visible' => false,
                'accessible' => false,
                'status' => condition_evaluator::STATUS_FAIL,
                'has_restrictions' => false,
                'reasons' => [
                    [
                        'type' => 'teacher_hidden',
                        'status' => condition_evaluator::STATUS_FAIL,
                        'detail' => 'cm_not_visible',
                    ],
                ],
            ];
        }

        $hasrestrictions = $cm->availability !== null && $cm->availability !== '';
        $evalresult = $this->evaluator->evaluate($cm->availability, $state);

        return [
            'cmid' => $cm->id,
            'name' => $cm->name,
            'modname' => $cm->modname,
            'teacher_visible' => true,
            'accessible' => $evalresult['accessible'],
            'status' => $evalresult['status'],
            'has_restrictions' => $hasrestrictions,
            'reasons' => $evalresult['reasons'],
        ];
    }
}
