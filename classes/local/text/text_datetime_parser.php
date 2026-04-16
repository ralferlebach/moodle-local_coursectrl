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
 * Parser that normalises raw extractor matches into ISO 8601 values.
 *
 * All date operations use UTC with a leading '!' in format strings so
 * missing portions are reset to the Unix epoch (00:00:00) instead of
 * inheriting the current system time. This guarantees deterministic
 * results regardless of when or where the test runs.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\text;

/**
 * Stateless parser: match descriptor → ISO 8601 string.
 */
class text_datetime_parser {
    /** @var \DateTimeZone|null Shared UTC timezone instance. */
    private static ?\DateTimeZone $utc = null;

    /**
     * Return the shared UTC timezone instance.
     *
     * @return \DateTimeZone
     */
    private static function utc(): \DateTimeZone {
        if (self::$utc === null) {
            self::$utc = new \DateTimeZone('UTC');
        }
        return self::$utc;
    }

    /**
     * Attempt to normalise a match descriptor into an ISO 8601 string.
     *
     * @param array $hit Match descriptor from text_datetime_extractor.
     * @return string|null ISO 8601 date(time) string, or null on failure.
     */
    public function normalise(array $hit): ?string {
        $groups = $hit['groups'] ?? [];
        $pattern = $hit['pattern'] ?? '';

        $year = $this->extract_year($groups);
        $month = $this->extract_month($groups, $pattern);
        $day = $this->extract_day($groups);

        if ($month === null || $day === null) {
            return null;
        }
        if ($day < 1 || $day > 31 || $month < 1 || $month > 12) {
            return null;
        }
        if ($year !== null && !checkdate($month, $day, $year)) {
            return null;
        }

        $hour = $this->extract_hour($groups);
        $minute = $this->extract_minute($groups);

        if ($year === null) {
            return null;
        }
        $iso = sprintf('%04d-%02d-%02d', $year, $month, $day);
        if ($hour !== null && $minute !== null) {
            $iso .= sprintf('T%02d:%02d', $hour, $minute);
        }
        return $iso;
    }

    /**
     * Convert an ISO 8601 string to a Unix timestamp.
     *
     * Uses UTC and a bang-prefixed format so missing time components
     * default to 00:00:00 rather than the current system time.
     *
     * @param string $iso ISO 8601 date or datetime.
     * @return int|null Unix timestamp, or null on failure.
     */
    public function to_timestamp(string $iso): ?int {
        $dt = \DateTime::createFromFormat('!Y-m-d\TH:i', $iso, self::utc());
        if ($dt === false) {
            $dt = \DateTime::createFromFormat('!Y-m-d', $iso, self::utc());
        }
        if ($dt === false) {
            return null;
        }
        return $dt->getTimestamp();
    }

    /**
     * Shift an ISO 8601 string by a delta in seconds.
     *
     * @param string $iso   ISO 8601 date or datetime.
     * @param int    $delta Seconds to shift (positive = forward).
     * @return string|null  Shifted ISO string, or null on failure.
     */
    public function shift_iso(string $iso, int $delta): ?string {
        $hastime = str_contains($iso, 'T');
        $readformat = $hastime ? '!Y-m-d\TH:i' : '!Y-m-d';
        $writeformat = $hastime ? 'Y-m-d\TH:i' : 'Y-m-d';
        $dt = \DateTime::createFromFormat($readformat, $iso, self::utc());
        if ($dt === false) {
            return null;
        }
        $dt->modify(($delta >= 0 ? '+' : '') . $delta . ' seconds');
        return $dt->format($writeformat);
    }

    /**
     * Extract year from capture groups.
     *
     * @param array $groups Named capture groups.
     * @return int|null
     */
    private function extract_year(array $groups): ?int {
        if (isset($groups['year']) && $groups['year'] !== '') {
            return (int) $groups['year'];
        }
        return null;
    }

    /**
     * Extract month from capture groups, resolving month names.
     *
     * @param array  $groups  Named capture groups.
     * @param string $pattern Pattern tag for language detection.
     * @return int|null Month number 1-12.
     */
    private function extract_month(array $groups, string $pattern): ?int {
        if (isset($groups['month']) && $groups['month'] !== '') {
            return (int) $groups['month'];
        }
        if (isset($groups['monthname']) && $groups['monthname'] !== '') {
            $name = $groups['monthname'];
            if (str_starts_with($pattern, 'de_')) {
                return text_datetime_extractor::resolve_de_month($name);
            }
            if (str_starts_with($pattern, 'en_')) {
                return text_datetime_extractor::resolve_en_month($name);
            }
            return text_datetime_extractor::resolve_de_month($name)
                ?? text_datetime_extractor::resolve_en_month($name);
        }
        return null;
    }

    /**
     * Extract day from capture groups.
     *
     * @param array $groups Named capture groups.
     * @return int|null
     */
    private function extract_day(array $groups): ?int {
        if (isset($groups['day']) && $groups['day'] !== '') {
            return (int) $groups['day'];
        }
        return null;
    }

    /**
     * Extract hour from capture groups, applying AM/PM conversion.
     *
     * @param array $groups Named capture groups.
     * @return int|null 0-23.
     */
    private function extract_hour(array $groups): ?int {
        if (!isset($groups['hour']) || $groups['hour'] === '') {
            return null;
        }
        $hour = (int) $groups['hour'];
        if (isset($groups['ampm']) && $groups['ampm'] !== '') {
            $ampm = strtolower($groups['ampm']);
            if ($ampm === 'pm' && $hour < 12) {
                $hour += 12;
            } else if ($ampm === 'am' && $hour === 12) {
                $hour = 0;
            }
        }
        return ($hour >= 0 && $hour <= 23) ? $hour : null;
    }

    /**
     * Extract minute from capture groups.
     *
     * @param array $groups Named capture groups.
     * @return int|null 0-59.
     */
    private function extract_minute(array $groups): ?int {
        if (!isset($groups['minute']) || $groups['minute'] === '') {
            return null;
        }
        $min = (int) $groups['minute'];
        return ($min >= 0 && $min <= 59) ? $min : null;
    }
}
