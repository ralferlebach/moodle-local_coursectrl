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
 * Tests for the group-condition extensions of reachability_analyzer.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\analysis\reachability_analyzer::class)]
/**
 * Tests for reachability_analyzer group/grouping checks.
 *
 * @covers \local_coursectrl\local\analysis\reachability_analyzer
 */
final class reachability_analyzer_group_test extends \advanced_testcase {
    /**
     * Build a cm_item with the given availability JSON.
     *
     * @param int    $cmid  CM id.
     * @param string $avail Availability JSON string.
     * @return cm_item
     */
    private function make_cm(int $cmid, string $avail = ''): cm_item {
        return new cm_item(
            $cmid,
            1,
            10,
            'assign',
            $cmid,
            'Activity ' . $cmid,
            true,
            $avail !== '' ? $avail : null,
            2
        );
    }

    /**
     * Build availability JSON requiring a specific group.
     *
     * @param int $groupid Moodle group id.
     * @return string
     */
    private function avail_group(int $groupid): string {
        return json_encode([
            'op' => '&',
            'c' => [['type' => 'group', 'id' => $groupid]],
            'show' => false,
        ]);
    }

    /**
     * Build availability JSON requiring a specific grouping.
     *
     * @param int $groupingid Moodle grouping id.
     * @return string
     */
    private function avail_grouping(int $groupingid): string {
        return json_encode([
            'op' => '&',
            'c' => [['type' => 'grouping', 'id' => $groupingid]],
            'show' => false,
        ]);
    }

    /**
     * No dangling_group issue when no group_resolver is passed.
     * @covers \local_coursectrl\local\analysis\reachability_analyzer
     * @return void
     */
    public function test_no_resolver_skips_group_checks(): void {
        $this->resetAfterTest();
        $cms = [1 => $this->make_cm(1, $this->avail_group(9999))];
        $depindex = new dependency_index($cms);
        $analyzer = new reachability_analyzer();
        $result = $analyzer->analyze($cms, $depindex);
        // Without a resolver no group issues are raised.
        $this->assertEmpty($result);
    }

    /**
     * dangling_group is raised when group does not exist in the course.
     * @covers \local_coursectrl\local\analysis\reachability_analyzer
     * @return void
     */
    public function test_dangling_group_raised_for_missing_group(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $cms = [1 => $this->make_cm(1, $this->avail_group(9999))];
        $depindex = new dependency_index($cms);
        $resolver = new group_resolver((int) $course->id);
        $analyzer = new reachability_analyzer();
        $result = $analyzer->analyze($cms, $depindex, $resolver);
        $this->assertArrayHasKey(1, $result);
        $types = array_column($result[1], 'issuetype');
        $this->assertContains('dangling_group', $types);
    }

    /**
     * No dangling_group when the required group exists.
     * @covers \local_coursectrl\local\analysis\reachability_analyzer
     * @return void
     */
    public function test_no_dangling_group_when_group_exists(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $cms = [1 => $this->make_cm(1, $this->avail_group((int) $group->id))];
        $depindex = new dependency_index($cms);
        $resolver = new group_resolver((int) $course->id);
        $analyzer = new reachability_analyzer();
        $result = $analyzer->analyze($cms, $depindex, $resolver);
        // Group exists — no dangling_group issue.
        $types = [];
        foreach ($result[1] ?? [] as $issue) {
            $types[] = $issue['issuetype'];
        }
        $this->assertNotContains('dangling_group', $types);
    }

    /**
     * dangling_grouping is raised when grouping does not exist.
     * @covers \local_coursectrl\local\analysis\reachability_analyzer
     * @return void
     */
    public function test_dangling_grouping_raised_for_missing_grouping(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $cms = [2 => $this->make_cm(2, $this->avail_grouping(9999))];
        $depindex = new dependency_index($cms);
        $resolver = new group_resolver((int) $course->id);
        $analyzer = new reachability_analyzer();
        $result = $analyzer->analyze($cms, $depindex, $resolver);
        $this->assertArrayHasKey(2, $result);
        $types = array_column($result[2], 'issuetype');
        $this->assertContains('dangling_grouping', $types);
    }

    /**
     * Existing grouping produces no dangling_grouping issue.
     * @covers \local_coursectrl\local\analysis\reachability_analyzer
     * @return void
     */
    public function test_no_dangling_grouping_when_grouping_exists(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id]);
        $cms = [2 => $this->make_cm(2, $this->avail_grouping((int) $grouping->id))];
        $depindex = new dependency_index($cms);
        $resolver = new group_resolver((int) $course->id);
        $analyzer = new reachability_analyzer();
        $result = $analyzer->analyze($cms, $depindex, $resolver);
        $types = [];
        foreach ($result[2] ?? [] as $issue) {
            $types[] = $issue['issuetype'];
        }
        $this->assertNotContains('dangling_grouping', $types);
    }
}
