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
 * Tests for deep_journey_simulator.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;
use local_coursectrl\local\simulation\condition_evaluator;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\analysis\deep_journey_simulator::class)]
/**
 * Unit and integration tests for deep_journey_simulator.
 *
 * @covers \local_coursectrl\local\analysis\deep_journey_simulator
 */
final class deep_journey_simulator_test extends \advanced_testcase {
    /** @var int Fixed start timestamp (2026-06-01 00:00 UTC). */
    private const TS = 1748736000;

    // Helpers.

    /**
     * Build a cm_item.
     *
     * @param int         $id
     * @param string|null $avail
     * @param bool        $visible
     * @param int         $completion
     * @return cm_item
     */
    private function cm(
        int $id,
        ?string $avail = null,
        bool $visible = true,
        int $completion = 2
    ): cm_item {
        return new cm_item(
            $id,
            1,
            10,
            'assign',
            $id,
            'Activity ' . $id,
            $visible,
            $avail,
            $completion
        );
    }

    /**
     * Build completion-based availability JSON (e=1, must be complete).
     *
     * @param int $depcmid
     * @return string
     */
    private function avail_requires(int $depcmid): string {
        return json_encode([
            'op' => '&',
            'c' => [['type' => 'completion', 'cm' => $depcmid, 'e' => 1]],
        ]);
    }

    /**
     * Build a simulator with overridden settings (no DB call).
     *
     * @param int $minutes  Minutes per activity.
     * @param int $maxcombos Max group combos.
     * @return deep_journey_simulator
     */
    private function sim(int $minutes = 30, int $maxcombos = 32): deep_journey_simulator {
        return new deep_journey_simulator($minutes, $maxcombos);
    }

    // Tests: simulate_journey.

    /**
     * An activity with no conditions is reachable immediately.
     * @covers \local_coursectrl\local\analysis\deep_journey_simulator
     * @return void
     */
    public function test_open_activity_is_reachable(): void {
        $this->resetAfterTest();
        $cms = [1 => $this->cm(1)];
        $ev = new condition_evaluator();
        $result = $this->sim()->simulate_journey($cms, $ev, [], [], [], 'pass', self::TS);

        $this->assertContains(1, $result['reachable']);
        $this->assertEmpty($result['unreachable']);
    }

    /**
     * Linear chain A→B→C: all reachable by working through A then B.
     * @covers \local_coursectrl\local\analysis\deep_journey_simulator
     * @return void
     */
    public function test_linear_chain_all_reachable(): void {
        $this->resetAfterTest();
        $cms = [
            1 => $this->cm(1),
            2 => $this->cm(2, $this->avail_requires(1)),
            3 => $this->cm(3, $this->avail_requires(2)),
        ];
        $ev = new condition_evaluator();
        $result = $this->sim()->simulate_journey($cms, $ev, [], [], [], 'pass', self::TS);

        $this->assertContains(1, $result['reachable']);
        $this->assertContains(2, $result['reachable']);
        $this->assertContains(3, $result['reachable']);
        $this->assertEmpty($result['unreachable']);
    }

    /**
     * Activity dependent on a hidden prerequisite is unreachable.
     * @covers \local_coursectrl\local\analysis\deep_journey_simulator
     * @return void
     */
    public function test_dep_on_hidden_is_unreachable(): void {
        $this->resetAfterTest();
        $cms = [
            1 => $this->cm(1, null, false), // Hidden.
            2 => $this->cm(2, $this->avail_requires(1)),
        ];
        $ev = new condition_evaluator();
        $result = $this->sim()->simulate_journey($cms, $ev, [], [], [], 'pass', self::TS);

        $this->assertContains(2, $result['unreachable']);
    }

    /**
     * Circular dependency: neither can be reached first.
     * @covers \local_coursectrl\local\analysis\deep_journey_simulator
     * @return void
     */
    public function test_circular_dep_both_unreachable(): void {
        $this->resetAfterTest();
        $cms = [
            10 => $this->cm(10, $this->avail_requires(11)),
            11 => $this->cm(11, $this->avail_requires(10)),
        ];
        $ev = new condition_evaluator();
        $result = $this->sim()->simulate_journey($cms, $ev, [], [], [], 'pass', self::TS);

        $this->assertContains(10, $result['unreachable']);
        $this->assertContains(11, $result['unreachable']);
    }

    /**
     * Journey steps are recorded in visit order.
     * @covers \local_coursectrl\local\analysis\deep_journey_simulator
     * @return void
     */
    public function test_journey_steps_recorded(): void {
        $this->resetAfterTest();
        $cms = [
            1 => $this->cm(1),
            2 => $this->cm(2, $this->avail_requires(1)),
        ];
        $ev = new condition_evaluator();
        $result = $this->sim(30)->simulate_journey($cms, $ev, [], [], [], 'pass', self::TS);

        $this->assertCount(2, $result['steps']);
        $this->assertSame(1, $result['steps'][0]['cmid']);
        $this->assertSame(2, $result['steps'][1]['cmid']);

        // Step timestamps advance by min_activity_minutes.
        $this->assertSame(self::TS + 1800, $result['steps'][0]['ts']);
        $this->assertSame(self::TS + 3600, $result['steps'][1]['ts']);
    }

    // Tests: build_group_combinations.

    /**
     * No groups → one scenario (empty set).
     * @covers \local_coursectrl\local\analysis\deep_journey_simulator
     * @return void
     */
    public function test_no_groups_returns_one_scenario(): void {
        $this->resetAfterTest();
        $combos = $this->sim()->build_group_combinations([]);
        $this->assertCount(1, $combos);
        $this->assertSame([], $combos[0]);
    }

    /**
     * Two groups → 4 scenarios: {}, {G1}, {G2}, {G1,G2}.
     * @covers \local_coursectrl\local\analysis\deep_journey_simulator
     * @return void
     */
    public function test_two_groups_returns_four_scenarios(): void {
        $this->resetAfterTest();
        $groups = [(object)['id' => 1], (object)['id' => 2]];
        $combos = $this->sim()->build_group_combinations($groups);
        $this->assertCount(4, $combos);
    }

    /**
     * max_group_combinations limits the number of scenarios returned.
     * @covers \local_coursectrl\local\analysis\deep_journey_simulator
     * @return void
     */
    public function test_max_group_combinations_limits_output(): void {
        $this->resetAfterTest();
        $groups = array_map(fn ($i) => (object)['id' => $i], range(1, 10));
        // 2^10 = 1024 combinations possible; limit to 5.
        $combos = $this->sim(30, 5)->build_group_combinations($groups);
        $this->assertCount(5, $combos);
    }

    // Tests: simulate (full pipeline).

    /**
     * Fully open course with no conditions → no findings.
     * @covers \local_coursectrl\local\analysis\deep_journey_simulator
     * @return void
     */
    public function test_simulate_open_course_no_findings(): void {
        $this->resetAfterTest();
        $cms = [
            1 => $this->cm(1),
            2 => $this->cm(2),
        ];
        $findings = $this->sim()->simulate($cms, []);
        $this->assertEmpty($findings);
    }

    /**
     * Circular dep produces journey_unreachable findings.
     * @covers \local_coursectrl\local\analysis\deep_journey_simulator
     * @return void
     */
    public function test_simulate_circular_produces_findings(): void {
        $this->resetAfterTest();
        $cms = [
            10 => $this->cm(10, $this->avail_requires(11)),
            11 => $this->cm(11, $this->avail_requires(10)),
        ];
        $findings = $this->sim()->simulate($cms, []);
        $types = array_column($findings, 'type');
        $this->assertContains('journey_unreachable', $types);
    }

    /**
     * Severity is escalated to error when the blocked activity is in critcmids.
     * @covers \local_coursectrl\local\analysis\deep_journey_simulator
     * @return void
     */
    public function test_simulate_escalates_severity_for_critcmid(): void {
        $this->resetAfterTest();
        $cms = [
            10 => $this->cm(10, $this->avail_requires(11)),
            11 => $this->cm(11, $this->avail_requires(10)),
        ];
        // CM 10 is required for course completion.
        $findings = $this->sim()->simulate($cms, [], [], [], [10]);

        $cm10findings = array_filter(
            $findings,
            fn ($f) => in_array(10, $f['cmids'], true) && $f['grademode'] === 'pass'
        );
        $this->assertNotEmpty($cm10findings);
        foreach ($cm10findings as $f) {
            $this->assertSame('error', $f['severity']);
        }
    }

    /**
     * Each finding contains a non-empty simlink.
     * @covers \local_coursectrl\local\analysis\deep_journey_simulator
     * @return void
     */
    public function test_simulate_finding_has_simlink(): void {
        $this->resetAfterTest();
        $cms = [
            10 => $this->cm(10, $this->avail_requires(11)),
            11 => $this->cm(11, $this->avail_requires(10)),
        ];
        $findings = $this->sim()->simulate($cms, []);
        foreach ($findings as $f) {
            $this->assertNotEmpty($f['simlink']);
            $this->assertStringContainsString('tab=simulation', $f['simlink']);
        }
    }
}
