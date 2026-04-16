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
 * Tests for visibility_simulator and next_step_engine.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\simulation;

use local_coursectrl\local\entity\cm_item;

/**
 * Tests for visibility_simulator.
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
            $cmid, 1, 10, 'assign', $cmid, 'Activity ' . $cmid,
            $visible, $avail !== '' ? $avail : null, $completion
        );
    }

    /**
     * No restrictions → CM is accessible.
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

/**
 * Tests for next_step_engine.
 *
 * @covers \local_coursectrl\local\simulation\next_step_engine
 */
final class next_step_engine_test extends \advanced_testcase {
    /** @var int Simulated timestamp. */
    private const NOW = 1750507200;

    /**
     * Build a minimal cm_item with completion tracking.
     *
     * @param int $cmid       CM id.
     * @param int $completion Tracking mode (0=off, 1=manual, 2=auto).
     * @return cm_item
     */
    private function make_cm(int $cmid, int $completion = 2): cm_item {
        return new cm_item($cmid, 1, 10, 'assign', $cmid, 'Activity ' . $cmid, true, null, $completion);
    }

    /**
     * Accessible + incomplete + tracking enabled → next step.
     */
    public function test_accessible_incomplete_tracked_is_next_step(): void {
        $this->resetAfterTest();
        $cms = [1 => $this->make_cm(1, 2)];
        $state = new learner_state(self::NOW, []);
        $simresults = [1 => ['accessible' => true, 'teacher_visible' => true, 'status' => 'pass']];
        $engine = new next_step_engine();
        $this->assertSame([1], $engine->find_next_steps($simresults, $cms, $state));
    }

    /**
     * Already complete CM is not a next step.
     */
    public function test_complete_cm_not_next_step(): void {
        $this->resetAfterTest();
        $cms = [1 => $this->make_cm(1, 2)];
        $state = new learner_state(self::NOW, [1 => 1]);
        $simresults = [1 => ['accessible' => true, 'teacher_visible' => true, 'status' => 'pass']];
        $engine = new next_step_engine();
        $this->assertEmpty($engine->find_next_steps($simresults, $cms, $state));
    }

    /**
     * Inaccessible CM is not a next step.
     */
    public function test_inaccessible_cm_not_next_step(): void {
        $this->resetAfterTest();
        $cms = [1 => $this->make_cm(1, 2)];
        $state = new learner_state(self::NOW);
        $simresults = [1 => ['accessible' => false, 'teacher_visible' => true, 'status' => 'fail']];
        $engine = new next_step_engine();
        $this->assertEmpty($engine->find_next_steps($simresults, $cms, $state));
    }

    /**
     * CM without completion tracking is not a next step.
     */
    public function test_no_tracking_not_next_step(): void {
        $this->resetAfterTest();
        $cms = [1 => $this->make_cm(1, 0)];
        $state = new learner_state(self::NOW);
        $simresults = [1 => ['accessible' => true, 'teacher_visible' => true, 'status' => 'pass']];
        $engine = new next_step_engine();
        $this->assertEmpty($engine->find_next_steps($simresults, $cms, $state));
    }

    /**
     * find_blocked returns teacher-visible but inaccessible CMs.
     */
    public function test_find_blocked_returns_blocked_cms(): void {
        $this->resetAfterTest();
        $simresults = [
            1 => ['accessible' => true, 'teacher_visible' => true, 'status' => 'pass'],
            2 => ['accessible' => false, 'teacher_visible' => true, 'status' => 'fail'],
            3 => ['accessible' => false, 'teacher_visible' => false, 'status' => 'fail'],
        ];
        $engine = new next_step_engine();
        // Only cmid 2 is blocked (teacher-visible but inaccessible); cmid 3 is hidden.
        $this->assertSame([2], $engine->find_blocked($simresults));
    }
}
