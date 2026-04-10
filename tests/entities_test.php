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
 * Unit tests for the inventory entity DTOs.
 *
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl;

use local_coursectrl\local\entity\cm_item;
use local_coursectrl\local\entity\course_item;
use local_coursectrl\local\entity\inventory_item;
use local_coursectrl\local\entity\section_item;
use local_coursectrl\local\entity\text_item;

/**
 * Covers the four Phase 2 entity DTOs.
 *
 * @coversNothing
 */
final class entities_test extends \advanced_testcase
{
    /**
     * The course_item must round-trip through to_array()/from_array() losslessly.
     */
    public function test_course_item_roundtrip(): void
    {
        $item = new course_item(
            42,
            'Intro to Testing',
            'TEST101',
            '<p>Course summary</p>',
            1,
            1700000000,
            1710000000,
            true
        );
        $this->assertSame('course', $item->get_type());
        $this->assertInstanceOf(inventory_item::class, $item);

        $array = $item->to_array();
        $this->assertSame('course', $array['type']);
        $this->assertSame(42, $array['id']);

        $rebuilt = course_item::from_array($array);
        $this->assertSame($item->to_array(), $rebuilt->to_array());
    }

    /**
     * A course_item with a null enddate must keep the null through serialisation.
     */
    public function test_course_item_nullable_enddate(): void
    {
        $item = new course_item(1, 'A', 'A', '', 1, 0, null, false);
        $this->assertNull($item->enddate);
        $rebuilt = course_item::from_array($item->to_array());
        $this->assertNull($rebuilt->enddate);
        $this->assertFalse($rebuilt->visible);
    }

    /**
     * The course_item factory must throw when required keys are missing.
     */
    public function test_course_item_missing_key_throws(): void
    {
        $this->expectException(\coding_exception::class);
        course_item::from_array(['id' => 1]);
    }

    /**
     * The section_item must round-trip with a null name.
     */
    public function test_section_item_roundtrip_with_null_name(): void
    {
        $item = new section_item(7, 42, 3, null, 'Week 3', 1, true);
        $this->assertSame('section', $item->get_type());
        $rebuilt = section_item::from_array($item->to_array());
        $this->assertNull($rebuilt->name);
        $this->assertSame(3, $rebuilt->sectionnum);
        $this->assertSame(42, $rebuilt->courseid);
    }

    /**
     * The section_item must round-trip with an explicit name.
     */
    public function test_section_item_roundtrip_with_name(): void
    {
        $item = new section_item(8, 42, 4, 'Week 4 - Midterm', '', 1, false);
        $rebuilt = section_item::from_array($item->to_array());
        $this->assertSame('Week 4 - Midterm', $rebuilt->name);
        $this->assertFalse($rebuilt->visible);
    }

    /**
     * The cm_item must round-trip and expose a correct component helper.
     */
    public function test_cm_item_roundtrip_and_component(): void
    {
        $item = new cm_item(
            101,
            42,
            8,
            'assign',
            55,
            'Homework 1',
            true,
            '{"op":"&","c":[]}',
            2
        );
        $this->assertSame('cm', $item->get_type());
        $this->assertSame('mod_assign', $item->get_component());

        $rebuilt = cm_item::from_array($item->to_array());
        $this->assertSame($item->to_array(), $rebuilt->to_array());
        $this->assertSame('mod_assign', $rebuilt->get_component());
    }

    /**
     * A cm_item may carry a null availability string.
     */
    public function test_cm_item_null_availability(): void
    {
        $item = new cm_item(102, 42, 8, 'quiz', 9, 'Quiz 1', true, null, 0);
        $this->assertNull($item->availability);
        $rebuilt = cm_item::from_array($item->to_array());
        $this->assertNull($rebuilt->availability);
    }

    /**
     * The cm_item factory must throw when required keys are missing.
     */
    public function test_cm_item_missing_key_throws(): void
    {
        $this->expectException(\coding_exception::class);
        // Key 'name' is intentionally missing from the payload.
        cm_item::from_array([
            'id' => 1,
            'courseid' => 2,
            'sectionid' => 3,
            'modname' => 'assign',
            'instance' => 4,
        ]);
    }

    /**
     * The text_item must round-trip and expose its canonical composite key.
     */
    public function test_text_item_roundtrip_and_key(): void
    {
        $item = new text_item(
            text_item::OWNER_SECTION,
            8,
            'summary',
            'Deadline is next Friday.',
            1
        );
        $this->assertSame('text', $item->get_type());
        $this->assertSame('section:8:summary', $item->get_key());

        $rebuilt = text_item::from_array($item->to_array());
        $this->assertSame($item->to_array(), $rebuilt->to_array());
        $this->assertSame('section:8:summary', $rebuilt->get_key());
    }

    /**
     * All four text_item owner constants must be distinct strings.
     */
    public function test_text_item_owner_constants_are_distinct(): void
    {
        $owners = [
            text_item::OWNER_COURSE,
            text_item::OWNER_SECTION,
            text_item::OWNER_CM,
            text_item::OWNER_LABEL,
        ];
        $this->assertSame($owners, array_unique($owners));
        $this->assertCount(4, $owners);
    }

    /**
     * Every entity must be encodable via json_encode through JsonSerializable.
     */
    public function test_entities_are_json_serializable(): void
    {
        $entities = [
            new course_item(1, 'f', 's', '', 1, 0, null, true),
            new section_item(2, 1, 0, 'General', '', 1, true),
            new cm_item(3, 1, 2, 'assign', 1, 'n', true, null, 0),
            new text_item(text_item::OWNER_COURSE, 1, 'summary', 'x', 1),
        ];
        foreach ($entities as $entity) {
            $json = json_encode($entity);
            $this->assertIsString($json);
            $this->assertNotFalse($json);
            $decoded = json_decode($json, true);
            $this->assertIsArray($decoded);
            $this->assertSame($entity->to_array(), $decoded);
        }
    }
}
