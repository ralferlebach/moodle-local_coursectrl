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
 * Tests for group_resolver.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

/**
 * Unit tests for group_resolver using the Moodle test DB.
 *
 * @covers \local_coursectrl\local\analysis\group_resolver
 */
final class group_resolver_test extends \advanced_testcase {
    /**
     * Returns false for a group that does not exist in the course.
     */
    public function test_group_exists_returns_false_for_unknown_group(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $resolver = new group_resolver((int) $course->id);
        $this->assertFalse($resolver->group_exists(99999));
    }

    /**
     * Returns true for a group that was created in the course.
     */
    public function test_group_exists_returns_true_for_known_group(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $resolver = new group_resolver((int) $course->id);
        $this->assertTrue($resolver->group_exists((int) $group->id));
    }

    /**
     * Returns false for a grouping that does not exist in the course.
     */
    public function test_grouping_exists_returns_false_for_unknown(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $resolver = new group_resolver((int) $course->id);
        $this->assertFalse($resolver->grouping_exists(99999));
    }

    /**
     * Returns true for a grouping that was created in the course.
     */
    public function test_grouping_exists_returns_true_for_known(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id]);
        $resolver = new group_resolver((int) $course->id);
        $this->assertTrue($resolver->grouping_exists((int) $grouping->id));
    }

    /**
     * get_groups_for_template returns all course groups as id/name pairs.
     */
    public function test_get_groups_for_template_returns_all_groups(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Alpha']);
        $this->getDataGenerator()->create_group(['courseid' => $course->id, 'name' => 'Beta']);
        $resolver = new group_resolver((int) $course->id);
        $groups = $resolver->get_groups_for_template();
        $this->assertCount(2, $groups);
        $names = array_column($groups, 'name');
        $this->assertContains('Alpha', $names);
        $this->assertContains('Beta', $names);
    }

    /**
     * get_group_name returns null for an unknown group id.
     */
    public function test_get_group_name_returns_null_for_unknown(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $resolver = new group_resolver((int) $course->id);
        $this->assertNull($resolver->get_group_name(99999));
    }

    /**
     * get_group_name returns the group name for a known group.
     */
    public function test_get_group_name_returns_name(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group([
            'courseid' => $course->id,
            'name' => 'TestGroup',
        ]);
        $resolver = new group_resolver((int) $course->id);
        $this->assertSame('TestGroup', $resolver->get_group_name((int) $group->id));
    }

    /**
     * Data from a different course is not returned.
     */
    public function test_groups_are_scoped_to_course(): void {
        $this->resetAfterTest();
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course1->id]);
        $resolver = new group_resolver((int) $course2->id);
        $this->assertFalse($resolver->group_exists((int) $group->id));
    }
}
