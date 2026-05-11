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
 * Tests for consistency_runner.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\analysis\consistency_runner::class)]
/**
 * Unit tests for consistency_runner::get_warnings().
 *
 * @covers \local_coursectrl\local\analysis\consistency_runner
 */
final class consistency_runner_test extends \advanced_testcase {
    /** @var int Base timestamp (2026-06-01 UTC). */
    private const T1 = 1748736000;

    /** @var int T1 + 7 days. */
    private const T2 = 1749340800;

    /**
     * Empty CMs produce an empty result without errors.
     * @covers \local_coursectrl\local\analysis\consistency_runner
     * @return void
     */
    public function test_empty_cms_returns_empty(): void {
        $this->resetAfterTest();
        $runner = new consistency_runner();
        $depindex = new dependency_index([]);
        $result = $runner->get_warnings([], $depindex, []);
        $this->assertEmpty($result);
    }

    /**
     * A temporal conflict is returned under the correct cmid with type key.
     * @covers \local_coursectrl\local\analysis\consistency_runner
     * @return void
     */
    public function test_temporal_conflict_surfaced(): void {
        $this->resetAfterTest();
        // Quiz with timeclose before timeopen.
        $cm = new cm_item(10, 1, 10, 'quiz', 10, 'Quiz', true, null, 2);
        $cms = [10 => $cm];
        $depindex = new dependency_index($cms);
        $datesbycm = [
            10 => [
                ['cmid' => 10, 'field' => 'timeopen', 'fieldlabel' => 'timeopen',
                    'timestamp' => self::T2, 'source' => 'adapter'],
                ['cmid' => 10, 'field' => 'timeclose', 'fieldlabel' => 'timeclose',
                    'timestamp' => self::T1, 'source' => 'adapter'],
            ],
        ];
        $runner = new consistency_runner();
        $result = $runner->get_warnings($cms, $depindex, $datesbycm);
        $this->assertArrayHasKey(10, $result);
        $types = array_column($result[10], 'type');
        $this->assertContains('temporal_conflict', $types);
        $conflict = array_values(array_filter($result[10], fn($w) => $w['type'] === 'temporal_conflict'))[0];
        $this->assertSame('timeopen', $conflict['field_early']);
        $this->assertSame('timeclose', $conflict['field_late']);
    }

    /**
     * A dangling dependency is returned under the depending CM's cmid.
     * @covers \local_coursectrl\local\analysis\consistency_runner
     * @return void
     */
    public function test_dangling_dep_surfaced(): void {
        $this->resetAfterTest();
        $avail = json_encode([
            'op' => '&',
            'c' => [['type' => 'completion', 'cm' => 999, 'e' => 1]],
            'show' => false,
        ]);
        $cm = new cm_item(20, 1, 10, 'assign', 20, 'Homework', true, $avail, 2);
        $cms = [20 => $cm];
        $depindex = new dependency_index($cms);
        $runner = new consistency_runner();
        $result = $runner->get_warnings($cms, $depindex, []);
        $this->assertArrayHasKey(20, $result);
        $types = array_column($result[20], 'type');
        $this->assertContains('dangling_dep', $types);
    }

    /**
     * Both conflict types can appear together for different CMs in one call.
     * @covers \local_coursectrl\local\analysis\consistency_runner
     * @return void
     */
    public function test_multiple_issues_across_cms(): void {
        $this->resetAfterTest();
        $avail = json_encode([
            'op' => '&',
            'c' => [['type' => 'completion', 'cm' => 999, 'e' => 1]],
            'show' => false,
        ]);
        $cms = [
            10 => new cm_item(10, 1, 10, 'quiz', 10, 'Quiz', true, null, 2),
            20 => new cm_item(20, 1, 10, 'assign', 20, 'HW', true, $avail, 2),
        ];
        $depindex = new dependency_index($cms);
        $datesbycm = [
            10 => [
                ['cmid' => 10, 'field' => 'timeopen', 'fieldlabel' => 'timeopen',
                    'timestamp' => self::T2, 'source' => 'adapter'],
                ['cmid' => 10, 'field' => 'timeclose', 'fieldlabel' => 'timeclose',
                    'timestamp' => self::T1, 'source' => 'adapter'],
            ],
        ];
        $runner = new consistency_runner();
        $result = $runner->get_warnings($cms, $depindex, $datesbycm);
        $this->assertArrayHasKey(10, $result);
        $this->assertArrayHasKey(20, $result);
        $this->assertSame('temporal_conflict', $result[10][0]['type']);
        $this->assertSame('dangling_dep', $result[20][0]['type']);
    }

    /**
     * A CM with no issues is not present in the result map.
     * @covers \local_coursectrl\local\analysis\consistency_runner
     * @return void
     */
    public function test_clean_cm_absent_from_result(): void {
        $this->resetAfterTest();
        $cm = new cm_item(30, 1, 10, 'assign', 30, 'Clean', true, null, 2);
        $cms = [30 => $cm];
        $depindex = new dependency_index($cms);
        $datesbycm = [
            30 => [
                ['cmid' => 30, 'field' => 'allowsubmissionsfromdate', 'fieldlabel' => 'open',
                    'timestamp' => self::T1, 'source' => 'adapter'],
                ['cmid' => 30, 'field' => 'duedate', 'fieldlabel' => 'due',
                    'timestamp' => self::T2, 'source' => 'adapter'],
            ],
        ];
        $runner = new consistency_runner();
        $result = $runner->get_warnings($cms, $depindex, $datesbycm);
        $this->assertArrayNotHasKey(30, $result);
    }
}
