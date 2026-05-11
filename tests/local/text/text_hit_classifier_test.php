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
 * Tests for the text_hit_classifier.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\text;

use local_coursectrl\local\persistent\text_hit;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\text\text_hit_classifier::class)]
/**
 * Unit tests for text_hit_classifier::classify().
 *
 * @covers \local_coursectrl\local\text\text_hit_classifier
 */
final class text_hit_classifier_test extends \basic_testcase {
    /** @var text_hit_classifier */
    private text_hit_classifier $classifier;

    protected function setUp(): void {
        parent::setUp();
        $this->classifier = new text_hit_classifier();
    }

    /**
     * ISO date with valid normalisation must be safe.
     * @covers \local_coursectrl\local\text\text_hit_classifier
     * @return void
     */
    public function test_iso_with_value_is_safe(): void {
        $hit = ['pattern' => 'iso_ymd', 'groups' => []];
        $result = $this->classifier->classify($hit, '2026-04-15');
        $this->assertSame(text_hit::CONFIDENCE_SAFE, $result);
    }

    /**
     * German full date with valid normalisation must be safe.
     * @covers \local_coursectrl\local\text\text_hit_classifier
     * @return void
     */
    public function test_de_full_with_value_is_safe(): void {
        $hit = ['pattern' => 'de_dmy_full', 'groups' => []];
        $result = $this->classifier->classify($hit, '2026-04-15');
        $this->assertSame(text_hit::CONFIDENCE_SAFE, $result);
    }

    /**
     * German numeric full date with valid normalisation must be safe.
     * @covers \local_coursectrl\local\text\text_hit_classifier
     * @return void
     */
    public function test_de_numeric_full_with_value_is_safe(): void {
        $hit = ['pattern' => 'de_numeric_full', 'groups' => []];
        $result = $this->classifier->classify($hit, '2026-04-15');
        $this->assertSame(text_hit::CONFIDENCE_SAFE, $result);
    }

    /**
     * English full date with valid normalisation must be safe.
     * @covers \local_coursectrl\local\text\text_hit_classifier
     * @return void
     */
    public function test_en_full_with_value_is_safe(): void {
        $hit = ['pattern' => 'en_mdy_full', 'groups' => []];
        $result = $this->classifier->classify($hit, '2026-04-15');
        $this->assertSame(text_hit::CONFIDENCE_SAFE, $result);
    }

    /**
     * Safe pattern that fails to normalise must be informational.
     * @covers \local_coursectrl\local\text\text_hit_classifier
     * @return void
     */
    public function test_safe_pattern_without_value_is_informational(): void {
        $hit = ['pattern' => 'iso_ymd', 'groups' => []];
        $result = $this->classifier->classify($hit, null);
        $this->assertSame(text_hit::CONFIDENCE_INFORMATIONAL, $result);
    }

    /**
     * No-year pattern with normalisation must be ambiguous.
     * @covers \local_coursectrl\local\text\text_hit_classifier
     * @return void
     */
    public function test_noyear_with_value_is_ambiguous(): void {
        $hit = ['pattern' => 'de_dmy_noyear', 'groups' => []];
        $result = $this->classifier->classify($hit, '2026-04-15');
        $this->assertSame(text_hit::CONFIDENCE_AMBIGUOUS, $result);
    }

    /**
     * No-year pattern without normalisation must be informational.
     * @covers \local_coursectrl\local\text\text_hit_classifier
     * @return void
     */
    public function test_noyear_without_value_is_informational(): void {
        $hit = ['pattern' => 'de_dmy_noyear', 'groups' => []];
        $result = $this->classifier->classify($hit, null);
        $this->assertSame(text_hit::CONFIDENCE_INFORMATIONAL, $result);
    }

    /**
     * US numeric format must always be ambiguous (DD/MM vs MM/DD).
     * @covers \local_coursectrl\local\text\text_hit_classifier
     * @return void
     */
    public function test_us_numeric_is_ambiguous(): void {
        $hit = ['pattern' => 'us_numeric_full', 'groups' => []];
        $result = $this->classifier->classify($hit, '2026-04-15');
        $this->assertSame(text_hit::CONFIDENCE_AMBIGUOUS, $result);
    }

    /**
     * German numeric no-year must be ambiguous.
     * @covers \local_coursectrl\local\text\text_hit_classifier
     * @return void
     */
    public function test_de_numeric_noyear_is_ambiguous(): void {
        $hit = ['pattern' => 'de_numeric_noyear', 'groups' => []];
        $result = $this->classifier->classify($hit, '2026-04-15');
        $this->assertSame(text_hit::CONFIDENCE_AMBIGUOUS, $result);
    }

    /**
     * English no-year must be ambiguous.
     * @covers \local_coursectrl\local\text\text_hit_classifier
     * @return void
     */
    public function test_en_noyear_is_ambiguous(): void {
        $hit = ['pattern' => 'en_mdy_noyear', 'groups' => []];
        $result = $this->classifier->classify($hit, '2026-04-15');
        $this->assertSame(text_hit::CONFIDENCE_AMBIGUOUS, $result);
    }

    /**
     * Unknown pattern with normalisation must be ambiguous.
     * @covers \local_coursectrl\local\text\text_hit_classifier
     * @return void
     */
    public function test_unknown_pattern_with_value_is_ambiguous(): void {
        $hit = ['pattern' => 'unknown_pattern', 'groups' => []];
        $result = $this->classifier->classify($hit, '2026-01-01');
        $this->assertSame(text_hit::CONFIDENCE_AMBIGUOUS, $result);
    }

    /**
     * Unknown pattern without normalisation must be informational.
     * @covers \local_coursectrl\local\text\text_hit_classifier
     * @return void
     */
    public function test_unknown_pattern_without_value_is_informational(): void {
        $hit = ['pattern' => 'unknown_pattern', 'groups' => []];
        $result = $this->classifier->classify($hit, null);
        $this->assertSame(text_hit::CONFIDENCE_INFORMATIONAL, $result);
    }
}
