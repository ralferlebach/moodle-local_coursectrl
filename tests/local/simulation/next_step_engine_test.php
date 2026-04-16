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
 * Tests for next_step_engine.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\simulation;

use local_coursectrl\local\entity\cm_item;

/**
 * Unit tests for next_step_engine.
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
        return new cm_item(
            $cmid,
            1,
            10,
            'assign',
            $cmid,
            'Activity ' . $cmid,
            true,
            null,
            $completion
        );
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
     * find_blocked returns teacher-visible but inaccessible CMs only.
     */
    public function test_find_blocked_returns_blocked_cms(): void {
        $this->resetAfterTest();
        $simresults = [
            1 => ['accessible' => true, 'teacher_visible' => true, 'status' => 'pass'],
            2 => ['accessible' => false, 'teacher_visible' => true, 'status' => 'fail'],
            3 => ['accessible' => false, 'teacher_visible' => false, 'status' => 'fail'],
        ];
        $engine = new next_step_engine();
        // Only cmid 2 is blocked; cmid 3 is teacher-hidden, not counted as blocked.
        $this->assertSame([2], $engine->find_blocked($simresults));
    }
}
