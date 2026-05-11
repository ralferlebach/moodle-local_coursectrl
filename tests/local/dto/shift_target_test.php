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
 * Unit tests for the shift_target DTO.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\dto;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\dto\shift_target::class)]
/**
 * Tests for shift_target: source resolution, from_array, from_json_array, to_array.
 *
 * @covers \local_coursectrl\local\dto\shift_target
 */
final class shift_target_test extends \advanced_testcase {
    /**
     * Adapter fields return SOURCE_ADAPTER.
     * @covers \local_coursectrl\local\dto\shift_target
     */
    public function test_resolve_source_adapter_fields(): void {
        $this->assertSame(shift_target::SOURCE_ADAPTER, shift_target::resolve_source('duedate'));
        $this->assertSame(shift_target::SOURCE_ADAPTER, shift_target::resolve_source('timeopen'));
        $this->assertSame(shift_target::SOURCE_ADAPTER, shift_target::resolve_source('timeclose'));
    }

    /**
     * completionexpected resolves to SOURCE_CM.
     * @covers \local_coursectrl\local\dto\shift_target
     */
    public function test_resolve_source_cm(): void {
        $this->assertSame(shift_target::SOURCE_CM, shift_target::resolve_source('completionexpected'));
    }

    /**
     * Fields prefixed availability_ resolve to SOURCE_AVAILABILITY.
     * @covers \local_coursectrl\local\dto\shift_target
     */
    public function test_resolve_source_availability(): void {
        $this->assertSame(shift_target::SOURCE_AVAILABILITY, shift_target::resolve_source('availability_from_0'));
        $this->assertSame(shift_target::SOURCE_AVAILABILITY, shift_target::resolve_source('availability_until_1'));
    }

    /**
     * from_array with explicit source honours the supplied value.
     * @covers \local_coursectrl\local\dto\shift_target
     */
    public function test_from_array_explicit_source(): void {
        $t = shift_target::from_array(['cmid' => 42, 'source' => 'adapter', 'field' => 'duedate', 'timestamp' => 1700000000]);
        $this->assertSame(42, $t->get_cmid());
        $this->assertSame('adapter', $t->get_source());
        $this->assertSame('duedate', $t->get_field());
        $this->assertSame(1700000000, $t->get_timestamp());
    }

    /**
     * from_array without source infers it from the field name.
     * @covers \local_coursectrl\local\dto\shift_target
     */
    public function test_from_array_infers_source(): void {
        $a = shift_target::from_array(['cmid' => 1, 'field' => 'duedate', 'timestamp' => 0]);
        $this->assertSame(shift_target::SOURCE_ADAPTER, $a->get_source());

        $c = shift_target::from_array(['cmid' => 2, 'field' => 'completionexpected', 'timestamp' => 0]);
        $this->assertSame(shift_target::SOURCE_CM, $c->get_source());

        $av = shift_target::from_array(['cmid' => 3, 'field' => 'availability_from_0', 'timestamp' => 0]);
        $this->assertSame(shift_target::SOURCE_AVAILABILITY, $av->get_source());
    }

    /**
     * from_json_array parses valid JSON into shift_target instances.
     * @covers \local_coursectrl\local\dto\shift_target
     */
    public function test_from_json_array_valid(): void {
        $json = json_encode([
            ['cmid' => 10, 'source' => 'adapter', 'field' => 'duedate', 'timestamp' => 100],
            ['cmid' => 11, 'source' => 'cm', 'field' => 'completionexpected', 'timestamp' => 200],
        ]);
        $targets = shift_target::from_json_array($json);
        $this->assertCount(2, $targets);
        $this->assertSame(10, $targets[0]->get_cmid());
        $this->assertSame(11, $targets[1]->get_cmid());
    }

    /**
     * from_json_array silently skips entries missing cmid or field.
     * @covers \local_coursectrl\local\dto\shift_target
     */
    public function test_from_json_array_skips_invalid(): void {
        $json = json_encode([
            ['cmid' => 1, 'field' => 'duedate', 'timestamp' => 0],
            ['source' => 'adapter', 'field' => 'duedate', 'timestamp' => 0],
            ['cmid' => 3, 'timestamp' => 0],
            'not_an_array',
        ]);
        $targets = shift_target::from_json_array($json);
        $this->assertCount(1, $targets);
        $this->assertSame(1, $targets[0]->get_cmid());
    }

    /**
     * from_json_array returns empty array on malformed JSON.
     * @covers \local_coursectrl\local\dto\shift_target
     */
    public function test_from_json_array_malformed(): void {
        $this->assertSame([], shift_target::from_json_array('not json'));
        $this->assertSame([], shift_target::from_json_array(''));
        $this->assertSame([], shift_target::from_json_array('"string"'));
    }

    /**
     * to_array round-trips exactly {cmid, source, field, timestamp}.
     * @covers \local_coursectrl\local\dto\shift_target
     */
    public function test_to_array_round_trip(): void {
        $data = ['cmid' => 99, 'source' => 'availability', 'field' => 'availability_from_0', 'timestamp' => 999];
        $this->assertSame($data, shift_target::from_array($data)->to_array());
    }

    /**
     * Multiple targets survive encode→from_json_array round-trip.
     * @covers \local_coursectrl\local\dto\shift_target
     */
    public function test_json_round_trip(): void {
        $originals = [
            ['cmid' => 1, 'source' => 'adapter', 'field' => 'duedate', 'timestamp' => 1700000000],
            ['cmid' => 2, 'source' => 'cm', 'field' => 'completionexpected', 'timestamp' => 1700086400],
        ];
        $targets = shift_target::from_json_array(json_encode($originals));
        $this->assertSame($originals, array_map(fn($t) => $t->to_array(), $targets));
    }
}
