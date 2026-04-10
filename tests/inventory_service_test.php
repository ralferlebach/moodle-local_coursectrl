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
 * Integration tests for the inventory service.
 *
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl;

use local_coursectrl\local\entity\cm_item;
use local_coursectrl\local\entity\course_item;
use local_coursectrl\local\entity\section_item;
use local_coursectrl\local\entity\text_item;
use local_coursectrl\local\inventory\inventory_service;
use local_coursectrl\local\inventory\inventory_snapshot;

/**
 * Unit tests for local_coursectrl\local\inventory\inventory_service.
 *
 * @coversDefaultClass \local_coursectrl\local\inventory\inventory_service
 */
final class inventory_service_test extends \advanced_testcase
{
    /**
     * A minimal course must be inventoried with correct course metadata.
     */
    public function test_build_for_course_returns_course_item(): void
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Inventory Service Test Course',
            'shortname' => 'INV-TEST-1',
            'summary' => '<p>Welcome to the test course.</p>',
            'summaryformat' => FORMAT_HTML,
            'startdate' => 1700000000,
        ]);

        $service = new inventory_service();
        $snapshot = $service->build_for_course((int) $course->id);

        $this->assertInstanceOf(inventory_snapshot::class, $snapshot);
        $this->assertInstanceOf(course_item::class, $snapshot->course);
        $this->assertSame((int) $course->id, $snapshot->course->id);
        $this->assertSame('Inventory Service Test Course', $snapshot->course->fullname);
        $this->assertSame('INV-TEST-1', $snapshot->course->shortname);
        $this->assertStringContainsString('Welcome', $snapshot->course->summary);
        $this->assertSame(1700000000, $snapshot->course->startdate);
        $this->assertTrue($snapshot->course->visible);
    }

    /**
     * A course with activities must report each course module as a cm_item
     * keyed by cmid, with the correct component resolution.
     */
    public function test_build_for_course_returns_cm_items(): void
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'name' => 'Homework 1',
        ]);
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'name' => 'Quiz 1',
        ]);

        $service = new inventory_service();
        $snapshot = $service->build_for_course((int) $course->id);

        $this->assertSame(2, $snapshot->count_cms());
        $this->assertArrayHasKey((int) $assign->cmid, $snapshot->cms);
        $this->assertArrayHasKey((int) $quiz->cmid, $snapshot->cms);

        /** @var cm_item $assigncm */
        $assigncm = $snapshot->cms[(int) $assign->cmid];
        $this->assertSame('assign', $assigncm->modname);
        $this->assertSame('mod_assign', $assigncm->get_component());
        $this->assertSame('Homework 1', $assigncm->name);
        $this->assertSame((int) $course->id, $assigncm->courseid);

        /** @var cm_item $quizcm */
        $quizcm = $snapshot->cms[(int) $quiz->cmid];
        $this->assertSame('mod_quiz', $quizcm->get_component());
    }

    /**
     * Every course has at least the implicit section 0; the service must
     * report sections keyed by their row id and ordered by section number.
     */
    public function test_build_for_course_returns_sections(): void
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['numsections' => 3]);

        $service = new inventory_service();
        $snapshot = $service->build_for_course((int) $course->id);

        $this->assertGreaterThanOrEqual(1, $snapshot->count_sections());
        foreach ($snapshot->sections as $sectionid => $section) {
            $this->assertInstanceOf(section_item::class, $section);
            $this->assertSame($sectionid, $section->id);
            $this->assertSame((int) $course->id, $section->courseid);
        }
    }

    /**
     * The course summary must be collected as a text_item under its
     * canonical composite key.
     */
    public function test_build_for_course_collects_course_summary_text(): void
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course([
            'summary' => 'Deadline: next Friday.',
            'summaryformat' => FORMAT_PLAIN,
        ]);

        $service = new inventory_service();
        $snapshot = $service->build_for_course((int) $course->id);

        $key = text_item::OWNER_COURSE.':'.(int) $course->id.':summary';
        $this->assertArrayHasKey($key, $snapshot->texts);

        $text = $snapshot->texts[$key];
        $this->assertSame('Deadline: next Friday.', $text->content);
        $this->assertSame(text_item::OWNER_COURSE, $text->entitytype);
        $this->assertSame('summary', $text->fieldname);
    }

    /**
     * Non-existent courses must raise a Moodle DB exception.
     */
    public function test_build_for_course_throws_on_missing_course(): void
    {
        $this->resetAfterTest();

        $service = new inventory_service();
        $this->expectException(\dml_missing_record_exception::class);
        $service->build_for_course(999999);
    }

    /**
     * The snapshot must round-trip through JSON serialisation.
     */
    public function test_snapshot_is_json_serializable(): void
    {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['summary' => 'x']);
        $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        $service = new inventory_service();
        $snapshot = $service->build_for_course((int) $course->id);

        $json = json_encode($snapshot);
        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('course', $decoded);
        $this->assertArrayHasKey('sections', $decoded);
        $this->assertArrayHasKey('cms', $decoded);
        $this->assertArrayHasKey('texts', $decoded);
    }
}
