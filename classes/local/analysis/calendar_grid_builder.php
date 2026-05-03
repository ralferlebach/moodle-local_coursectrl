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
     *   - istoday: bool
     *   - isholiday: bool    (requires calman parameter)
     *   - isschoolholiday: bool
     *   - hasholidays: bool
     *   - holidaynames: array
     * Month objects additionally contain: monthlabel, monthkey, iscurrentmonth.
     *
     * @param int                  $startdate Course start date (unix timestamp).
     * @param int|null             $enddate   Course end date (unix timestamp). 0 or null means
     *                                        no course end is configured; the builder will then
     *                                        compute a smart end: the later of (a) now + 3 months
     *                                        and (b) the end of the month two months after the
     *                                        latest entry. Falls back to startdate + 3 months
     *                                        when there are no entries at all.
     * @param array                $entries   Date entries from date_collector::collect().
     * @param int                  $now       Reference time for 'ispast' flag.
     * @param \local_coursectrl\manager\calendar_manager|null $calman Optional for holiday markers.
     * @return array[] List of month blocks.
     */
    public function build(
        int $startdate,
        ?int $enddate,
        array $entries,
        int $now,
        ?\local_coursectrl\manager\calendar_manager $calman = null
    ): array {
        if ($startdate <= 0) {
            $startdate = $now;
        }
        if (!$enddate || $enddate <= $startdate) {
            // No course end date configured — use a smart range so the
            // calendar does not stop arbitrarily at startdate + 6 months.
            //
            // Rule: show at least "now + N months" (N = calendar_lookahead_months
            // admin setting, default 3); if there are dated
            // entries, also cover until the end of the month two months
            // after the latest entry (the "übernächsten Monatsende");
            // use whichever horizon is later.
            $latestentry = 0;
            foreach ($entries as $entry) {
                if ((int) $entry['timestamp'] > $latestentry) {
                    $latestentry = (int) $entry['timestamp'];
                }
            }
            // Read the admin-configurable lookahead (default 3 months).
            $lookahead = max(1, (int) get_config('local_coursectrl', 'calendar_lookahead_months'));
            $threemonths = strtotime('+' . $lookahead . ' months', $now);
            if ($latestentry > 0) {
                // End of the month two months after the month of the last entry.
                // E.g. last entry in April → target month = June → end of June.
                $lastmonth  = (int) date('n', $latestentry);
                $lastyear   = (int) date('Y', $latestentry);
                $targetmonth = $lastmonth + 2;
                $targetyear  = $lastyear;
                if ($targetmonth > 12) {
                    $targetmonth -= 12;
                    $targetyear  += 1;
                }
                // First moment of the month after target = last moment of target month.
                $overmonthend = mktime(0, 0, 0, $targetmonth + 1, 1, $targetyear);
                if ($targetmonth === 12) {
                    $overmonthend = mktime(0, 0, 0, 1, 1, $targetyear + 1);
                }
                $enddate = max($threemonths, $overmonthend);
            } else {
                $enddate = $threemonths;
            }
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

        // Load holiday data when a calendar manager is provided.
        $holidays = [];
        if ($calman !== null) {
            $holidays = $calman->get_holidays_for_range($startdate, $enddate);
        }

        $months = [];
        $cursor = strtotime(date('Y-m-01', $startdate));
        $end = strtotime(date('Y-m-01', $enddate));

        while ($cursor <= $end) {
            $months[] = $this->build_month($cursor, $byday, $now, $holidays);
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
     * @param array $holidays Holiday entries keyed by Y-m-d date string.
     * @return array Month block with label and weeks.
     */
    private function build_month(int $monthstart, array $byday, int $now, array $holidays = []): array {
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
                'cellclass' => $this->build_cell_class(false, false, false, false),
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
            // Holiday markers from optional calendar_manager data.
            $dayholidays   = $holidays[$daykey] ?? [];
            $isholiday     = !empty($dayholidays);
            $isschoolhol   = false;
            $holidaynames  = [];
            foreach ($dayholidays as $hevent) {
                if (($hevent['category'] ?? '') === 'school_holiday') {
                    $isschoolhol = true;
                }
                $holidaynames[] = ['name' => $hevent['name']];
            }
            $cells[] = [
                'day'            => $d,
                'inmonth'        => true,
                'timestamp'      => $dayts,
                'daykey'         => $daykey,
                'count'          => count($entries),
                'hasentries'     => count($entries) > 0,
                'entries'        => $tooltip,
                'ispast'         => ($dayts + 86400) < $now,
                'istoday'        => date('Y-m-d', $now) === $daykey,
                'isholiday'      => $isholiday,
                'isschoolholiday' => $isschoolhol,
                'hasholidays'    => $isholiday,
                'holidaynames'   => $holidaynames,
                'cellclass'      => $this->build_cell_class(
                    true,
                    count($entries) > 0,
                    $isholiday,
                    date('Y-m-d', $now) === $daykey
                ),
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
                'cellclass' => $this->build_cell_class(false, false, false, false),
            ];
        }

        // Find the first day with entries to link the month label to.
        $firstentrykey = '';
        foreach ($cells as $cell) {
            if (!empty($cell['inmonth']) && !empty($cell['hasentries'])) {
                $firstentrykey = $cell['daykey'];
                break;
            }
        }

        // Chunk into weeks of 7.
        $weeks = [];
        foreach (array_chunk($cells, 7) as $row) {
            $weeks[] = ['days' => $row];
        }

        return [
            'year'           => $year,
            'month'          => $month,
            'label'          => userdate($monthstart, get_string('strftimemonthyear', 'core_langconfig')),
            'monthlabel'     => userdate($monthstart, get_string('strftimemonthyear', 'core_langconfig')),
            'monthkey'       => date('Y-m', $monthstart),
            'iscurrentmonth' => (date('Y-m') === date('Y-m', $monthstart)),
            'firstentrykey'  => $firstentrykey,
            'hasfirstentry'  => $firstentrykey !== '',
            'weeks'          => $weeks,
        ];
    }

    /**
     * Build the CSS class string for a calendar day cell.
     *
     * Priority: danger (entry+holiday) > primary (entry) > secondary (holiday).
     * Out-of-month cells always get the 'cc-out' class.
     *
     * @param bool $inmonth    Whether the day belongs to the displayed month.
     * @param bool $hasentries Whether there are date entries on this day.
     * @param bool $isholiday  Whether this day is a public holiday.
     * @param bool $istoday    Whether this day is today.
     * @return string Space-separated CSS class string.
     */
    private function build_cell_class(
        bool $inmonth,
        bool $hasentries,
        bool $isholiday,
        bool $istoday
    ): string {
        $classes = ['cc'];
        if (!$inmonth) {
            $classes[] = 'cc-out';
            return implode(' ', $classes);
        }
        if ($hasentries && $isholiday) {
            $classes[] = 'cc-danger';
        } else if ($hasentries) {
            $classes[] = 'cc-primary';
        } else if ($isholiday) {
            $classes[] = 'cc-secondary';
        }
        if ($istoday) {
            $classes[] = 'cc-today';
        }
        return implode(' ', $classes);
    }
}
