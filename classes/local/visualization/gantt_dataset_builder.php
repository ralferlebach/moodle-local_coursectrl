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
 * Gantt dataset builder for the Course Control Hub.
 *
 * Collects all date entries for a course (adapter dates, completionexpected,
 * availability date conditions) and organises them into a Gantt row structure
 * where each CM with at least one date becomes a row and each date entry
 * becomes a bar marker within that row.
 *
 * The builder also computes the global time window (mints / maxts) so that
 * the rendering layer can normalise bar positions to percentages without any
 * further calculation.
 *
 * Rows are sorted by the earliest bar timestamp in each row so that the
 * Gantt chart reads chronologically from left to right and top to bottom.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\visualization;

use local_coursectrl\local\analysis\date_collector;
use local_coursectrl\local\entity\cm_item;
use local_coursectrl\manager\calendar_manager;

/**
 * Builds a Gantt row dataset from a set of CMs.
 */
class gantt_dataset_builder {
    /** @var date_collector */
    private date_collector $collector;

    /**
     * Constructor.
     *
     * @param date_collector|null $collector Optional custom collector for DI/testing.
     */
    public function __construct(?date_collector $collector = null) {
        $this->collector = $collector ?? new date_collector();
    }

    /**
     * Build the Gantt dataset.
     *
     * @param cm_item[]             $cms    Course modules keyed by cmid.
     * @param calendar_manager|null $calman Optional calendar manager for holiday bands.
     * @return array{
     *     rows: array,
     *     mints: int,
     *     maxts: int,
     *     hasdata: bool,
     *     rowcount: int,
     *     holidaybands: array,
     *     hasholidaybands: bool
     * }
     */
    public function build(array $cms, ?calendar_manager $calman = null): array {
        if (empty($cms)) {
            return $this->empty_result();
        }

        $bycm = $this->collector->collect_grouped_by_cm($cms);

        // Build per-CM rows, skipping CMs with no date entries.
        $rows = [];
        foreach ($cms as $cm) {
            $entries = $bycm[$cm->id] ?? [];
            if (empty($entries)) {
                continue;
            }
            $bars = [];
            foreach ($entries as $entry) {
                $bars[] = [
                    'field' => $entry['field'],
                    'fieldlabel' => $entry['fieldlabel'],
                    'timestamp' => $entry['timestamp'],
                    'source' => $entry['source'],
                ];
            }
            // Sort bars within row chronologically.
            usort($bars, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
            $rows[] = [
                'cmid' => $cm->id,
                'name' => $cm->name,
                'modname' => $cm->modname,
                'component' => $cm->get_component(),
                'bars' => $bars,
                'rowmints' => $bars[0]['timestamp'],
            ];
        }

        if (empty($rows)) {
            return $this->empty_result();
        }

        // Sort rows by earliest bar timestamp.
        usort($rows, fn($a, $b) => $a['rowmints'] <=> $b['rowmints']);

        // Compute global time window.
        $mints = PHP_INT_MAX;
        $maxts = 0;
        foreach ($rows as $row) {
            foreach ($row['bars'] as $bar) {
                $mints = min($mints, $bar['timestamp']);
                $maxts = max($maxts, $bar['timestamp']);
            }
        }

        // Strip internal sort key.
        foreach ($rows as &$row) {
            unset($row['rowmints']);
        }
        unset($row);

        return [
            'rows' => $rows,
            'mints' => $mints,
            'maxts' => $maxts,
            'hasdata' => true,
            'rowcount' => count($rows),
            'holidaybands' => $this->build_holiday_bands($mints, $maxts, $calman),
            'hasholidaybands' => $calman !== null,
        ];
    }

    /**
     * Return an empty dataset when no date entries exist.
     *
     * @return array
     */
    private function empty_result(): array {
        return [
            'rows' => [],
            'mints' => 0,
            'maxts' => 0,
            'hasdata' => false,
            'rowcount' => 0,
            'holidaybands' => [],
            'hasholidaybands' => false,
        ];
    }

    /**
     * Build a list of holiday band descriptors for the Gantt renderer.
     *
     * Each band represents a single special day or a contiguous run of
     * special days of the same category. The renderer uses these to paint
     * semi-transparent background bands across all rows.
     *
     * @param int                   $mints  Global min timestamp of the Gantt.
     * @param int                   $maxts  Global max timestamp of the Gantt.
     * @param calendar_manager|null $calman Calendar manager, or null.
     * @return array[] Each entry: {from_ts, to_ts, category, names[]}.
     */
    private function build_holiday_bands(
        int $mints,
        int $maxts,
        ?calendar_manager $calman
    ): array {
        if ($calman === null || $mints <= 0 || $maxts <= 0) {
            return [];
        }
        $holidays = $calman->get_holidays_for_range($mints, $maxts);
        if (empty($holidays)) {
            return [];
        }
        ksort($holidays);
        $bands = [];
        foreach ($holidays as $datekey => $events) {
            $ts = strtotime('!' . $datekey);
            if ($ts === false) {
                continue;
            }
            $category = 'custom';
            $names = [];
            foreach ($events as $ev) {
                $names[] = $ev['name'];
                if ($ev['category'] === 'public_holiday') {
                    $category = 'public_holiday';
                } else if ($ev['category'] === 'school_holiday' && $category !== 'public_holiday') {
                    $category = 'school_holiday';
                }
            }
            $bands[] = [
                'from_ts' => $ts,
                'to_ts' => $ts + 86399,
                'category' => $category,
                'names' => $names,
                'label' => implode(', ', array_unique($names)),
            ];
        }
        return $bands;
    }
}
