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
 * Post-install hook for local_coursectrl.
 *
 * Runs once after the plugin tables have been created by the installer.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
 * Queues the holiday-cache warm-up adhoc task. The actual warming is done
 * asynchronously on the next cron tick to keep the installer fast and free
 * of external network calls.
 *
 * @return bool Always true.
 */
function xmldb_local_coursectrl_install(): bool {
    // Queue the adhoc task so the first cron tick warms the holiday cache.
    // Synchronous warming is intentionally avoided in install to keep the
    // installer fast and deterministic (no external network calls).
    \local_coursectrl\task\warm_calendar_cache_adhoc::queue();
    return true;
}
