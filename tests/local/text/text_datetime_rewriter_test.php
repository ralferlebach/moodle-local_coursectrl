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
 * Tests for the text_datetime_rewriter.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\text;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\text\text_datetime_rewriter::class)]
/**
 * Unit tests for text_datetime_rewriter::rewrite().
 *
 * @covers \local_coursectrl\local\text\text_datetime_rewriter
 */
final class text_datetime_rewriter_test extends \basic_testcase {
    /** @var text_datetime_rewriter */
    private text_datetime_rewriter $rewriter;

    protected function setUp(): void {
        parent::setUp();
        $this->rewriter = new text_datetime_rewriter();
    }

    /**
     * Helper: build a hit record array for rewrite().
     *
     * @param string $matched  Original matched text.
     * @param string $iso      Normalised ISO value.
     * @param int    $offset   Byte offset in the text.
     * @param string $pattern  Extractor pattern tag.
     * @return array
     */
    private function hit(string $matched, string $iso, int $offset, string $pattern = 'iso_ymd'): array {
        return [
            'matchedtext' => $matched,
            'normalizedvalue' => $iso,
            'contextjson' => json_encode([
                'offset' => $offset,
                'length' => strlen($matched),
                'pattern' => $pattern,
            ]),
        ];
    }

    /**
     * ISO date must be shifted in place.
     * @covers \local_coursectrl\local\text\text_datetime_rewriter
     */
    public function test_rewrite_iso_date(): void {
        $text = 'Deadline: 2026-04-15 please.';
        $hits = [$this->hit('2026-04-15', '2026-04-15', 10)];

        $result = $this->rewriter->rewrite($text, $hits, 7 * 86400);

        $this->assertStringContainsString('2026-04-22', $result['text']);
        $this->assertCount(1, $result['applied']);
        $this->assertEmpty($result['skipped']);
    }

    /**
     * German named date must preserve formatting style.
     * @covers \local_coursectrl\local\text\text_datetime_rewriter
     */
    public function test_rewrite_de_named(): void {
        $text = 'Abgabe bis 15. April 2026 bitte.';
        $hits = [$this->hit('15. April 2026', '2026-04-15', 11, 'de_dmy_full')];

        $result = $this->rewriter->rewrite($text, $hits, 7 * 86400);

        $this->assertStringContainsString('22. April 2026', $result['text']);
        $this->assertCount(1, $result['applied']);
    }

    /**
     * German numeric date must preserve formatting style.
     * @covers \local_coursectrl\local\text\text_datetime_rewriter
     */
    public function test_rewrite_de_numeric(): void {
        $text = 'Frist: 15.04.2026 Ende.';
        $hits = [$this->hit('15.04.2026', '2026-04-15', 7, 'de_numeric_full')];

        $result = $this->rewriter->rewrite($text, $hits, 7 * 86400);

        $this->assertStringContainsString('22.04.2026', $result['text']);
    }

    /**
     * English named date must preserve formatting style.
     * @covers \local_coursectrl\local\text\text_datetime_rewriter
     */
    public function test_rewrite_en_named(): void {
        $text = 'Due: April 15, 2026 sharp.';
        $hits = [$this->hit('April 15, 2026', '2026-04-15', 5, 'en_mdy_full')];

        $result = $this->rewriter->rewrite($text, $hits, 7 * 86400);

        $this->assertStringContainsString('April 22, 2026', $result['text']);
    }

    /**
     * US numeric date must preserve formatting style.
     * @covers \local_coursectrl\local\text\text_datetime_rewriter
     */
    public function test_rewrite_us_numeric(): void {
        $text = 'Date: 04/15/2026 done.';
        $hits = [$this->hit('04/15/2026', '2026-04-15', 6, 'us_numeric_full')];

        $result = $this->rewriter->rewrite($text, $hits, 7 * 86400);

        $this->assertStringContainsString('04/22/2026', $result['text']);
    }

    /**
     * Multiple hits in one text must all be replaced.
     * @covers \local_coursectrl\local\text\text_datetime_rewriter
     */
    public function test_rewrite_multiple_hits(): void {
        $text = 'Start: 2026-04-01, End: 2026-04-30.';
        $hits = [
            $this->hit('2026-04-01', '2026-04-01', 7),
            $this->hit('2026-04-30', '2026-04-30', 24),
        ];

        $result = $this->rewriter->rewrite($text, $hits, 7 * 86400);

        $this->assertStringContainsString('2026-04-08', $result['text']);
        $this->assertStringContainsString('2026-05-07', $result['text']);
        $this->assertCount(2, $result['applied']);
    }

    /**
     * Hit without normalised value must be skipped.
     * @covers \local_coursectrl\local\text\text_datetime_rewriter
     */
    public function test_skip_no_normalized(): void {
        $text = 'Abgabe am 15. April irgendwann.';
        $hits = [[
            'matchedtext' => '15. April',
            'normalizedvalue' => null,
            'contextjson' => json_encode(['offset' => 11, 'length' => 9, 'pattern' => 'de_dmy_noyear']),
        ]];

        $result = $this->rewriter->rewrite($text, $hits, 86400);

        $this->assertSame($text, $result['text']);
        $this->assertEmpty($result['applied']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame('no_normalized_value', $result['skipped'][0]['reason']);
    }

    /**
     * Hit with mismatched offset must be skipped safely.
     * @covers \local_coursectrl\local\text\text_datetime_rewriter
     */
    public function test_skip_offset_mismatch(): void {
        $text = 'Changed: 2026-04-15 here.';
        // Wrong offset on purpose.
        $hits = [$this->hit('2026-04-15', '2026-04-15', 0)];

        $result = $this->rewriter->rewrite($text, $hits, 86400);

        $this->assertSame($text, $result['text']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame('offset_mismatch', $result['skipped'][0]['reason']);
    }

    /**
     * Zero delta must produce no-change replacements.
     * @covers \local_coursectrl\local\text\text_datetime_rewriter
     */
    public function test_rewrite_zero_delta(): void {
        $text = 'Deadline: 2026-04-15.';
        $hits = [$this->hit('2026-04-15', '2026-04-15', 10)];

        $result = $this->rewriter->rewrite($text, $hits, 0);

        $this->assertStringContainsString('2026-04-15', $result['text']);
        $this->assertCount(1, $result['applied']);
        $this->assertSame('2026-04-15', $result['applied'][0]['newiso']);
    }
}
