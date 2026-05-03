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
 * Adhoc-task twin of warm_calendar_cache.
 *
 * Plugin install, plugin upgrade, calendar-setting changes and post-purge
 * self-heal queue this task so the holiday cache is repopulated on the very
 * next cron tick rather than waiting up to 24 hours for the nightly schedule.
 *
 * Identical execution body as the scheduled twin — both delegate to
 * warm_calendar_cache::do_warm(). Queueing goes through
 * \core\task\manager::reschedule_or_queue_adhoc_task() so multiple triggers
 * (e.g. an admin saving several settings in a row) collapse into one
 * pending run.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\task;

/**
 * Adhoc twin of warm_calendar_cache.
 */
class warm_calendar_cache_adhoc extends \core\task\adhoc_task {
    /**
     * Run the warmer once.
     *
     * @return void
     */
    public function execute(): void {
        warm_calendar_cache::do_warm();
    }

    /**
     * Queue an instance of this task. Idempotent.
     *
     * @return void
     */
    public static function queue(): void {
        $task = new self();
        $task->set_component('local_coursectrl');
        \core\task\manager::reschedule_or_queue_adhoc_task($task);
    }
}
