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
 * Tests for calendar_grid_builder.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\analysis\calendar_grid_builder::class)]
/**
 * Unit tests for calendar_grid_builder::build().
 *
 * @covers \local_coursectrl\local\analysis\calendar_grid_builder
 */
final class calendar_grid_builder_test extends \advanced_testcase {
    /**
     * Build a sample entry for testing.
     *
     * @param int    $ts      Timestamp.
     * @param int    $cmid    Course module id.
     * @param string $name    Activity name.
     * @return array
     */
    private function entry(int $ts, int $cmid = 100, string $name = 'Task'): array {
        return [
            'cmid' => $cmid,
            'name' => $name,
            'modname' => 'assign',
            'component' => 'mod_assign',
            'field' => 'duedate',
            'fieldlabel' => 'duedate',
            'timestamp' => $ts,
            'source' => 'adapter',
        ];
    }

    /**
     * A course over 3 months must produce 3 month blocks.
     * @covers \local_coursectrl\local\analysis\calendar_grid_builder
     */
    public function test_month_count(): void {
        $this->resetAfterTest();
        $builder = new calendar_grid_builder();
        $start = strtotime('2026-04-01');
        $end = strtotime('2026-06-15');

        $months = $builder->build($start, $end, [], $start);

        $this->assertCount(3, $months);
        $this->assertSame(2026, $months[0]['year']);
        $this->assertSame(4, $months[0]['month']);
        $this->assertSame(6, $months[2]['month']);
    }

    /**
     * No end date with no entries — calendar covers at least now + 3 months.
     * @covers \local_coursectrl\local\analysis\calendar_grid_builder
     */
    public function test_default_end_date_no_entries(): void {
        $this->resetAfterTest();
        $builder = new calendar_grid_builder();
        $now   = strtotime('2026-04-01');

        // No entries: must show at least April + 3 months ahead.
        $months = $builder->build($now, null, [], $now);

        $this->assertGreaterThanOrEqual(4, count($months));
    }

    /**
     * No end date with a late entry — calendar reaches end of month+2 of that entry.
     * @covers \local_coursectrl\local\analysis\calendar_grid_builder
     */
    public function test_default_end_date_with_late_entry(): void {
        $this->resetAfterTest();
        $builder = new calendar_grid_builder();
        $now   = strtotime('2026-01-01');

        // Entry in April 2026 — calendar must reach at least end of June 2026.
        $entry = [
            'timestamp'  => strtotime('2026-04-15'),
            'cmid'       => 1,
            'name'       => 'Test',
            'modname'    => 'assign',
            'fieldlabel' => 'Due date',
        ];
        $months = $builder->build($now, null, [$entry], $now);

        $last    = end($months);
        $lastkey = $last['monthkey'];
        $this->assertGreaterThanOrEqual(
            '2026-06',
            $lastkey,
            'Calendar must reach at least June 2026 when last entry is April 2026.'
        );
    }

    /**
     * Days with entries must be marked with count and entry list.
     * @covers \local_coursectrl\local\analysis\calendar_grid_builder
     */
    public function test_day_with_entries(): void {
        $this->resetAfterTest();
        $builder = new calendar_grid_builder();
        $start = strtotime('2026-04-01');
        $end = strtotime('2026-04-30');
        $entryts = strtotime('2026-04-15 14:00');

        $months = $builder->build($start, $end, [$this->entry($entryts)], $start);

        $this->assertCount(1, $months);
        $april = $months[0];

        // Find the cell for day 15.
        $targetday = null;
        foreach ($april['weeks'] as $week) {
            foreach ($week['days'] as $cell) {
                if ($cell['inmonth'] && $cell['day'] === 15) {
                    $targetday = $cell;
                    break 2;
                }
            }
        }

        $this->assertNotNull($targetday);
        $this->assertSame(1, $targetday['count']);
        $this->assertTrue($targetday['hasentries']);
        $this->assertCount(1, $targetday['entries']);
    }

    /**
     * Each week row must contain exactly 7 cells.
     * @covers \local_coursectrl\local\analysis\calendar_grid_builder
     */
    public function test_week_has_seven_cells(): void {
        $this->resetAfterTest();
        $builder = new calendar_grid_builder();
        $start = strtotime('2026-04-01');
        $end = strtotime('2026-04-30');

        $months = $builder->build($start, $end, [], $start);

        foreach ($months[0]['weeks'] as $week) {
            $this->assertCount(7, $week['days']);
        }
    }

    /**
     * Padding cells before/after the month must be inmonth=false.
     * @covers \local_coursectrl\local\analysis\calendar_grid_builder
     */
    public function test_padding_cells(): void {
        $this->resetAfterTest();
        $builder = new calendar_grid_builder();
        // April 2026: 1st is a Wednesday (ISO dow 3), so 2 leading pad cells.
        $start = strtotime('2026-04-01');
        $end = strtotime('2026-04-30');

        $months = $builder->build($start, $end, [], $start);

        $firstweek = $months[0]['weeks'][0];
        $this->assertFalse($firstweek['days'][0]['inmonth']);
        $this->assertFalse($firstweek['days'][1]['inmonth']);
        $this->assertTrue($firstweek['days'][2]['inmonth']);
        $this->assertSame(1, $firstweek['days'][2]['day']);
    }

    /**
     * Past days must be marked ispast=true.
     * @covers \local_coursectrl\local\analysis\calendar_grid_builder
     */
    public function test_past_flag(): void {
        $this->resetAfterTest();
        $builder = new calendar_grid_builder();
        $start = strtotime('2026-04-01');
        $end = strtotime('2026-04-30');
        // Simulated "now" is in the middle of the month.
        $now = strtotime('2026-04-20');

        $months = $builder->build($start, $end, [], $now);

        // Day 10 must be past, day 25 must be future.
        foreach ($months[0]['weeks'] as $week) {
            foreach ($week['days'] as $cell) {
                if ($cell['inmonth'] && $cell['day'] === 10) {
                    $this->assertTrue($cell['ispast']);
                }
                if ($cell['inmonth'] && $cell['day'] === 25) {
                    $this->assertFalse($cell['ispast']);
                }
            }
        }
    }

    /**
     * Today flag must be set correctly.
     * @covers \local_coursectrl\local\analysis\calendar_grid_builder
     */
    public function test_today_flag(): void {
        $this->resetAfterTest();
        $builder = new calendar_grid_builder();
        $start = strtotime('2026-04-01');
        $end = strtotime('2026-04-30');
        $now = strtotime('2026-04-15 12:00');

        $months = $builder->build($start, $end, [], $now);

        foreach ($months[0]['weeks'] as $week) {
            foreach ($week['days'] as $cell) {
                if ($cell['inmonth'] && $cell['day'] === 15) {
                    $this->assertTrue($cell['istoday']);
                }
            }
        }
    }

    /**
     * Zero startdate must fall back to "now" for the first month.
     * @covers \local_coursectrl\local\analysis\calendar_grid_builder
     */
    public function test_zero_startdate_fallback(): void {
        $this->resetAfterTest();
        $builder = new calendar_grid_builder();
        $now = strtotime('2026-04-15');

        $months = $builder->build(0, null, [], $now);

        $this->assertNotEmpty($months);
        $this->assertSame(2026, $months[0]['year']);
        $this->assertSame(4, $months[0]['month']);
    }
}
