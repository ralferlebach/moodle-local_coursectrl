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
 * Tests for the preview_bulk_action external function.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\external;

use core_external\external_api;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\external\preview_bulk_action::class)]
/**
 * Integration tests for preview_bulk_action including capability
 * enforcement and result structure validation.
 *
 * @covers \local_coursectrl\external\preview_bulk_action
 */
final class preview_bulk_action_test extends \advanced_testcase {
    /** @var int Reference timestamp. */
    private const BASE_TIME = 1700000000;

    /** @var int One-day delta in seconds. */
    private const ONE_DAY = 86400;

    /**
     * An enrolled editing teacher must receive a structurally valid preview.
     * @covers \local_coursectrl\external\preview_bulk_action
     */
    public function test_execute_returns_preview_for_teacher(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $assign = $this->getDataGenerator()->get_plugin_generator('mod_assign')->create_instance([
            'course'  => $course->id,
            'name'    => 'A1',
            'duedate' => self::BASE_TIME,
        ]);

        $this->setUser($teacher);
        $result = preview_bulk_action::execute(
            (int)$course->id,
            'shift_dates',
            json_encode(['delta' => self::ONE_DAY]),
            [(int)$assign->cmid]
        );
        $result = external_api::clean_returnvalue(preview_bulk_action::execute_returns(), $result);

        $this->assertSame('shift_dates', $result['action']);
        $this->assertCount(1, $result['changes']);
        $this->assertSame((int)$assign->cmid, $result['changes'][0]['cmid']);
        $this->assertSame('mod_assign', $result['changes'][0]['component']);
        $this->assertTrue($result['changes'][0]['haschanges']);
        $this->assertSame(1, $result['summary']['changes']);
        $this->assertSame(0, $result['summary']['errors']);
    }

    /**
     * A non-enrolled user must be rejected by validate_context.
     * @covers \local_coursectrl\external\preview_bulk_action
     */
    public function test_execute_rejects_unenrolled_user(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $this->setUser($user);
        $this->expectException(\core\exception\require_login_exception::class);
        preview_bulk_action::execute(
            (int)$course->id,
            'shift_dates',
            json_encode(['delta' => self::ONE_DAY]),
            []
        );
    }

    /**
     * An enrolled student without the view cap must be rejected.
     * @covers \local_coursectrl\external\preview_bulk_action
     */
    public function test_execute_rejects_enrolled_student(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $this->setUser($student);
        $this->expectException(\core\exception\required_capability_exception::class);
        preview_bulk_action::execute(
            (int)$course->id,
            'shift_dates',
            json_encode(['delta' => self::ONE_DAY]),
            []
        );
    }

    /**
     * Skipped items (e.g. labels) appear in the skipped list, not in errors.
     * @covers \local_coursectrl\external\preview_bulk_action
     */
    public function test_skipped_items_for_unsupported_cmids(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $label = $this->getDataGenerator()->create_module('label', [
            'course' => $course->id,
        ]);

        $this->setUser($teacher);
        $result = preview_bulk_action::execute(
            (int)$course->id,
            'shift_dates',
            json_encode(['delta' => self::ONE_DAY]),
            [(int)$label->cmid]
        );
        $result = external_api::clean_returnvalue(preview_bulk_action::execute_returns(), $result);

        $this->assertCount(0, $result['changes']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame('no_adapter', $result['skipped'][0]['reason']);
    }

    /**
     * Empty cmids list previews the entire course.
     * @covers \local_coursectrl\external\preview_bulk_action
     */
    public function test_empty_cmids_previews_whole_course(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->get_plugin_generator('mod_assign')->create_instance([
            'course'  => $course->id,
            'duedate' => self::BASE_TIME,
        ]);
        $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course'    => $course->id,
            'timeopen'  => self::BASE_TIME,
            'timeclose' => self::BASE_TIME + self::ONE_DAY,
        ]);

        $this->setUser($teacher);
        $result = preview_bulk_action::execute(
            (int)$course->id,
            'shift_dates',
            json_encode(['delta' => self::ONE_DAY]),
            []
        );
        $result = external_api::clean_returnvalue(preview_bulk_action::execute_returns(), $result);

        $this->assertGreaterThanOrEqual(2, $result['summary']['changes']);
    }

    /**
     * preview_bulk_action must reject CMIDs from a foreign course.
     * preview_manager::build() throws invalidcmid when a CMID does not
     * belong to the requested course.
     * @covers \local_coursectrl\external\preview_bulk_action
     */
    public function test_preview_filters_cmid_from_foreign_course(): void {
        $this->resetAfterTest();

        $course1  = $this->getDataGenerator()->create_course();
        $course2  = $this->getDataGenerator()->create_course();
        $teacher  = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course1->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($teacher->id, $course2->id, 'editingteacher');

        $assign2 = $this->getDataGenerator()
            ->get_plugin_generator('mod_assign')
            ->create_instance(['course' => $course2->id, 'duedate' => self::BASE_TIME]);

        $this->setUser($teacher);
        $this->expectException(\moodle_exception::class);
        preview_bulk_action::execute(
            (int) $course1->id,
            'shift_dates',
            json_encode(['delta' => self::ONE_DAY]),
            [(int) $assign2->cmid]
        );
    }
}
