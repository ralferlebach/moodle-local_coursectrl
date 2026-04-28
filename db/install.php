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
 * Post-install steps for local_coursectrl.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run once after the plugin is installed.
 *
 * Tries to warm the holiday cache synchronously so the dashboard shows
 * holidays immediately. If the synchronous fetch fails for any reason
 * (network down, providers misconfigured, timeout), an adhoc task is queued
 * as fallback so the next cron tick will retry.
 *
 * @return bool Always true.
 */
function xmldb_local_coursectrl_install(): bool {
    try {
        \local_coursectrl\task\warm_calendar_cache::do_warm();
    } catch (\Throwable $e) {
        debugging(
            'local_coursectrl synchronous warm during install failed: ' . $e->getMessage(),
            DEBUG_DEVELOPER
        );
    }
    // Always queue the adhoc fallback. If do_warm() succeeded, the task is
    // a cheap no-op (cache already warm). If it failed, the task retries.
    \local_coursectrl\task\warm_calendar_cache_adhoc::queue();
    return true;
}
