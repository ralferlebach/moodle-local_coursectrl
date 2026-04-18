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
 * Text datetime rewriter for confirmed text hits.
 *
 * Takes a list of confirmed text_hit records (typically those with
 * confidence 'safe' or manually approved 'ambiguous' hits) together
 * with a delta in seconds and rewrites the matched substrings in the
 * original text. The rewriter works backwards from the highest offset
 * to avoid invalidating earlier offsets when replacements have different
 * lengths than the originals.
 *
 * The rewriter does NOT write back to the database. It returns the
 * rewritten text and a list of applied changes so the caller can
 * decide whether and how to persist.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\text;

/**
 * Stateless rewriter: original text + confirmed hits + delta → rewritten text.
 */
class text_datetime_rewriter {
    /** @var text_datetime_parser */
    private text_datetime_parser $parser;

    /** @var text_datetime_extractor */
    private text_datetime_extractor $extractor;

    /**
     * Constructor.
     *
     * @param text_datetime_parser|null    $parser    Optional custom parser.
     * @param text_datetime_extractor|null $extractor Optional custom extractor.
     */
    public function __construct(
        ?text_datetime_parser $parser = null,
        ?text_datetime_extractor $extractor = null
    ) {
        $this->parser = $parser ?? new text_datetime_parser();
        $this->extractor = $extractor ?? new text_datetime_extractor();
    }

    /**
     * Rewrite date references in a text by applying a delta.
     *
     * Each replacement descriptor must contain at least:
     *   - matchedtext:     string (original matched substring)
     *   - normalizedvalue: string (ISO 8601, required for shifting)
     *   - contextjson:     string (JSON with 'offset' and 'length')
     *
     * @param string  $text         Original text.
     * @param array[] $hitrecords   Confirmed hit records (persistent rows or arrays).
     * @param int     $delta        Seconds to shift (positive = forward).
     * @return array{text: string, applied: array[], skipped: array[]}
     */
    public function rewrite(string $text, array $hitrecords, int $delta): array {
        $replacements = [];
        $skipped = [];

        foreach ($hitrecords as $record) {
            $matched = $this->get_field($record, 'matchedtext');
            $normalized = $this->get_field($record, 'normalizedvalue');
            $contextraw = $this->get_field($record, 'contextjson');

            if ($normalized === null || $normalized === '') {
                $skipped[] = ['matchedtext' => $matched, 'reason' => 'no_normalized_value'];
                continue;
            }

            $context = json_decode($contextraw, true);
            if (!is_array($context) || !isset($context['offset'])) {
                $skipped[] = ['matchedtext' => $matched, 'reason' => 'no_offset'];
                continue;
            }

            $offset = (int) $context['offset'];
            $length = (int) ($context['length'] ?? strlen($matched));

            // Verify the text at this offset still matches.
            $actual = substr($text, $offset, $length);
            if ($actual !== $matched) {
                // Offset was computed on plain text after HTML stripping.
                // If the original content contains HTML tags, the byte offset
                // in the raw HTML may differ — fall back to a literal search.
                // Only attempt this when the text actually contains HTML;
                // a plain-text offset mismatch is a genuine data error → skip.
                if (!str_contains($text, '<') || !str_contains($text, $matched)) {
                    $skipped[] = [
                        'matchedtext' => $matched,
                        'reason' => 'offset_mismatch',
                        'expected' => $matched,
                        'actual' => $actual,
                    ];
                    continue;
                }
                // Use position of first occurrence in the raw HTML text.
                $offset = strpos($text, $matched);
                $length = strlen($matched);
            }

            // Compute shifted ISO value.
            $shiftediso = $this->parser->shift_iso($normalized, $delta);
            if ($shiftediso === null) {
                $skipped[] = ['matchedtext' => $matched, 'reason' => 'shift_failed'];
                continue;
            }

            // Format replacement string in the same style as the original.
            $replacement = $this->format_replacement($matched, $shiftediso, $context['pattern'] ?? '');

            $replacements[] = [
                'offset' => $offset,
                'length' => $length,
                'old' => $matched,
                'new' => $replacement,
                'oldiso' => $normalized,
                'newiso' => $shiftediso,
            ];
        }

        // Sort by offset descending to apply from end to start.
        usort($replacements, fn($a, $b) => $b['offset'] <=> $a['offset']);

        $applied = [];
        foreach ($replacements as $r) {
            $text = substr_replace($text, $r['new'], $r['offset'], $r['length']);
            $applied[] = $r;
        }

        // Reverse applied list to return in original order.
        $applied = array_reverse($applied);

        return [
            'text' => $text,
            'applied' => $applied,
            'skipped' => $skipped,
        ];
    }

    /**
     * Format a replacement string matching the style of the original.
     *
     * Attempts to preserve the original formatting style (DE named,
     * DE numeric, EN named, ISO) so the replacement blends in naturally.
     *
     * @param string $original Original matched substring.
     * @param string $iso      Shifted ISO 8601 value.
     * @param string $pattern  Pattern tag from the extractor.
     * @return string Formatted replacement.
     */
    private function format_replacement(string $original, string $iso, string $pattern): string {
        $hastime = str_contains($iso, 'T');
        if ($hastime) {
            $dt = \DateTime::createFromFormat('Y-m-d\TH:i', $iso);
        } else {
            $dt = \DateTime::createFromFormat('Y-m-d', $iso);
        }
        if ($dt === false) {
            return $iso;
        }

        $demonths = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
        ];
        $enmonths = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
        ];

        $day = (int) $dt->format('j');
        $month = (int) $dt->format('n');
        $year = $dt->format('Y');
        $time = $hastime ? $dt->format('H:i') : '';

        switch ($pattern) {
            case 'de_dmy_full':
                $result = $day . '. ' . $demonths[$month] . ' ' . $year;
                if ($time !== '' && str_contains($original, 'Uhr')) {
                    $result .= ', ' . $time . ' Uhr';
                } else if ($time !== '') {
                    $result .= ', ' . $time;
                }
                return $result;

            case 'de_dmy_noyear':
                // Preserve format without year: "17. Mai" or "17. Mai 10:00 Uhr".
                $result = $day . '. ' . $demonths[$month];
                if ($time !== '' && str_contains($original, 'Uhr')) {
                    $result .= ' ' . $time . ' Uhr';
                } else if ($time !== '') {
                    $result .= ' ' . $time;
                }
                return $result;

            case 'de_numeric_full':
                $result = sprintf('%02d.%02d.%s', $day, $month, $year);
                if ($time !== '') {
                    $result .= ', ' . $time;
                }
                return $result;

            case 'de_numeric_noyear':
                // Preserve format without year: "19.05." or "19.05. 10:00 Uhr".
                $result = sprintf('%02d.%02d.', $day, $month);
                if ($time !== '' && str_contains($original, 'Uhr')) {
                    $result .= ' ' . $time . ' Uhr';
                } else if ($time !== '') {
                    $result .= ' ' . $time;
                }
                return $result;

            case 'en_mdy_full':
                $result = $enmonths[$month] . ' ' . $day . ', ' . $year;
                if ($time !== '' && preg_match('/[AaPp][Mm]/', $original)) {
                    $hour = (int) $dt->format('G');
                    $min = $dt->format('i');
                    $ampm = $hour >= 12 ? 'PM' : 'AM';
                    $hour12 = $hour % 12;
                    if ($hour12 === 0) {
                        $hour12 = 12;
                    }
                    $result .= ' at ' . $hour12 . ':' . $min . ' ' . $ampm;
                } else if ($time !== '') {
                    $result .= ' at ' . $time;
                }
                return $result;

            case 'us_numeric_full':
                return sprintf('%02d/%02d/%s', $month, $day, $year);

            case 'iso_ymd':
            default:
                if ($hastime) {
                    return $dt->format('Y-m-d\TH:i');
                }
                return $dt->format('Y-m-d');
        }
    }

    /**
     * Get a field from a persistent object or plain array.
     *
     * @param mixed  $record Persistent or array.
     * @param string $field  Field name.
     * @return mixed|null
     */
    private function get_field($record, string $field) {
        if (is_object($record) && method_exists($record, 'get')) {
            return $record->get($field);
        }
        if (is_array($record)) {
            return $record[$field] ?? null;
        }
        return $record->$field ?? null;
    }
}
