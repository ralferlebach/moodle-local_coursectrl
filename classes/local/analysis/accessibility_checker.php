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
 * Accessibility checker (Rule R1) for the Course Control Hub.
 *
 * Checks whether each CM is currently accessible from a neutral learner
 * perspective — no assumed completions, no group membership, today's date.
 *
 * Three modes (configured via Admin → local_coursectrl → r1_mode):
 *
 *   off        — R1 is disabled entirely.
 *
 *   static     — Only the teacher-visible flag is evaluated. A CM that is
 *                hidden by the teacher is flagged. No availability conditions
 *                are evaluated. Fast, requires no simulation infrastructure.
 *
 *   simulation — Full condition evaluation via condition_evaluator using a
 *                neutral learner_state (now, no completions, no groups).
 *                A CM is flagged when it is invisible OR when all availability
 *                conditions evaluate to FAIL or UNKNOWN with at least one FAIL.
 *
 * The checker intentionally uses a conservative neutral state so that it only
 * flags CMs that are unreachable even for the most permissive learner. CMs
 * that are gated behind a group condition are not flagged in static mode.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;
use local_coursectrl\local\simulation\condition_evaluator;
use local_coursectrl\local\simulation\learner_state;

/**
 * Checks whether course modules are currently accessible to learners.
 */
class accessibility_checker {
    /** @var string R1 mode: 'off' | 'static' | 'simulation' */
    private string $mode;

    /** @var string Configured severity for R1 findings. */
    private string $severity;

    /** @var condition_evaluator|null Only instantiated in simulation mode. */
    private ?condition_evaluator $evaluator;

    /**
     * Constructor.
     *
     * @param string|null               $mode      Override mode (for tests). Reads
     *                                             admin config when null.
     * @param string|null               $severity  Override severity (for tests).
     * @param condition_evaluator|null  $evaluator Override evaluator (for tests).
     */
    public function __construct(
        ?string $mode = null,
        ?string $severity = null,
        ?condition_evaluator $evaluator = null
    ) {
        $cfgmode = (string)(get_config('local_coursectrl', 'r1_mode') ?? 'static');
        $cfgsev = (string)(get_config('local_coursectrl', 'r1_severity') ?? 'notice');
        $this->mode = $mode ?? (in_array($cfgmode, ['off', 'static', 'simulation']) ? $cfgmode : 'static');
        $this->severity = $severity ?? (in_array($cfgsev, ['notice', 'warning']) ? $cfgsev : 'notice');
        $this->evaluator = $evaluator;
    }

    /**
     * Check all CMs for accessibility issues.
     *
     * @param cm_item[] $cms Course modules keyed by cmid.
     * @return array<int, array[]> cmid → list of R1 issue arrays.
     */
    public function check(array $cms): array {
        if ($this->mode === 'off') {
            return [];
        }

        $issues = [];

        if ($this->mode === 'simulation') {
            if ($this->evaluator === null) {
                $this->evaluator = new condition_evaluator();
            }
            // Neutral state: now, no completions, no groups.
            $state = new learner_state(time(), [], [], []);
        }

        foreach ($cms as $cm) {
            // Static mode: only check teacher-visible flag.
            if ($this->mode === 'static') {
                if (!$cm->visible) {
                    $issues[$cm->id][] = [
                        'type'     => 'r1_hidden',
                        'severity' => $this->severity,
                    ];
                }
                continue;
            }

            // Simulation mode: full condition evaluation.
            if (!$cm->visible) {
                $issues[$cm->id][] = [
                    'type'     => 'r1_hidden',
                    'severity' => $this->severity,
                ];
                continue;
            }

            if ($cm->availability === null || $cm->availability === '') {
                continue;
            }

            $result = $this->evaluator->evaluate($cm->availability, $state);

            if ($result['accessible']) {
                continue;
            }

            // Only flag when at least one condition is an outright FAIL
            // (not just UNKNOWN, which could be a grade condition).
            $hasfail = false;
            foreach ($result['reasons'] as $reason) {
                if (($reason['status'] ?? '') === condition_evaluator::STATUS_FAIL) {
                    $hasfail = true;
                    break;
                }
            }

            if ($hasfail) {
                $issues[$cm->id][] = [
                    'type'     => 'r1_not_accessible',
                    'severity' => $this->severity,
                    'reasons'  => $result['reasons'],
                ];
            }
        }

        return $issues;
    }
}
