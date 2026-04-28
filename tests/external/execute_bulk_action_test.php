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
 * Tests for the execute_bulk_action external function.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\external;

use core_external\external_api;
use local_coursectrl\local\persistent\batch;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\external\execute_bulk_action::class)]
/**
 * Integration tests for execute_bulk_action including capability
 * enforcement, DB mutation verification and result structure validation.
 *
 * @covers \local_coursectrl\external\execute_bulk_action
 */
final class execute_bulk_action_test extends \advanced_testcase {
    /** @var int Reference timestamp. */
    private const BASE_TIME = 1700000000;

    /** @var int One-day delta in seconds. */
    private const ONE_DAY = 86400;

    /**
     * An editing teacher must be able to execute and get a valid result.
     * @covers \local_coursectrl\external\execute_bulk_action
     */
    public function test_execute_returns_batch_for_teacher(): void {
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
        $result = execute_bulk_action::execute(
            (int)$course->id,
            'shift_dates',
            json_encode(['delta' => self::ONE_DAY]),
            [(int)$assign->cmid]
        );
        $result = external_api::clean_returnvalue(execute_bulk_action::execute_returns(), $result);

        $this->assertGreaterThan(0, $result['batchid']);
        $this->assertSame('executed', $result['status']);
        $this->assertSame(1, $result['summary']['total']);
        $this->assertSame(1, $result['summary']['success']);
        $this->assertSame(0, $result['summary']['error']);
    }

    /**
     * The DB must reflect the shifted dates after a successful execute call.
     * @covers \local_coursectrl\external\execute_bulk_action
     */
    public function test_execute_mutates_db_state(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $assign = $this->getDataGenerator()->get_plugin_generator('mod_assign')->create_instance([
            'course'  => $course->id,
            'duedate' => self::BASE_TIME,
        ]);

        $this->setUser($teacher);
        execute_bulk_action::execute(
            (int)$course->id,
            'shift_dates',
            json_encode(['delta' => self::ONE_DAY]),
            [(int)$assign->cmid]
        );

        $record = $DB->get_record('assign', ['id' => $assign->id]);
        $this->assertSame(self::BASE_TIME + self::ONE_DAY, (int)$record->duedate);
    }

    /**
     * An enrolled student without the bulkaction cap must be rejected.
     * @covers \local_coursectrl\external\execute_bulk_action
     */
    public function test_execute_rejects_student(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $this->setUser($student);
        $this->expectException(\core\exception\required_capability_exception::class);
        execute_bulk_action::execute(
            (int)$course->id,
            'shift_dates',
            json_encode(['delta' => self::ONE_DAY]),
            []
        );
    }

    /**
     * A non-enrolled user must be rejected by validate_context.
     * @covers \local_coursectrl\external\execute_bulk_action
     */
    public function test_execute_rejects_unenrolled_user(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $this->setUser($user);
        $this->expectException(\core\exception\require_login_exception::class);
        execute_bulk_action::execute(
            (int)$course->id,
            'shift_dates',
            json_encode(['delta' => self::ONE_DAY]),
            []
        );
    }

    /**
     * The batch row persisted by execute must be reloadable by id.
     * @covers \local_coursectrl\external\execute_bulk_action
     */
    public function test_batch_row_is_persisted(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course'    => $course->id,
            'timeopen'  => self::BASE_TIME,
            'timeclose' => self::BASE_TIME + self::ONE_DAY,
        ]);

        $this->setUser($teacher);
        $result = execute_bulk_action::execute(
            (int)$course->id,
            'shift_dates',
            json_encode(['delta' => self::ONE_DAY]),
            []
        );
        $result = external_api::clean_returnvalue(execute_bulk_action::execute_returns(), $result);

        $batchrow = new batch($result['batchid']);
        $this->assertSame((int)$course->id, $batchrow->get('courseid'));
        $this->assertSame('shift_dates', $batchrow->get('action'));
        $this->assertSame((int)$teacher->id, $batchrow->get('userid'));
    }
}
