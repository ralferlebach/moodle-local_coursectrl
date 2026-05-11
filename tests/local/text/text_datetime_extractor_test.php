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
 * Tests for the text_datetime_extractor.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\text;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\text\text_datetime_extractor::class)]
/**
 * Unit tests for text_datetime_extractor::extract().
 *
 * @covers \local_coursectrl\local\text\text_datetime_extractor
 */
final class text_datetime_extractor_test extends \basic_testcase {
    /** @var text_datetime_extractor */
    private text_datetime_extractor $extractor;

    protected function setUp(): void {
        parent::setUp();
        $this->extractor = new text_datetime_extractor();
    }

    /**
     * ISO 8601 date must be extracted.
     * @covers \local_coursectrl\local\text\text_datetime_extractor
     * @return void
     */
    public function test_iso_date(): void {
        $hits = $this->extractor->extract('Submit by 2026-04-15 please.');
        $this->assertCount(1, $hits);
        $this->assertSame('2026-04-15', $hits[0]['match']);
        $this->assertSame('iso_ymd', $hits[0]['pattern']);
    }

    /**
     * ISO 8601 datetime must be extracted.
     * @covers \local_coursectrl\local\text\text_datetime_extractor
     * @return void
     */
    public function test_iso_datetime(): void {
        $hits = $this->extractor->extract('Deadline: 2026-04-15T14:00');
        $this->assertCount(1, $hits);
        $this->assertStringContainsString('14:00', $hits[0]['match']);
        $this->assertSame('14', $hits[0]['groups']['hour']);
        $this->assertSame('00', $hits[0]['groups']['minute']);
    }

    /**
     * German full date with month name.
     * @covers \local_coursectrl\local\text\text_datetime_extractor
     * @return void
     */
    public function test_de_full_date(): void {
        $hits = $this->extractor->extract('Abgabe bis 15. April 2026');
        $this->assertCount(1, $hits);
        $this->assertSame('de_dmy_full', $hits[0]['pattern']);
        $this->assertSame('15', $hits[0]['groups']['day']);
        $this->assertSame('2026', $hits[0]['groups']['year']);
    }

    /**
     * German full date with abbreviated month.
     * @covers \local_coursectrl\local\text\text_datetime_extractor
     * @return void
     */
    public function test_de_abbreviated_month(): void {
        $hits = $this->extractor->extract('Termin: 3. Okt. 2026');
        $this->assertCount(1, $hits);
        $this->assertSame('de_dmy_full', $hits[0]['pattern']);
    }

    /**
     * German full date with time suffix.
     * @covers \local_coursectrl\local\text\text_datetime_extractor
     * @return void
     */
    public function test_de_full_with_time(): void {
        $hits = $this->extractor->extract('Am 15. April 2026, 14:00 Uhr ist Abgabe.');
        $this->assertCount(1, $hits);
        $this->assertSame('14', $hits[0]['groups']['hour']);
        $this->assertSame('00', $hits[0]['groups']['minute']);
    }

    /**
     * German date without year must match the no-year pattern.
     * @covers \local_coursectrl\local\text\text_datetime_extractor
     * @return void
     */
    public function test_de_noyear(): void {
        $hits = $this->extractor->extract('Abgabe am 15. April');
        $this->assertCount(1, $hits);
        $this->assertSame('de_dmy_noyear', $hits[0]['pattern']);
    }

    /**
     * German numeric date with year.
     * @covers \local_coursectrl\local\text\text_datetime_extractor
     * @return void
     */
    public function test_de_numeric_full(): void {
        $hits = $this->extractor->extract('Frist: 15.04.2026');
        $this->assertCount(1, $hits);
        $this->assertSame('de_numeric_full', $hits[0]['pattern']);
        $this->assertSame('15', $hits[0]['groups']['day']);
        $this->assertSame('04', $hits[0]['groups']['month']);
        $this->assertSame('2026', $hits[0]['groups']['year']);
    }

    /**
     * German numeric date without year (trailing dot).
     * @covers \local_coursectrl\local\text\text_datetime_extractor
     * @return void
     */
    public function test_de_numeric_noyear(): void {
        $hits = $this->extractor->extract('bis zum 15.04. bitte einreichen');
        $this->assertCount(1, $hits);
        $this->assertSame('de_numeric_noyear', $hits[0]['pattern']);
    }

    /**
     * English full date: Month Day, Year.
     * @covers \local_coursectrl\local\text\text_datetime_extractor
     * @return void
     */
    public function test_en_full_date(): void {
        $hits = $this->extractor->extract('Due: April 15, 2026');
        $this->assertCount(1, $hits);
        $this->assertSame('en_mdy_full', $hits[0]['pattern']);
        $this->assertSame('15', $hits[0]['groups']['day']);
        $this->assertSame('2026', $hits[0]['groups']['year']);
    }

    /**
     * English full date with time and AM/PM.
     * @covers \local_coursectrl\local\text\text_datetime_extractor
     * @return void
     */
    public function test_en_full_with_ampm(): void {
        $hits = $this->extractor->extract('Submit by April 15, 2026 at 2:00 PM');
        $this->assertCount(1, $hits);
        $this->assertSame('2', $hits[0]['groups']['hour']);
        $this->assertSame('00', $hits[0]['groups']['minute']);
        $this->assertSame('PM', $hits[0]['groups']['ampm']);
    }

    /**
     * English date without year.
     * @covers \local_coursectrl\local\text\text_datetime_extractor
     * @return void
     */
    public function test_en_noyear(): void {
        $hits = $this->extractor->extract('Due on April 15');
        $this->assertCount(1, $hits);
        $this->assertSame('en_mdy_noyear', $hits[0]['pattern']);
    }

    /**
     * US numeric date: MM/DD/YYYY.
     * @covers \local_coursectrl\local\text\text_datetime_extractor
     * @return void
     */
    public function test_us_numeric(): void {
        $hits = $this->extractor->extract('Deadline 04/15/2026');
        $this->assertCount(1, $hits);
        $this->assertSame('us_numeric_full', $hits[0]['pattern']);
    }

    /**
     * Multiple dates in one text must all be extracted.
     * @covers \local_coursectrl\local\text\text_datetime_extractor
     * @return void
     */
    public function test_multiple_dates(): void {
        $text = 'Start: 2026-04-01. End: 2026-04-30. Review: 15. Mai 2026.';
        $hits = $this->extractor->extract($text);
        $this->assertGreaterThanOrEqual(3, count($hits));
    }

    /**
     * HTML tags must be stripped without breaking offsets.
     * @covers \local_coursectrl\local\text\text_datetime_extractor
     * @return void
     */
    public function test_html_stripped(): void {
        $text = '<p>Abgabe <strong>15.04.2026</strong> bitte.</p>';
        $hits = $this->extractor->extract($text);
        $this->assertCount(1, $hits);
        $this->assertSame('15.04.2026', $hits[0]['match']);
    }

    /**
     * Text without any dates must return an empty array.
     * @covers \local_coursectrl\local\text\text_datetime_extractor
     * @return void
     */
    public function test_no_dates(): void {
        $hits = $this->extractor->extract('This text has no dates at all.');
        $this->assertCount(0, $hits);
    }

    /**
     * German month name resolver must handle common forms.
     * @covers \local_coursectrl\local\text\text_datetime_extractor
     * @return void
     */
    public function test_resolve_de_month(): void {
        $this->assertSame(1, text_datetime_extractor::resolve_de_month('Januar'));
        $this->assertSame(3, text_datetime_extractor::resolve_de_month('März'));
        $this->assertSame(3, text_datetime_extractor::resolve_de_month('Mär'));
        $this->assertSame(10, text_datetime_extractor::resolve_de_month('Okt.'));
        $this->assertNull(text_datetime_extractor::resolve_de_month('Foobar'));
    }

    /**
     * English month name resolver must handle common forms.
     * @covers \local_coursectrl\local\text\text_datetime_extractor
     * @return void
     */
    public function test_resolve_en_month(): void {
        $this->assertSame(1, text_datetime_extractor::resolve_en_month('January'));
        $this->assertSame(9, text_datetime_extractor::resolve_en_month('Sept'));
        $this->assertSame(12, text_datetime_extractor::resolve_en_month('Dec.'));
        $this->assertNull(text_datetime_extractor::resolve_en_month('Foobar'));
    }
}
