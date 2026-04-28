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
 * Tests for the text_hit persistent.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\persistent;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\persistent\text_hit::class)]
/**
 * Unit tests for the text_hit persistent.
 *
 * @covers \local_coursectrl\local\persistent\text_hit
 */
final class text_hit_test extends \advanced_testcase {
    /**
     * A text_hit row must persist and load with all fields intact.
     * @covers \local_coursectrl\local\persistent\text_hit
     */
    public function test_create_and_read(): void {
        $this->resetAfterTest();

        $hit = new text_hit(0, (object) [
            'courseid' => 1,
            'entitytype' => 'section',
            'entityid' => 10,
            'fieldname' => 'summary',
            'matchedtext' => '15. April 2026',
            'normalizedvalue' => '2026-04-15',
            'confidence' => text_hit::CONFIDENCE_SAFE,
            'contextjson' => json_encode(['before' => 'Abgabe bis ', 'after' => ' bitte.']),
        ]);
        $hit->create();
        $this->assertGreaterThan(0, $hit->get('id'));

        $loaded = new text_hit($hit->get('id'));
        $this->assertSame(1, $loaded->get('courseid'));
        $this->assertSame('section', $loaded->get('entitytype'));
        $this->assertSame(10, $loaded->get('entityid'));
        $this->assertSame('summary', $loaded->get('fieldname'));
        $this->assertSame('15. April 2026', $loaded->get('matchedtext'));
        $this->assertSame('2026-04-15', $loaded->get('normalizedvalue'));
        $this->assertSame(text_hit::CONFIDENCE_SAFE, $loaded->get('confidence'));
    }

    /**
     * Default confidence must be 'ambiguous'.
     * @covers \local_coursectrl\local\persistent\text_hit
     */
    public function test_default_confidence(): void {
        $this->resetAfterTest();

        $hit = new text_hit(0, (object) [
            'courseid' => 1,
            'entitytype' => 'cm',
            'entityid' => 100,
            'fieldname' => 'intro',
            'matchedtext' => '15. April',
        ]);
        $hit->create();

        $this->assertSame(text_hit::CONFIDENCE_AMBIGUOUS, $hit->get('confidence'));
    }

    /**
     * Nullable fields must accept null values.
     * @covers \local_coursectrl\local\persistent\text_hit
     */
    public function test_nullable_fields(): void {
        $this->resetAfterTest();

        $hit = new text_hit(0, (object) [
            'courseid' => 1,
            'entitytype' => 'course',
            'entityid' => 1,
            'fieldname' => 'summary',
            'matchedtext' => 'April 15',
            'normalizedvalue' => null,
            'contextjson' => null,
        ]);
        $hit->create();

        $loaded = new text_hit($hit->get('id'));
        $this->assertNull($loaded->get('normalizedvalue'));
        $this->assertNull($loaded->get('contextjson'));
    }

    /**
     * get_records must filter by courseid.
     * @covers \local_coursectrl\local\persistent\text_hit
     */
    public function test_filter_by_course(): void {
        $this->resetAfterTest();

        foreach ([1, 1, 2] as $courseid) {
            $hit = new text_hit(0, (object) [
                'courseid' => $courseid,
                'entitytype' => 'section',
                'entityid' => 10,
                'fieldname' => 'summary',
                'matchedtext' => 'some date',
            ]);
            $hit->create();
        }

        $course1hits = text_hit::get_records(['courseid' => 1]);
        $this->assertCount(2, $course1hits);

        $course2hits = text_hit::get_records(['courseid' => 2]);
        $this->assertCount(1, $course2hits);
    }
}
