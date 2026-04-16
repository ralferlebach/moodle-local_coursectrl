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
 * Interface contract for coursectrlcal_* calendar provider subplugins.
 *
 * A calendar provider answers one question: "which days in a given month
 * are special (holidays, free periods, etc.)?" The result is a flat map
 * keyed by ISO date string ('Y-m-d'), where each value is a list of
 * named events on that day. Multiple events per day are allowed.
 *
 * Day entries carry:
 *   name      (string) — Human-readable event name (localised if possible).
 *   category  (string) — 'public_holiday' | 'school_holiday' | 'custom'.
 *   source    (string) — Component name of the provider (for attribution).
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\contract;

/**
 * Contract for all calendar provider subplugins.
 */
interface calendar_provider {
    /**
     * Return the frankenstyle component name of this provider.
     *
     * @return string e.g. 'coursectrlcal_nager'
     */
    public static function component(): string;

    /**
     * Whether this provider is currently enabled in plugin settings.
     *
     * @return bool
     */
    public function is_enabled(): bool;

    /**
     * Whether this provider is operational (API reachable, config valid).
     *
     * This check should be lightweight; it must not make network requests.
     *
     * @return bool
     */
    public function is_available(): bool;

    /**
     * Return the categories supplied by this provider.
     *
     * @return string[] e.g. ['public_holiday'] or ['public_holiday', 'school_holiday']
     */
    public function get_supported_categories(): array;

    /**
     * Fetch day information for a given month.
     *
     * Implementations MUST use the Moodle MUC cache (cache::make with the
     * 'caldata' definition) to avoid repeated remote API calls. Cache keys
     * should encode the component, year, month, and any region/config hash.
     *
     * @param int $year  Four-digit year.
     * @param int $month Month number (1-12).
     * @return array<string, array[]> Keyed by 'Y-m-d'. Each value is a list of
     *                                event arrays with keys: name, category, source.
     */
    public function get_day_info(int $year, int $month): array;
}
