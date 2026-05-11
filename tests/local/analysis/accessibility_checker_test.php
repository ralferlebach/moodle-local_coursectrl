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
 * Tests for accessibility_checker.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;
use local_coursectrl\local\simulation\condition_evaluator;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\analysis\accessibility_checker::class)]
/**
 * Unit tests for accessibility_checker::check().
 *
 * Tests cover all three modes (off, static, simulation) and both issue types
 * (r1_hidden, r1_not_accessible).
 *
 * @covers \local_coursectrl\local\analysis\accessibility_checker
 */
final class accessibility_checker_test extends \advanced_testcase {
    // Helpers.

    /**
     * Build a cm_item.
     *
     * @param int         $cmid
     * @param bool        $visible
     * @param string|null $avail  JSON availability or null.
     * @return cm_item
     */
    private function make_cm(int $cmid, bool $visible = true, ?string $avail = null): cm_item {
        return new cm_item($cmid, 1, 1, 'assign', $cmid, "CM $cmid", $visible, $avail, 2);
    }

    /**
     * Build an availability JSON that always evaluates to FAIL (requires
     * completion of a CM that does not exist in any learner state).
     *
     * @param int $requirecmid
     * @return string
     */
    private function avail_requires(int $requirecmid): string {
        return json_encode([
            'op' => '&',
            'c' => [['type' => 'completion', 'cm' => $requirecmid, 'e' => 1]],
            'showc' => [true],
        ]);
    }

    /**
     * Build an availability JSON that is date-gated to a future timestamp.
     *
     * @param int $ts Future unix timestamp.
     * @return string
     */
    private function avail_future_date(int $ts): string {
        return json_encode([
            'op' => '&',
            'c' => [['type' => 'date', 'd' => '>=', 't' => $ts]],
            'showc' => [true],
        ]);
    }

    // Mode: off.

    /**
     * Mode 'off' → always returns empty, even for hidden CMs.
     * @covers \local_coursectrl\local\analysis\accessibility_checker
     * @return void
     */
    public function test_mode_off_returns_empty(): void {
        $this->resetAfterTest();
        $checker = new accessibility_checker('off');
        $cm = $this->make_cm(1, false); // Hidden.
        $result = $checker->check([1 => $cm]);
        $this->assertEmpty($result, 'Mode off should return empty regardless');
    }

    // Mode: static.

    /**
     * Mode 'static': hidden CM → r1_hidden.
     * @covers \local_coursectrl\local\analysis\accessibility_checker
     * @return void
     */
    public function test_static_hidden_cm_flagged(): void {
        $this->resetAfterTest();
        $checker = new accessibility_checker('static', 'notice');
        $cm = $this->make_cm(1, false);
        $result = $checker->check([1 => $cm]);
        $this->assertArrayHasKey(1, $result);
        $types = array_column($result[1], 'type');
        $this->assertContains('r1_hidden', $types);
        $issue = current(array_filter($result[1], fn($i) => $i['type'] === 'r1_hidden'));
        $this->assertSame('notice', $issue['severity']);
    }

    /**
     * Mode 'static': visible CM → no issue, even with restrictive availability.
     * @covers \local_coursectrl\local\analysis\accessibility_checker
     * @return void
     */
    public function test_static_visible_cm_no_issue(): void {
        $this->resetAfterTest();
        $checker = new accessibility_checker('static', 'notice');
        $cm = $this->make_cm(1, true, $this->avail_requires(999));
        $result = $checker->check([1 => $cm]);
        $this->assertArrayNotHasKey(1, $result, 'Static mode must not evaluate availability conditions');
    }

    /**
     * Mode 'static': severity override respected.
     * @covers \local_coursectrl\local\analysis\accessibility_checker
     * @return void
     */
    public function test_static_severity_override(): void {
        $this->resetAfterTest();
        $checker = new accessibility_checker('static', 'warning');
        $cm = $this->make_cm(1, false);
        $result = $checker->check([1 => $cm]);
        $this->assertArrayHasKey(1, $result);
        $this->assertSame('warning', $result[1][0]['severity']);
    }

    /**
     * Mode 'static': empty CMs → empty result.
     * @covers \local_coursectrl\local\analysis\accessibility_checker
     * @return void
     */
    public function test_static_empty_cms(): void {
        $this->resetAfterTest();
        $checker = new accessibility_checker('static');
        $this->assertEmpty($checker->check([]));
    }

    /**
     * Mode 'static': multiple CMs — hidden ones flagged, visible not.
     * @covers \local_coursectrl\local\analysis\accessibility_checker
     * @return void
     */
    public function test_static_mixed_visibility(): void {
        $this->resetAfterTest();
        $checker = new accessibility_checker('static', 'notice');
        $cms = [
            1 => $this->make_cm(1, true),
            2 => $this->make_cm(2, false),
            3 => $this->make_cm(3, true),
        ];
        $result = $checker->check($cms);
        $this->assertArrayNotHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertArrayNotHasKey(3, $result);
    }

    // Mode: simulation.

    /**
     * Mode 'simulation': hidden CM → r1_hidden (same as static).
     * @covers \local_coursectrl\local\analysis\accessibility_checker
     * @return void
     */
    public function test_simulation_hidden_cm_flagged(): void {
        $this->resetAfterTest();
        $checker = new accessibility_checker('simulation', 'notice');
        $cm = $this->make_cm(1, false);
        $result = $checker->check([1 => $cm]);
        $this->assertArrayHasKey(1, $result);
        $types = array_column($result[1], 'type');
        $this->assertContains('r1_hidden', $types);
    }

    /**
     * Mode 'simulation': visible CM without availability → not flagged.
     * @covers \local_coursectrl\local\analysis\accessibility_checker
     * @return void
     */
    public function test_simulation_no_availability_not_flagged(): void {
        $this->resetAfterTest();
        $checker = new accessibility_checker('simulation', 'notice');
        $cm = $this->make_cm(1, true, null);
        $result = $checker->check([1 => $cm]);
        $this->assertArrayNotHasKey(1, $result);
    }

    /**
     * Mode 'simulation': visible CM with future date → r1_not_accessible.
     *
     * A neutral learner state uses today's time, so a CM gated behind a
     * far-future date evaluates to FAIL.
     * @covers \local_coursectrl\local\analysis\accessibility_checker
     * @return void
     */
    public function test_simulation_future_date_condition_flagged(): void {
        $this->resetAfterTest();
        $futurerts = time() + (365 * DAYSECS); // 1 year from now.
        $checker = new accessibility_checker('simulation', 'notice');
        $cm = $this->make_cm(1, true, $this->avail_future_date($futurerts));
        $result = $checker->check([1 => $cm]);
        $this->assertArrayHasKey(1, $result, 'CM gated behind future date should be flagged');
        $types = array_column($result[1], 'type');
        $this->assertContains('r1_not_accessible', $types);
    }

    /**
     * Mode 'simulation': visible CM with past date → not flagged (accessible).
     * @covers \local_coursectrl\local\analysis\accessibility_checker
     * @return void
     */
    public function test_simulation_past_date_condition_not_flagged(): void {
        $this->resetAfterTest();
        $pastts = time() - DAYSECS; // Yesterday.
        $checker = new accessibility_checker('simulation', 'notice');
        $cm = $this->make_cm(1, true, $this->avail_future_date($pastts));
        $result = $checker->check([1 => $cm]);
        $this->assertArrayNotHasKey(1, $result, 'CM gated behind past date should be accessible and not flagged');
    }

    /**
     * Mode 'simulation': unsatisfied completion condition → r1_not_accessible.
     * @covers \local_coursectrl\local\analysis\accessibility_checker
     * @return void
     */
    public function test_simulation_completion_condition_flagged(): void {
        $this->resetAfterTest();
        $checker = new accessibility_checker('simulation', 'notice');
        // Neutral learner has completed nothing → completion condition fails.
        $cm = $this->make_cm(1, true, $this->avail_requires(999));
        $result = $checker->check([1 => $cm]);
        $this->assertArrayHasKey(1, $result);
        $types = array_column($result[1], 'type');
        $this->assertContains('r1_not_accessible', $types);
        $issue = current(array_filter($result[1], fn($i) => $i['type'] === 'r1_not_accessible'));
        $this->assertArrayHasKey('reasons', $issue);
    }

    /**
     * Mode 'simulation': result has expected keys.
     * @covers \local_coursectrl\local\analysis\accessibility_checker
     * @return void
     */
    public function test_simulation_result_shape(): void {
        $this->resetAfterTest();
        $checker = new accessibility_checker('simulation', 'warning');
        $cm = $this->make_cm(1, false);
        $result = $checker->check([1 => $cm]);
        $this->assertNotEmpty($result);
        $issue = $result[1][0];
        $this->assertArrayHasKey('type', $issue);
        $this->assertArrayHasKey('severity', $issue);
    }
}
