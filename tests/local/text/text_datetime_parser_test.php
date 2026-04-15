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
 * Tests for the text_datetime_parser.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\text;

/**
 * Unit tests for text_datetime_parser.
 *
 * @covers \local_coursectrl\local\text\text_datetime_parser
 */
final class text_datetime_parser_test extends \basic_testcase {
    /** @var text_datetime_parser */
    private text_datetime_parser $parser;

    /** @var text_datetime_extractor */
    private text_datetime_extractor $extractor;

    protected function setUp(): void {
        parent::setUp();
        $this->parser = new text_datetime_parser();
        $this->extractor = new text_datetime_extractor();
    }

    /**
     * ISO date must normalise to itself.
     */
    public function test_normalise_iso_date(): void {
        $hits = $this->extractor->extract('2026-04-15');
        $this->assertCount(1, $hits);
        $iso = $this->parser->normalise($hits[0]);
        $this->assertSame('2026-04-15', $iso);
    }

    /**
     * ISO datetime must normalise with time component.
     */
    public function test_normalise_iso_datetime(): void {
        $hits = $this->extractor->extract('2026-04-15T14:30');
        $iso = $this->parser->normalise($hits[0]);
        $this->assertSame('2026-04-15T14:30', $iso);
    }

    /**
     * German full date must normalise to ISO.
     */
    public function test_normalise_de_full(): void {
        $hits = $this->extractor->extract('15. April 2026');
        $iso = $this->parser->normalise($hits[0]);
        $this->assertSame('2026-04-15', $iso);
    }

    /**
     * German full date with time.
     */
    public function test_normalise_de_full_with_time(): void {
        $hits = $this->extractor->extract('15. April 2026, 14:00 Uhr');
        $iso = $this->parser->normalise($hits[0]);
        $this->assertSame('2026-04-15T14:00', $iso);
    }

    /**
     * German numeric date.
     */
    public function test_normalise_de_numeric(): void {
        $hits = $this->extractor->extract('15.04.2026');
        $iso = $this->parser->normalise($hits[0]);
        $this->assertSame('2026-04-15', $iso);
    }

    /**
     * English full date.
     */
    public function test_normalise_en_full(): void {
        $hits = $this->extractor->extract('April 15, 2026');
        $iso = $this->parser->normalise($hits[0]);
        $this->assertSame('2026-04-15', $iso);
    }

    /**
     * English date with PM time.
     */
    public function test_normalise_en_with_pm(): void {
        $hits = $this->extractor->extract('April 15, 2026 at 2:00 PM');
        $iso = $this->parser->normalise($hits[0]);
        $this->assertSame('2026-04-15T14:00', $iso);
    }

    /**
     * US numeric date.
     */
    public function test_normalise_us_numeric(): void {
        $hits = $this->extractor->extract('04/15/2026');
        $iso = $this->parser->normalise($hits[0]);
        $this->assertSame('2026-04-15', $iso);
    }

    /**
     * No-year patterns must return null (cannot normalise without year).
     */
    public function test_normalise_noyear_returns_null(): void {
        $hits = $this->extractor->extract('Abgabe am 15. April');
        $this->assertCount(1, $hits);
        $iso = $this->parser->normalise($hits[0]);
        $this->assertNull($iso);
    }

    /**
     * Invalid date (Feb 30) must return null.
     */
    public function test_normalise_invalid_date_returns_null(): void {
        $hits = $this->extractor->extract('30.02.2026');
        if (count($hits) > 0) {
            $iso = $this->parser->normalise($hits[0]);
            $this->assertNull($iso);
        } else {
            // Regex might not match invalid day for February.
            $this->assertTrue(true);
        }
    }

    /**
     * to_timestamp must return correct epoch value.
     */
    public function test_to_timestamp(): void {
        $ts = $this->parser->to_timestamp('2026-04-15');
        $this->assertNotNull($ts);
        $dt = new \DateTime('@' . $ts);
        $this->assertSame('2026-04-15', $dt->format('Y-m-d'));
    }

    /**
     * shift_iso must shift a date forward by the given delta.
     */
    public function test_shift_iso_forward(): void {
        $shifted = $this->parser->shift_iso('2026-04-15', 7 * 86400);
        $this->assertSame('2026-04-22', $shifted);
    }

    /**
     * shift_iso must shift a datetime backward.
     */
    public function test_shift_iso_backward_with_time(): void {
        $shifted = $this->parser->shift_iso('2026-04-15T14:00', -3 * 86400);
        $this->assertSame('2026-04-12T14:00', $shifted);
    }

    /**
     * shift_iso with zero delta must return the same value.
     */
    public function test_shift_iso_zero(): void {
        $shifted = $this->parser->shift_iso('2026-04-15', 0);
        $this->assertSame('2026-04-15', $shifted);
    }
}
