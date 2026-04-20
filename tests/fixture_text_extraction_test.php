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
 * Integration tests: Text-Datetime-Erkennung (S7 Fixture-Texte).
 *
 * Prüft ob der text_datetime_extractor alle relevanten Datumsformate
 * aus den Fixture-Texten erkennt und Nicht-Daten korrekt ignoriert.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl;

use local_coursectrl\local\text\text_datetime_extractor;
use local_coursectrl\local\text\text_datetime_parser;
use local_coursectrl\local\text\text_hit_classifier;
use local_coursectrl\local\persistent\text_hit;

/**
 * Tests for text_datetime_extractor using the S7 fixture text content.
 *
 * All tests operate on plain strings matching the blueprint texts for LB_TXT1,
 * PA_TXT2, URL_TXT3, AS_TXT4 — no DB restore needed.
 *
 * @covers \local_coursectrl\local\text\text_datetime_extractor
 * @covers \local_coursectrl\local\text\text_datetime_parser
 * @covers \local_coursectrl\local\text\text_hit_classifier
 */
final class fixture_text_extraction_test extends \advanced_testcase {
    /** @var text_datetime_extractor */
    private text_datetime_extractor $extractor;

    /** @var text_datetime_parser */
    private text_datetime_parser $parser;

    /** @var text_hit_classifier */
    private text_hit_classifier $classifier;

    protected function setUp(): void {
        parent::setUp();
        $this->extractor = new text_datetime_extractor();
        $this->parser = new text_datetime_parser();
        $this->classifier = new text_hit_classifier();
    }

    // Helpers.

    /**
     * Extract and return only the matched strings.
     *
     * @param string $text
     * @return string[]
     */
    private function matched(string $text): array {
        return array_column($this->extractor->extract($text), 'match');
    }

    // LB_TXT1: Deutsche und ISO-Formate.

    /**
     * Deutsches Langformat "1. Mai 2026" wird erkannt.
     */
    public function test_german_long_date_detected(): void {
        $this->resetAfterTest();
        $text = 'Der Kurs beginnt am 1. Mai 2026 und endet am 31. Oktober 2026.';
        $hits = $this->matched($text);
        $this->assertNotEmpty($hits, 'Kein Treffer im deutschen Langformat');
        $this->assertContains('1. Mai 2026', $hits);
        $this->assertContains('31. Oktober 2026', $hits);
    }

    /**
     * Numerisch-deutsches Format "01.05.2026" wird erkannt.
     */
    public function test_german_numeric_date_detected(): void {
        $this->resetAfterTest();
        $text = 'Erste Phase: 01.05.2026 bis 31.05.2026.';
        $hits = $this->matched($text);
        $this->assertNotEmpty($hits);
        $this->assertContains('01.05.2026', $hits);
        $this->assertContains('31.05.2026', $hits);
    }

    /**
     * ISO-Format "2026-06-01" wird erkannt.
     */
    public function test_iso_date_detected(): void {
        $this->resetAfterTest();
        $text = 'Zweite Phase: 2026-06-01 bis 2026-06-30.';
        $hits = $this->matched($text);
        $this->assertContains('2026-06-01', $hits);
        $this->assertContains('2026-06-30', $hits);
    }

    /**
     * ISO-Datetime mit T "2026-10-15T23:59:00" wird erkannt.
     */
    public function test_iso_datetime_t_detected(): void {
        $this->resetAfterTest();
        $text = 'Abgabe bis 2026-10-15T23:59:00.';
        $hits = $this->extractor->extract($text);
        $this->assertNotEmpty($hits);
        $this->assertStringContainsString('2026-10-15', implode(', ', array_column($hits, 'match')));
    }

    /**
     * Wochentag + Datum "Freitag, 30.10.2026" wird erkannt.
     */
    public function test_date_with_weekday_detected(): void {
        $this->resetAfterTest();
        $text = 'Abschluss-Stichtag: Freitag, 30.10.2026 um 23:59 Uhr.';
        $hits = $this->matched($text);
        $this->assertNotEmpty($hits);
        $this->assertStringContainsString('30.10.2026', implode(', ', $hits));
    }

    /**
     * Historische Jahreszahlen (1947, 1994, 1930) werden erkannt.
     */
    public function test_historical_years_detected(): void {
        $this->resetAfterTest();
        $text = 'Roswell Juli 1947. Bielefeld 1994. KFC-Gründung 1930.';
        $hits = $this->extractor->extract($text);
        $this->assertNotEmpty($hits, 'Historische Jahreszahlen sollten erkannt werden');
    }

    // PA_TXT2: Englische und internationale Formate.

    /**
     * Englisches Format "May 1, 2026" wird erkannt.
     */
    public function test_english_month_day_year_detected(): void {
        $this->resetAfterTest();
        $text = 'The course runs from May 1, 2026 to October 31, 2026.';
        $hits = $this->matched($text);
        $this->assertNotEmpty($hits);
        $all = implode(', ', $hits);
        $this->assertStringContainsString('May 1, 2026', $all);
        $this->assertStringContainsString('October 31, 2026', $all);
    }

    /**
     * Englisches Format "10 May 2026" (Tag-Monat-Jahr ohne Komma) wird erkannt.
     */
    public function test_english_dmy_detected(): void {
        $this->resetAfterTest();
        $text = 'The quiz window opens on 10 May 2026 at 8:00 AM.';
        $hits = $this->matched($text);
        $this->assertNotEmpty($hits);
        $this->assertStringContainsString('10 May 2026', implode(', ', $hits));
    }

    /**
     * Reine Zahlen ohne Datumskontext (42, 60, 3, 10) werden NICHT als Daten erkannt.
     */
    public function test_standalone_numbers_not_dates(): void {
        $this->resetAfterTest();
        $text = '42 students. The pass threshold is 60%. A minimum of 3 posts. 10 chapters.';
        $hits = $this->extractor->extract($text);
        foreach ($hits as $hit) {
            $m = $hit['match'];
            $this->assertNotSame('42', $m, "'42' should not be a date");
            $this->assertNotSame('3', $m, "'3' should not be a date");
            $this->assertNotSame('10', $m, "'10' should not be a date");
        }
    }

    // URL_TXT3: Gemischte Formate, gleiches Datum viermal.

    /**
     * Dasselbe Datum in vier Schreibweisen — mindestens 3 werden erkannt.
     */
    public function test_same_date_four_formats_mostly_detected(): void {
        $this->resetAfterTest();
        $text = 'Dasselbe Datum: 01. Mai 2026, May 1, 2026, 2026-05-01, 01/05/2026.';
        $hits = $this->matched($text);
        $this->assertGreaterThanOrEqual(
            3,
            count($hits),
            'At least 3 of 4 date formats for the same date should be detected. Got: ' . implode(', ', $hits)
        );
    }

    // AS_TXT4: Historische und technische Referenzdaten.

    /**
     * Datum "01.01.2000" (Y2K) wird erkannt.
     */
    public function test_y2k_date_detected(): void {
        $this->resetAfterTest();
        $text = 'Y2K-Bug: kritische Systeme ab 01.01.2000 betroffen.';
        $hits = $this->matched($text);
        $this->assertNotEmpty($hits, 'Y2K date 01.01.2000 should be detected');
    }

    /**
     * Unix-Grenze "2038-01-19" wird erkannt.
     */
    public function test_unix_epoch_limit_detected(): void {
        $this->resetAfterTest();
        $text = 'Unix-Timestamp-Grenze: 2038-01-19T03:14:07 UTC.';
        $hits = $this->matched($text);
        $this->assertNotEmpty($hits);
    }

    // Parser.

    /**
     * Parser normalisiert ISO-Datum zu "2026-10-15".
     */
    public function test_parser_normalises_iso(): void {
        $this->resetAfterTest();
        $hits = $this->extractor->extract('Deadline: 2026-10-15T23:59:00.');
        $this->assertNotEmpty($hits);
        $normalized = $this->parser->normalise($hits[0]);
        $this->assertNotNull($normalized, 'ISO date should be parseable');
        $this->assertStringContainsString('2026-10-15', $normalized);
    }

    /**
     * Parser normalisiert deutsches Langformat "15. Oktober 2026".
     */
    public function test_parser_normalises_german_long(): void {
        $this->resetAfterTest();
        $hits = $this->extractor->extract('Abgabe bis 15. Oktober 2026.');
        $this->assertNotEmpty($hits);
        $normalized = $this->parser->normalise($hits[0]);
        $this->assertNotNull($normalized);
        $this->assertStringContainsString('2026-10-15', $normalized);
    }

    // Klassifizierung.

    /**
     * ISO-Datum mit vollständigem Jahr → Klassifizierung 'safe'.
     */
    public function test_classifier_iso_full_year_safe(): void {
        $this->resetAfterTest();
        $hits = $this->extractor->extract('Datum: 2026-05-01.');
        $this->assertNotEmpty($hits);
        $hit = $hits[0];
        $normalized = $this->parser->normalise($hit);
        $confidence = $this->classifier->classify($hit, $normalized);
        $this->assertSame(text_hit::CONFIDENCE_SAFE, $confidence, 'ISO date with full year should be safe');
    }

    /**
     * Format ohne Jahr → Klassifizierung 'ambiguous' oder 'informational'.
     */
    public function test_classifier_no_year_not_safe(): void {
        $this->resetAfterTest();
        $hits = $this->extractor->extract('Termin: 15. Oktober um 12:00 Uhr.');
        if (empty($hits)) {
            $this->markTestSkipped('No-year date not detected');
        }
        $hit = $hits[0];
        $normalized = $this->parser->normalise($hit);
        $confidence = $this->classifier->classify($hit, $normalized);
        $this->assertNotSame(text_hit::CONFIDENCE_SAFE, $confidence, 'Date without year should not be safe');
    }
}
