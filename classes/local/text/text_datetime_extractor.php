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
 * Regex-based date/time pattern extractor for free-text fields.
 *
 * Scans HTML-stripped text for date and datetime references in German
 * and English formats. Returns raw match descriptors with offset, matched
 * substring and a preliminary pattern tag used by the classifier.
 *
 * The extractor is intentionally stateless; it operates on a single text
 * string at a time and returns all matches found.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\text;

/**
 * Stateless regex-based date/time extractor.
 */
class text_datetime_extractor {
    /**
     * German month names (full and abbreviated) mapped to month numbers.
     */
    private const DE_MONTHS = [
        'januar' => 1, 'jan' => 1,
        'februar' => 2, 'feb' => 2,
        'märz' => 3, 'maerz' => 3, 'mär' => 3,
        'april' => 4, 'apr' => 4,
        'mai' => 5,
        'juni' => 6, 'jun' => 6,
        'juli' => 7, 'jul' => 7,
        'august' => 8, 'aug' => 8,
        'september' => 9, 'sep' => 9, 'sept' => 9,
        'oktober' => 10, 'okt' => 10,
        'november' => 11, 'nov' => 11,
        'dezember' => 12, 'dez' => 12,
    ];

    /**
     * English month names (full and abbreviated) mapped to month numbers.
     */
    private const EN_MONTHS = [
        'january' => 1, 'jan' => 1,
        'february' => 2, 'feb' => 2,
        'march' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4,
        'may' => 5,
        'june' => 6, 'jun' => 6,
        'july' => 7, 'jul' => 7,
        'august' => 8, 'aug' => 8,
        'september' => 9, 'sep' => 9, 'sept' => 9,
        'october' => 10, 'oct' => 10,
        'november' => 11, 'nov' => 11,
        'december' => 12, 'dec' => 12,
    ];

    /**
     * Extract all date/time matches from a text.
     *
     * The text should be plain text (HTML stripped). Each returned match
     * is an associative array:
     *   - match:   the matched substring
     *   - offset:  byte offset in the input string
     *   - pattern: tag identifying which regex matched (e.g. 'de_dmy_full')
     *   - groups:  named capture groups from the regex
     *
     * @param string $text Plain text to scan.
     * @return array[] List of match descriptors, ordered by offset.
     */
    public function extract(string $text): array {
        $text = $this->strip_tags_preserve_whitespace($text);
        $hits = [];
        foreach ($this->get_patterns() as $tag => $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($matches as $m) {
                    $groups = [];
                    foreach ($m as $key => $val) {
                        if (is_string($key)) {
                            $groups[$key] = $val[0];
                        }
                    }
                    $hits[] = [
                        'match' => $m[0][0],
                        'offset' => $m[0][1],
                        'pattern' => $tag,
                        'groups' => $groups,
                    ];
                }
            }
        }
        // De-duplicate overlapping matches: keep the longest at each offset.
        $hits = $this->deduplicate($hits);
        // Sort by offset.
        usort($hits, fn($a, $b) => $a['offset'] <=> $b['offset']);
        return $hits;
    }

    /**
     * Strip HTML tags while preserving whitespace offsets.
     *
     * @param string $text HTML or plain text.
     * @return string Plain text.
     */
    private function strip_tags_preserve_whitespace(string $text): string {
        // Replace block-level tags with newlines to avoid concatenating words.
        $text = preg_replace('/<\s*(br|p|div|li|tr|td|th|h[1-6])[^>]*>/i', "\n", $text);
        return strip_tags($text);
    }

    /**
     * Return all date/time regex patterns with named capture groups.
     *
     * @return array<string, string>
     */
    private function get_patterns(): array {
        // German month names for alternation.
        $demonths = implode('|', array_keys(self::DE_MONTHS));
        // English month names for alternation.
        $enmonths = implode('|', array_keys(self::EN_MONTHS));

        // Optional time suffix patterns.
        $timeopt = '(?:[,\s]+(?:um\s+)?(?P<hour>\d{1,2})[:.](?P<minute>\d{2})(?:\s*Uhr)?)?';
        $timeopt_en = '(?:[,\s]+(?:at\s+)?(?P<hour>\d{1,2}):(?P<minute>\d{2})(?:\s*(?P<ampm>[AaPp][Mm]))?)?';

        return [
            // ISO 8601: 2026-04-15 or 2026-04-15T14:00.
            'iso_ymd' => '/\b(?P<year>\d{4})-(?P<month>0[1-9]|1[0-2])-(?P<day>[0-2]\d|3[01])'
                . '(?:[T\s](?P<hour>\d{2}):(?P<minute>\d{2})(?::(?P<second>\d{2}))?)?/u',

            // German: 15. April 2026 [14:00] or 15. Apr. 2026.
            'de_dmy_full' => '/\b(?P<day>[0-3]?\d)\.\s*(?P<monthname>' . $demonths . ')\.?\s+'
                . '(?P<year>20\d{2})' . $timeopt . '/iu',

            // German without year: 15. April [14:00].
            'de_dmy_noyear' => '/\b(?P<day>[0-3]?\d)\.\s*(?P<monthname>' . $demonths . ')\.?'
                . '(?!\s*\d{4})' . $timeopt . '/iu',

            // German numeric: 15.04.2026 [14:00].
            'de_numeric_full' => '/\b(?P<day>[0-3]?\d)\.(?P<month>0?[1-9]|1[0-2])\.(?P<year>20\d{2})'
                . $timeopt . '/u',

            // German numeric without year: 15.04. (trailing dot).
            'de_numeric_noyear' => '/\b(?P<day>[0-3]?\d)\.(?P<month>0?[1-9]|1[0-2])\.'
                . '(?!\s*\d)' . '/u',

            // English: April 15, 2026 [at 2:00 PM].
            'en_mdy_full' => '/\b(?P<monthname>' . $enmonths . ')\.?\s+'
                . '(?P<day>[0-3]?\d)(?:st|nd|rd|th)?,?\s+(?P<year>20\d{2})'
                . $timeopt_en . '/iu',

            // English without year: April 15 [at 2:00 PM].
            'en_mdy_noyear' => '/\b(?P<monthname>' . $enmonths . ')\.?\s+'
                . '(?P<day>[0-3]?\d)(?:st|nd|rd|th)?(?!\s*,?\s*\d{4})'
                . $timeopt_en . '/iu',

            // US numeric: 04/15/2026 or 4/15/2026.
            'us_numeric_full' => '/\b(?P<month>0?[1-9]|1[0-2])\/(?P<day>[0-3]?\d)\/(?P<year>20\d{2})/u',
        ];
    }

    /**
     * Remove overlapping matches, keeping the longest at each position.
     *
     * @param array[] $hits Raw matches.
     * @return array[] De-duplicated matches.
     */
    private function deduplicate(array $hits): array {
        // Sort by offset asc, then by match length desc.
        usort($hits, function ($a, $b) {
            $cmp = $a['offset'] <=> $b['offset'];
            if ($cmp !== 0) {
                return $cmp;
            }
            return strlen($b['match']) <=> strlen($a['match']);
        });
        $result = [];
        $lastend = -1;
        foreach ($hits as $hit) {
            $start = $hit['offset'];
            $end = $start + strlen($hit['match']);
            if ($start >= $lastend) {
                $result[] = $hit;
                $lastend = $end;
            }
        }
        return $result;
    }

    /**
     * Resolve a German month name to its number.
     *
     * @param string $name Month name or abbreviation.
     * @return int|null Month number 1-12, or null.
     */
    public static function resolve_de_month(string $name): ?int {
        $key = mb_strtolower(trim($name, ". \t\n\r"));
        return self::DE_MONTHS[$key] ?? null;
    }

    /**
     * Resolve an English month name to its number.
     *
     * @param string $name Month name or abbreviation.
     * @return int|null Month number 1-12, or null.
     */
    public static function resolve_en_month(string $name): ?int {
        $key = mb_strtolower(trim($name, ". \t\n\r"));
        return self::EN_MONTHS[$key] ?? null;
    }
}
