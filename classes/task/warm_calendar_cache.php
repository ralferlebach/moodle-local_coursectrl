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
 * Scheduled task that pre-fetches holiday data from external calendar
 * providers and stores it in the local_coursectrl/caldata cache.
 *
 * Outbound HTTP from coursectrlcal_* providers is gated by a static flag on
 * abstract_calendar_provider; only this task lifts the gate. Without this
 * task, page renders find the cache empty and skip holiday rendering. With
 * this task running nightly, the cache stays warm for the configured year
 * range and page renders are HTTP-free.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\task;

use local_coursectrl\local\contract\abstract_calendar_provider;
use local_coursectrl\manager\calendar_manager;

/**
 * Pre-fetch holiday data for all enabled calendar providers.
 */
class warm_calendar_cache extends \core\task\scheduled_task {
    /** @var int Number of years to warm starting from the current year. */
    private const YEARS_TO_WARM = 2;

    /**
     * Localised task name shown in the admin task list.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_warm_calendar_cache', 'local_coursectrl');
    }

    /**
     * Run the warmer.
     *
     * @return void
     */
    public function execute(): void {
        self::do_warm();
    }

    /**
     * Static entry point shared with the adhoc twin and with the
     * synchronous warm helper used from install / upgrade / settings hooks.
     *
     * Lifts the global HTTP gate on abstract_calendar_provider for the
     * duration of this call only, then iterates the warm range. The
     * try/finally guarantees the gate is closed again even if a provider
     * throws.
     *
     * @return void
     */
    public static function do_warm(): void {
        abstract_calendar_provider::set_allow_http(true);
        try {
            $manager = new calendar_manager();
            $thisyear = (int) date('Y');
            for ($y = 0; $y < self::YEARS_TO_WARM; $y++) {
                $year = $thisyear + $y;
                for ($m = 1; $m <= 12; $m++) {
                    $from = mktime(0, 0, 0, $m, 1, $year);
                    $to   = mktime(23, 59, 59, $m, (int) date('t', $from), $year);
                    $manager->get_holidays_for_range($from, $to);
                }
            }
        } finally {
            abstract_calendar_provider::set_allow_http(false);
        }
    }
}
