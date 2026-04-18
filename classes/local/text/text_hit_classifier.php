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
 * Confidence classifier for text-datetime hits.
 *
 * Assigns one of three confidence levels to each extractor match:
 *
 *   - safe:          full date with year, unambiguous format, valid date.
 *   - ambiguous:     missing year, or numeric format that could be DD/MM
 *                    vs MM/DD without further context.
 *   - informational: not parseable to a full date; useful as a hint for
 *                    manual review but not suitable for automatic shifting.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\text;

use local_coursectrl\local\persistent\text_hit;

/**
 * Stateless classifier: match descriptor → confidence level.
 */
class text_hit_classifier {
    /**
     * Patterns that are safe when they produce a valid normalised value.
     *
     * These patterns contain an explicit year and use an unambiguous
     * day/month ordering (named month or ISO format).
     */
    private const SAFE_PATTERNS = [
        'iso_ymd',
        'de_dmy_full',
        'de_numeric_full',
        'en_mdy_full',
    ];

    /**
     * Patterns that are always ambiguous regardless of parseability.
     *
     * Either the year is missing or the numeric format is locale-dependent.
     */
    private const AMBIGUOUS_PATTERNS = [
        'de_dmy_noyear',
        'de_numeric_noyear',
        'en_mdy_noyear',
        'us_numeric_full',
    ];

    /**
     * Classify a single extractor hit.
     *
     * @param array       $hit             Match descriptor from extractor.
     * @param string|null $normalizedvalue ISO 8601 string from parser, or null.
     * @return string One of text_hit::CONFIDENCE_* constants.
     */
    public function classify(array $hit, ?string $normalizedvalue): string {
        $pattern = $hit['pattern'] ?? '';

        // No-year patterns: ambiguous if we have a normalised value (year assumed).
        if (in_array($pattern, self::AMBIGUOUS_PATTERNS, true)) {
            if ($normalizedvalue !== null) {
                return text_hit::CONFIDENCE_AMBIGUOUS;
            }
            return text_hit::CONFIDENCE_INFORMATIONAL;
        }

        // Full-year patterns are safe if parsing succeeded.
        if (in_array($pattern, self::SAFE_PATTERNS, true)) {
            if ($normalizedvalue !== null) {
                return text_hit::CONFIDENCE_SAFE;
            }
            // Pattern matched but parsing failed (e.g. invalid date like Feb 30).
            return text_hit::CONFIDENCE_INFORMATIONAL;
        }

        // Fallback for unknown patterns.
        if ($normalizedvalue !== null) {
            return text_hit::CONFIDENCE_AMBIGUOUS;
        }
        return text_hit::CONFIDENCE_INFORMATIONAL;
    }
}
