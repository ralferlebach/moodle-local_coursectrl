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
 * Tests for the local_coursectrl_get_inventory external function.
 *
 * @package    local_coursectrl
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\external;

use core_external\external_api;

/**
 * Integration tests for the get_inventory external function.
 *
 * @coversDefaultClass \local_coursectrl\external\get_inventory
 */
final class get_inventory_test extends \advanced_testcase {
    /**
     * An enrolled teacher must receive a structurally valid snapshot.
     */
    public function test_execute_returns_snapshot_for_enrolled_teacher(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'External API Test Course',
            'shortname' => 'EXT-API-1',
            'summary' => '<p>Hello.</p>',
            'summaryformat' => FORMAT_HTML,
        ]);
        $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $raw = get_inventory::execute((int) $course->id);
        $clean = external_api::clean_returnvalue(get_inventory::execute_returns(), $raw);

        $this->assertIsArray($clean);
        $this->assertArrayHasKey('course', $clean);
        $this->assertArrayHasKey('sections', $clean);
        $this->assertArrayHasKey('cms', $clean);
        $this->assertArrayHasKey('texts', $clean);

        $this->assertSame((int) $course->id, $clean['course']['id']);
        $this->assertSame('External API Test Course', $clean['course']['fullname']);
        $this->assertGreaterThanOrEqual(1, count($clean['sections']));
        $this->assertCount(1, $clean['cms']);
    }

    /**
     * Anonymous users with no read access must be rejected before any
     * inventory work runs.
     */
    public function test_execute_rejects_unprivileged_user(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $stranger = $this->getDataGenerator()->create_user();
        $this->setUser($stranger);

        $this->expectException(\required_capability_exception::class);
        get_inventory::execute((int) $course->id);
    }

    /**
     * A request for a non-existent course must surface as a context error.
     */
    public function test_execute_rejects_missing_course(): void {
        $this->resetAfterTest();

        $teacher = $this->getDataGenerator()->create_user();
        $this->setUser($teacher);

        $this->expectException(\dml_missing_record_exception::class);
        get_inventory::execute(999999);
    }

    /**
     * A nullable enddate must round-trip cleanly through the schema.
     */
    public function test_execute_handles_null_enddate(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['enddate' => 0]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $raw = get_inventory::execute((int) $course->id);
        $clean = external_api::clean_returnvalue(get_inventory::execute_returns(), $raw);

        $this->assertNull($clean['course']['enddate']);
    }
}
