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
 * Tests for the text_change_builder.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\text;

use local_coursectrl\local\entity\text_item;
use local_coursectrl\local\persistent\text_hit;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\text\text_change_builder::class)]
/**
 * Unit tests for text_change_builder.
 *
 * @covers \local_coursectrl\local\text\text_change_builder
 */
final class text_change_builder_test extends \advanced_testcase {
    /**
     * Scan must return hits for text containing dates.
     * @covers \local_coursectrl\local\text\text_change_builder
     */
    public function test_scan_returns_hits(): void {
        $builder = new text_change_builder();
        $item = new text_item('section', 10, 'summary', 'Abgabe bis 15. April 2026.', FORMAT_HTML);

        $hits = $builder->scan([$item]);

        $this->assertNotEmpty($hits);
        $this->assertSame('section', $hits[0]['entitytype']);
        $this->assertSame(10, $hits[0]['entityid']);
        $this->assertSame('summary', $hits[0]['fieldname']);
        $this->assertNotEmpty($hits[0]['matchedtext']);
        $this->assertSame('2026-04-15', $hits[0]['normalizedvalue']);
        $this->assertSame(text_hit::CONFIDENCE_SAFE, $hits[0]['confidence']);
    }

    /**
     * Scan must return empty for text without dates.
     * @covers \local_coursectrl\local\text\text_change_builder
     */
    public function test_scan_empty_for_no_dates(): void {
        $builder = new text_change_builder();
        $item = new text_item('section', 10, 'summary', 'No dates here.', FORMAT_HTML);

        $hits = $builder->scan([$item]);

        $this->assertEmpty($hits);
    }

    /**
     * Scan must handle multiple text items.
     * @covers \local_coursectrl\local\text\text_change_builder
     */
    public function test_scan_multiple_items(): void {
        $builder = new text_change_builder();
        $items = [
            new text_item('section', 10, 'summary', 'Abgabe 15.04.2026', FORMAT_HTML),
            new text_item('cm', 100, 'intro', 'Due April 15, 2026', FORMAT_HTML),
            new text_item('section', 11, 'summary', 'No date', FORMAT_HTML),
        ];

        $hits = $builder->scan($items);

        $this->assertCount(2, $hits);
    }

    /**
     * Context JSON must contain offset, before and after excerpts.
     * @covers \local_coursectrl\local\text\text_change_builder
     */
    public function test_scan_includes_context(): void {
        $builder = new text_change_builder();
        $item = new text_item('section', 10, 'summary', 'Abgabe bis 15. April 2026 bitte.', FORMAT_HTML);

        $hits = $builder->scan([$item]);
        $this->assertNotEmpty($hits);

        $context = json_decode($hits[0]['contextjson'], true);
        $this->assertIsArray($context);
        $this->assertArrayHasKey('offset', $context);
        $this->assertArrayHasKey('before', $context);
        $this->assertArrayHasKey('after', $context);
        $this->assertArrayHasKey('pattern', $context);
    }

    /**
     * scan_and_persist must store hits in the database.
     * @covers \local_coursectrl\local\text\text_change_builder
     */
    public function test_scan_and_persist(): void {
        $this->resetAfterTest();
        $builder = new text_change_builder();
        $items = [
            new text_item('section', 10, 'summary', 'Frist: 15.04.2026', FORMAT_HTML),
            new text_item('cm', 100, 'intro', 'Due: 2026-04-30', FORMAT_HTML),
        ];

        $summary = $builder->scan_and_persist(1, $items);

        $this->assertSame(2, $summary['total']);
        $this->assertSame(2, $summary['safe']);
        $this->assertSame(0, $summary['ambiguous']);

        $dbhits = text_hit::get_records(['courseid' => 1]);
        $this->assertCount(2, $dbhits);
    }

    /**
     * scan_and_persist must purge old hits before inserting new ones.
     * @covers \local_coursectrl\local\text\text_change_builder
     */
    public function test_scan_and_persist_purges_old(): void {
        $this->resetAfterTest();
        $builder = new text_change_builder();

        // First scan.
        $items = [
            new text_item('section', 10, 'summary', 'Frist: 15.04.2026', FORMAT_HTML),
        ];
        $builder->scan_and_persist(1, $items);
        $this->assertCount(1, text_hit::get_records(['courseid' => 1]));

        // Second scan with different content.
        $items = [
            new text_item('section', 10, 'summary', '2026-01-01 and 2026-06-30', FORMAT_HTML),
        ];
        $builder->scan_and_persist(1, $items);
        $this->assertCount(2, text_hit::get_records(['courseid' => 1]));
    }

    /**
     * Ambiguous hits (no-year patterns) must be classified correctly.
     * @covers \local_coursectrl\local\text\text_change_builder
     */
    public function test_scan_classifies_ambiguous(): void {
        $builder = new text_change_builder();
        $item = new text_item('section', 10, 'summary', 'Abgabe am 15. April', FORMAT_HTML);

        $hits = $builder->scan([$item]);

        $this->assertNotEmpty($hits);
        $found = false;
        foreach ($hits as $hit) {
            if (
                $hit['confidence'] === text_hit::CONFIDENCE_INFORMATIONAL
                || $hit['confidence'] === text_hit::CONFIDENCE_AMBIGUOUS
            ) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'No-year match must be ambiguous or informational.');
    }
}
