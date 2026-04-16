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
 * Tests for reachability_analyzer.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;

/**
 * Unit tests for reachability_analyzer::analyze().
 *
 * @covers \local_coursectrl\local\analysis\reachability_analyzer
 */
final class reachability_analyzer_test extends \advanced_testcase {
    /**
     * Build a cm_item with the given completion setting.
     *
     * @param int    $cmid       CM id.
     * @param int    $completion COMPLETION_TRACKING_* constant (0 = disabled).
     * @param string $avail      Optional availability JSON.
     * @return cm_item
     */
    private function make_cm(int $cmid, int $completion = 2, string $avail = ''): cm_item {
        return new cm_item(
            $cmid,
            1,
            10,
            'assign',
            $cmid,
            'Activity ' . $cmid,
            true,
            $avail !== '' ? $avail : null,
            $completion
        );
    }

    /**
     * Availability JSON that requires completion of the given cmid.
     *
     * @param int $depcmid The prerequisite cmid.
     * @return string
     */
    private function avail_requires(int $depcmid): string {
        return json_encode([
            'op' => '&',
            'c' => [['type' => 'completion', 'cm' => $depcmid, 'e' => 1]],
            'show' => false,
        ]);
    }

    /**
     * No issues when no CM has availability restrictions.
     */
    public function test_no_restrictions_returns_empty(): void {
        $this->resetAfterTest();
        $cms = [
            1 => $this->make_cm(1),
            2 => $this->make_cm(2),
        ];
        $depindex = new dependency_index($cms);
        $analyzer = new reachability_analyzer();
        $result = $analyzer->analyze($cms, $depindex);
        $this->assertEmpty($result);
    }

    /**
     * No issues when the prerequisite CM exists and has tracking enabled.
     */
    public function test_valid_dep_no_issue(): void {
        $this->resetAfterTest();
        $cms = [
            1 => $this->make_cm(1, 2),
            2 => $this->make_cm(2, 2, $this->avail_requires(1)),
        ];
        $depindex = new dependency_index($cms);
        $analyzer = new reachability_analyzer();
        $result = $analyzer->analyze($cms, $depindex);
        $this->assertEmpty($result);
    }

    /**
     * dangling_dep when the prerequisite cmid is not in the inventory.
     */
    public function test_missing_dep_cmid_is_dangling(): void {
        $this->resetAfterTest();
        $cms = [
            2 => $this->make_cm(2, 2, $this->avail_requires(999)),
        ];
        $depindex = new dependency_index($cms);
        $analyzer = new reachability_analyzer();
        $result = $analyzer->analyze($cms, $depindex);
        $this->assertArrayHasKey(2, $result);
        $this->assertCount(1, $result[2]);
        $this->assertSame('dangling_dep', $result[2][0]['issuetype']);
        $this->assertSame(999, $result[2][0]['depcmid']);
        $this->assertNull($result[2][0]['depname']);
    }

    /**
     * impossible_dep when the prerequisite CM has completion === 0.
     */
    public function test_dep_with_no_tracking_is_impossible(): void {
        $this->resetAfterTest();
        $cms = [
            1 => $this->make_cm(1, 0),
            2 => $this->make_cm(2, 2, $this->avail_requires(1)),
        ];
        $depindex = new dependency_index($cms);
        $analyzer = new reachability_analyzer();
        $result = $analyzer->analyze($cms, $depindex);
        $this->assertArrayHasKey(2, $result);
        $this->assertSame('impossible_dep', $result[2][0]['issuetype']);
        $this->assertSame(1, $result[2][0]['depcmid']);
        $this->assertSame('Activity 1', $result[2][0]['depname']);
    }

    /**
     * Multiple prerequisites with mixed states produce multiple issues.
     */
    public function test_multiple_prereqs_produce_multiple_issues(): void {
        $this->resetAfterTest();
        $avail = json_encode([
            'op' => '&',
            'c' => [
                ['type' => 'completion', 'cm' => 1, 'e' => 1],
                ['type' => 'completion', 'cm' => 999, 'e' => 1],
            ],
            'show' => false,
        ]);
        $cms = [
            1 => $this->make_cm(1, 0),
            3 => $this->make_cm(3, 2, $avail),
        ];
        $depindex = new dependency_index($cms);
        $analyzer = new reachability_analyzer();
        $result = $analyzer->analyze($cms, $depindex);
        $this->assertArrayHasKey(3, $result);
        $this->assertCount(2, $result[3]);
        $types = array_column($result[3], 'issuetype');
        $this->assertContains('impossible_dep', $types);
        $this->assertContains('dangling_dep', $types);
    }

    /**
     * CM with completion tracking enabled (1 = manual) is not flagged as impossible.
     */
    public function test_manual_completion_not_impossible(): void {
        $this->resetAfterTest();
        $cms = [
            1 => $this->make_cm(1, 1),
            2 => $this->make_cm(2, 2, $this->avail_requires(1)),
        ];
        $depindex = new dependency_index($cms);
        $analyzer = new reachability_analyzer();
        $result = $analyzer->analyze($cms, $depindex);
        $this->assertEmpty($result);
    }

    /**
     * The CM providing the prerequisite is not itself flagged.
     */
    public function test_provider_cm_not_flagged(): void {
        $this->resetAfterTest();
        $cms = [
            1 => $this->make_cm(1, 0),
            2 => $this->make_cm(2, 2, $this->avail_requires(1)),
        ];
        $depindex = new dependency_index($cms);
        $analyzer = new reachability_analyzer();
        $result = $analyzer->analyze($cms, $depindex);
        $this->assertArrayNotHasKey(1, $result);
    }
}
