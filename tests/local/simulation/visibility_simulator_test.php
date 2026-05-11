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
 * Tests for visibility_simulator.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\simulation;

use local_coursectrl\local\entity\cm_item;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\simulation\visibility_simulator::class)]
/**
 * Unit tests for visibility_simulator.
 *
 * @covers \local_coursectrl\local\simulation\visibility_simulator
 */
final class visibility_simulator_test extends \advanced_testcase {
    /** @var int Simulated "now" timestamp. */
    private const NOW = 1750507200;

    /** @var int One week in the past. */
    private const PAST = 1749902400;

    /** @var int One week in the future. */
    private const FUTURE = 1751112000;

    /**
     * Build a cm_item.
     *
     * @param int    $cmid       CM id.
     * @param bool   $visible    Teacher visibility.
     * @param string $avail      Availability JSON or ''.
     * @param int    $completion Completion tracking mode.
     * @return cm_item
     */
    private function make_cm(
        int $cmid,
        bool $visible = true,
        string $avail = '',
        int $completion = 2
    ): cm_item {
        return new cm_item(
            $cmid,
            1,
            10,
            'assign',
            $cmid,
            'Activity ' . $cmid,
            $visible,
            $avail !== '' ? $avail : null,
            $completion
        );
    }

    /**
     * No restrictions → CM is accessible.
     * @covers \local_coursectrl\local\simulation\visibility_simulator
     * @return void
     */
    public function test_no_restrictions_accessible(): void {
        $this->resetAfterTest();
        $cms = [1 => $this->make_cm(1)];
        $state = new learner_state(self::NOW);
        $sim = new visibility_simulator();
        $results = $sim->simulate($cms, $state);
        $this->assertTrue($results[1]['accessible']);
        $this->assertTrue($results[1]['teacher_visible']);
        $this->assertFalse($results[1]['has_restrictions']);
    }

    /**
     * Teacher-hidden CM is inaccessible regardless of availability.
     * @covers \local_coursectrl\local\simulation\visibility_simulator
     * @return void
     */
    public function test_hidden_cm_not_accessible(): void {
        $this->resetAfterTest();
        $cms = [2 => $this->make_cm(2, false)];
        $state = new learner_state(self::NOW);
        $sim = new visibility_simulator();
        $results = $sim->simulate($cms, $state);
        $this->assertFalse($results[2]['accessible']);
        $this->assertFalse($results[2]['teacher_visible']);
        $reasons = array_column($results[2]['reasons'], 'type');
        $this->assertContains('teacher_hidden', $reasons);
    }

    /**
     * Date restriction in past → accessible.
     * @covers \local_coursectrl\local\simulation\visibility_simulator
     * @return void
     */
    public function test_past_date_restriction_passes(): void {
        $this->resetAfterTest();
        $avail = json_encode([
            'op' => '&',
            'c' => [['type' => 'date', 'd' => '>=', 't' => self::PAST]],
            'show' => false,
        ]);
        $cms = [3 => $this->make_cm(3, true, $avail)];
        $state = new learner_state(self::NOW);
        $sim = new visibility_simulator();
        $results = $sim->simulate($cms, $state);
        $this->assertTrue($results[3]['accessible']);
        $this->assertTrue($results[3]['has_restrictions']);
    }

    /**
     * Date restriction in future → not accessible.
     * @covers \local_coursectrl\local\simulation\visibility_simulator
     * @return void
     */
    public function test_future_date_restriction_blocks(): void {
        $this->resetAfterTest();
        $avail = json_encode([
            'op' => '&',
            'c' => [['type' => 'date', 'd' => '>=', 't' => self::FUTURE]],
            'show' => false,
        ]);
        $cms = [4 => $this->make_cm(4, true, $avail)];
        $state = new learner_state(self::NOW);
        $sim = new visibility_simulator();
        $results = $sim->simulate($cms, $state);
        $this->assertFalse($results[4]['accessible']);
    }

    /**
     * All results are keyed by cmid.
     * @covers \local_coursectrl\local\simulation\visibility_simulator
     * @return void
     */
    public function test_results_keyed_by_cmid(): void {
        $this->resetAfterTest();
        $cms = [
            10 => $this->make_cm(10),
            20 => $this->make_cm(20),
        ];
        $state = new learner_state(self::NOW);
        $sim = new visibility_simulator();
        $results = $sim->simulate($cms, $state);
        $this->assertArrayHasKey(10, $results);
        $this->assertArrayHasKey(20, $results);
        $this->assertSame(10, $results[10]['cmid']);
        $this->assertSame(20, $results[20]['cmid']);
    }
}
