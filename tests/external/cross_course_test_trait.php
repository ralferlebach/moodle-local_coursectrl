<?php
// This file is part of Moodle - https://moodle.org/
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
 * Shared setup helpers for cross-course permission tests.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\external;

/**
 * Trait for setting up a teacher enrolled in two courses with an assign in the second course.
 */
trait cross_course_test_trait {
    /**
     * Create two courses with a teacher enrolled in both, and an assign in the second course.
     *
     * @param int $basetime Base timestamp for the assign duedate.
     * @return array{course1:\stdClass, course2:\stdClass, teacher:\stdClass, assign2:\stdClass}
     */
    private function setup_cross_course_teacher_assign(int $basetime): array {
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course1->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($teacher->id, $course2->id, 'editingteacher');
        $assign2 = $this->getDataGenerator()
            ->get_plugin_generator('mod_assign')
            ->create_instance(['course' => $course2->id, 'duedate' => $basetime]);
        return [
            'course1' => $course1,
            'course2' => $course2,
            'teacher' => $teacher,
            'assign2' => $assign2,
        ];
    }
}
