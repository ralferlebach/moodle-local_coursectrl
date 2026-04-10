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
 * Unit tests for the bulk-pipeline DTOs.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\dto;

/**
 * Tests preview_change, validation_result and execution_result.
 *
 * @covers \local_coursectrl\local\dto\preview_change
 * @covers \local_coursectrl\local\dto\validation_result
 * @covers \local_coursectrl\local\dto\execution_result
 */
final class dtos_test extends \advanced_testcase {
    /**
     * preview_change exposes its constructor inputs unchanged.
     */
    public function test_preview_change_getters(): void {
        $fields = [
            'duedate' => ['old' => 100, 'new' => 200, 'shifted' => true],
        ];
        $change = new preview_change(42, 'mod_assign', 'My Assignment', $fields);
        $this->assertSame(42, $change->get_cmid());
        $this->assertSame('mod_assign', $change->get_component());
        $this->assertSame('My Assignment', $change->get_name());
        $this->assertSame($fields, $change->get_fields());
    }

    /**
     * preview_change::has_changes returns true when at least one field is
     * marked as shifted, and false otherwise.
     */
    public function test_preview_change_has_changes(): void {
        $changed = new preview_change(1, 'mod_assign', 'A', [
            'duedate' => ['old' => 1, 'new' => 2, 'shifted' => true],
        ]);
        $this->assertTrue($changed->has_changes());

        $unchanged = new preview_change(1, 'mod_assign', 'A', [
            'duedate' => ['old' => 0, 'new' => 0, 'shifted' => false, 'reason' => 'unset'],
        ]);
        $this->assertFalse($unchanged->has_changes());
    }

    /**
     * preview_change::to_array round-trips through the four canonical keys.
     */
    public function test_preview_change_to_array(): void {
        $change = new preview_change(7, 'mod_quiz', 'Q', ['timeopen' => ['old' => 1, 'new' => 2]]);
        $this->assertSame(
            [
                'cmid'      => 7,
                'component' => 'mod_quiz',
                'name'      => 'Q',
                'fields'    => ['timeopen' => ['old' => 1, 'new' => 2]],
            ],
            $change->to_array()
        );
    }

    /**
     * validation_result::from_adapter_array reads the canonical adapter shape.
     */
    public function test_validation_result_from_adapter_array(): void {
        $valid = validation_result::from_adapter_array([
            'valid'  => true,
            'errors' => [],
            'cmids'  => ['1', 2, '3'],
        ]);
        $this->assertTrue($valid->is_valid());
        $this->assertSame([], $valid->get_errors());
        $this->assertSame([1, 2, 3], $valid->get_cmids());

        $invalid = validation_result::from_adapter_array([
            'valid'  => false,
            'errors' => [['code' => 'invalid_delta']],
        ]);
        $this->assertFalse($invalid->is_valid());
        $this->assertSame('invalid_delta', $invalid->get_errors()[0]['code']);
        $this->assertSame([], $invalid->get_cmids());
    }

    /**
     * execution_result captures cmid, status, snapshot, changed list and
     * optional message and round-trips through to_array.
     */
    public function test_execution_result_getters_and_to_array(): void {
        $snapshot = ['component' => 'mod_assign', 'cmid' => 9, 'fields' => ['duedate' => 1]];
        $result = new execution_result(9, execution_result::STATUS_OK, $snapshot, ['duedate']);
        $this->assertSame(9, $result->get_cmid());
        $this->assertSame(execution_result::STATUS_OK, $result->get_status());
        $this->assertSame($snapshot, $result->get_snapshot());
        $this->assertSame(['duedate'], $result->get_changed());
        $this->assertNull($result->get_message());
        $array = $result->to_array();
        $this->assertSame(9, $array['cmid']);
        $this->assertSame('ok', $array['status']);
        $this->assertSame($snapshot, $array['snapshot']);
        $this->assertSame(['duedate'], $array['changed']);
        $this->assertNull($array['message']);
    }

    /**
     * A failed execution_result carries a message and the failed status.
     */
    public function test_execution_result_failed_with_message(): void {
        $result = new execution_result(
            5,
            execution_result::STATUS_FAILED,
            [],
            [],
            'database write failed'
        );
        $this->assertSame(execution_result::STATUS_FAILED, $result->get_status());
        $this->assertSame('database write failed', $result->get_message());
    }
}
