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
 * Calendar grid builder for the mini-calendar block on the timeline page.
 *
 * Takes a list of date entries (from date_collector) and a course start
 * date, and builds a month-wise grid structure suitable for rendering
 * in the template: rows of months, each with 7-column weeks and tiny
 * day cells that carry the entries at that day for hover tooltips.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

/**
 * Stateless builder: date entries → calendar grid.
 */
class calendar_grid_builder {
    /**
     * Build a calendar grid from date entries.
     *
     * Returns an array of month objects; each month has a label and a
     * list of weeks, each week has 7 day cells. Every day cell carries:
     *   - day: int (day of month)
     *   - inmonth: bool (false for leading/trailing padding cells)
     *   - timestamp: int (midnight UTC of that day)
     *   - daykey: string (Y-m-d)
     *   - count: int (number of entries on this day)
     *   - entries: array (per-entry summary for tooltip)
     *   - ispast: bool
     *
     * @param int   $startdate Course start date (unix timestamp).
     * @param int   $enddate   Course end date, 0 or null = startdate + 6 months.
     * @param array $entries   Date entries from date_collector::collect().
     * @param int   $now       Reference time for 'ispast' flag.
     * @return array[] List of month blocks.
     */
    public function build(int $startdate, ?int $enddate, array $entries, int $now): array {
        if ($startdate <= 0) {
            $startdate = $now;
        }
        if (!$enddate || $enddate <= $startdate) {
            $enddate = strtotime('+6 months', $startdate);
        }

        // Group entries by day (Y-m-d).
        $byday = [];
        foreach ($entries as $entry) {
            $daykey = date('Y-m-d', $entry['timestamp']);
            if (!isset($byday[$daykey])) {
                $byday[$daykey] = [];
            }
            $byday[$daykey][] = $entry;
        }

        $months = [];
        $cursor = strtotime(date('Y-m-01', $startdate));
        $end = strtotime(date('Y-m-01', $enddate));

        while ($cursor <= $end) {
            $months[] = $this->build_month($cursor, $byday, $now);
            $cursor = strtotime('+1 month', $cursor);
        }

        return $months;
    }

    /**
     * Build a single month block.
     *
     * @param int   $monthstart First-of-month timestamp at midnight.
     * @param array $byday      Entries grouped by Y-m-d.
     * @param int   $now        Reference time for ispast flag.
     * @return array Month block with label and weeks.
     */
    private function build_month(int $monthstart, array $byday, int $now): array {
        $year = (int) date('Y', $monthstart);
        $month = (int) date('n', $monthstart);
        $daysinmonth = (int) date('t', $monthstart);

        // ISO week: Monday = 1 ... Sunday = 7.
        $firstdow = (int) date('N', $monthstart);
        $lastdow = (int) date('N', mktime(0, 0, 0, $month, $daysinmonth, $year));

        // Leading padding (cells from previous month's end).
        $leadpad = $firstdow - 1;
        // Trailing padding (cells from next month's start).
        $trailpad = 7 - $lastdow;

        $cells = [];
        for ($i = 0; $i < $leadpad; $i++) {
            $cells[] = [
                'day' => 0,
                'inmonth' => false,
                'timestamp' => 0,
                'daykey' => '',
                'count' => 0,
                'entries' => [],
                'ispast' => false,
            ];
        }

        for ($d = 1; $d <= $daysinmonth; $d++) {
            $dayts = mktime(0, 0, 0, $month, $d, $year);
            $daykey = date('Y-m-d', $dayts);
            $entries = $byday[$daykey] ?? [];
            $tooltip = [];
            foreach ($entries as $entry) {
                $tooltip[] = [
                    'cmid' => $entry['cmid'],
                    'name' => $entry['name'],
                    'modname' => $entry['modname'],
                    'field' => $entry['fieldlabel'],
                    'time' => date('H:i', $entry['timestamp']),
                ];
            }
            $cells[] = [
                'day' => $d,
                'inmonth' => true,
                'timestamp' => $dayts,
                'daykey' => $daykey,
                'count' => count($entries),
                'hasentries' => count($entries) > 0,
                'entries' => $tooltip,
                'ispast' => ($dayts + 86400) < $now,
                'istoday' => date('Y-m-d', $now) === $daykey,
            ];
        }

        for ($i = 0; $i < $trailpad; $i++) {
            $cells[] = [
                'day' => 0,
                'inmonth' => false,
                'timestamp' => 0,
                'daykey' => '',
                'count' => 0,
                'entries' => [],
                'ispast' => false,
            ];
        }

        // Chunk into weeks of 7.
        $weeks = [];
        foreach (array_chunk($cells, 7) as $row) {
            $weeks[] = ['cells' => $row];
        }

        return [
            'year' => $year,
            'month' => $month,
            'label' => userdate($monthstart, get_string('strftimemonthyear', 'core_langconfig')),
            'weeks' => $weeks,
        ];
    }
}
