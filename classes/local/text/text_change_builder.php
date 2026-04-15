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
 * Course-wide text-datetime scanning orchestrator.
 *
 * Iterates over all text_items from the inventory, runs the extractor,
 * parser and classifier pipeline on each, and persists the results into
 * the local_coursectrl_text_hit table. Existing hits for the course are
 * purged before a fresh scan so the table always reflects the current
 * course state.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\text;

use local_coursectrl\local\entity\text_item;
use local_coursectrl\local\persistent\text_hit;

/**
 * Orchestrates text scanning for an entire course.
 */
class text_change_builder {
    /** @var text_datetime_extractor */
    private text_datetime_extractor $extractor;

    /** @var text_datetime_parser */
    private text_datetime_parser $parser;

    /** @var text_hit_classifier */
    private text_hit_classifier $classifier;

    /**
     * Constructor.
     *
     * @param text_datetime_extractor|null $extractor  Optional custom extractor.
     * @param text_datetime_parser|null    $parser     Optional custom parser.
     * @param text_hit_classifier|null     $classifier Optional custom classifier.
     */
    public function __construct(
        ?text_datetime_extractor $extractor = null,
        ?text_datetime_parser $parser = null,
        ?text_hit_classifier $classifier = null
    ) {
        $this->extractor = $extractor ?? new text_datetime_extractor();
        $this->parser = $parser ?? new text_datetime_parser();
        $this->classifier = $classifier ?? new text_hit_classifier();
    }

    /**
     * Scan all given text items and return hit descriptors.
     *
     * Does NOT persist anything. Use scan_and_persist() for DB storage.
     *
     * Each returned hit is an associative array:
     *   - entitytype:      string
     *   - entityid:        int
     *   - fieldname:       string
     *   - matchedtext:     string
     *   - normalizedvalue: string|null
     *   - confidence:      string (safe|ambiguous|informational)
     *   - contextjson:     string (JSON with before/after/offset)
     *
     * @param text_item[] $textitems Text items to scan.
     * @return array[] List of hit descriptors.
     */
    public function scan(array $textitems): array {
        $results = [];
        foreach ($textitems as $item) {
            $itemhits = $this->scan_single($item);
            foreach ($itemhits as $hit) {
                $results[] = $hit;
            }
        }
        return $results;
    }

    /**
     * Scan all given text items, purge old hits for the course, and persist.
     *
     * @param int         $courseid  Course id for scoping the purge.
     * @param text_item[] $textitems Text items to scan.
     * @return array Summary with counts: total, safe, ambiguous, informational.
     */
    public function scan_and_persist(int $courseid, array $textitems): array {
        global $DB;

        // Purge existing hits for this course.
        $DB->delete_records('local_coursectrl_text_hit', ['courseid' => $courseid]);

        $hits = $this->scan($textitems);

        $summary = [
            'total' => count($hits),
            'safe' => 0,
            'ambiguous' => 0,
            'informational' => 0,
        ];

        foreach ($hits as $hitdata) {
            $persistent = new text_hit(0, (object) [
                'courseid' => $courseid,
                'entitytype' => $hitdata['entitytype'],
                'entityid' => $hitdata['entityid'],
                'fieldname' => $hitdata['fieldname'],
                'matchedtext' => $hitdata['matchedtext'],
                'normalizedvalue' => $hitdata['normalizedvalue'],
                'confidence' => $hitdata['confidence'],
                'contextjson' => $hitdata['contextjson'],
            ]);
            $persistent->create();

            if (isset($summary[$hitdata['confidence']])) {
                $summary[$hitdata['confidence']]++;
            }
        }

        return $summary;
    }

    /**
     * Scan a single text item and return hit descriptors.
     *
     * @param text_item $item The text item to scan.
     * @return array[] List of hit descriptors for this item.
     */
    private function scan_single(text_item $item): array {
        $rawmatches = $this->extractor->extract($item->content);
        if (empty($rawmatches)) {
            return [];
        }

        $hits = [];
        foreach ($rawmatches as $match) {
            $normalized = $this->parser->normalise($match);
            $confidence = $this->classifier->classify($match, $normalized);

            // Build context excerpt.
            $offset = $match['offset'];
            $matchlen = strlen($match['match']);
            $plaintext = strip_tags($item->content);
            $before = mb_substr($plaintext, max(0, $offset - 30), min($offset, 30));
            $after = mb_substr($plaintext, $offset + $matchlen, 30);

            $hits[] = [
                'entitytype' => $item->entitytype,
                'entityid' => $item->entityid,
                'fieldname' => $item->fieldname,
                'matchedtext' => $match['match'],
                'normalizedvalue' => $normalized,
                'confidence' => $confidence,
                'contextjson' => json_encode([
                    'before' => $before,
                    'after' => $after,
                    'offset' => $offset,
                    'length' => $matchlen,
                    'pattern' => $match['pattern'],
                ]),
            ];
        }
        return $hits;
    }
}
