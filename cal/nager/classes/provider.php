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
 * Nager.Date public holiday provider for the Course Control Hub.
 *
 * Fetches public holidays from the free Nager.Date REST API
 * (https://date.nager.at/api/v3/PublicHolidays/{year}/{countryCode}).
 * Results are cached per country+year for 24 hours via Moodle MUC.
 *
 * Settings used (local_coursectrl plugin config):
 *   calnager_enabled     — bool: activate this provider
 *   calnager_countrycode — ISO 3166-1 alpha-2 country code (default: DE)
 *
 * @package    coursectrlcal_nager
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace coursectrlcal_nager;

use local_coursectrl\local\contract\abstract_calendar_provider;

/**
 * Calendar provider backed by the Nager.Date public holidays API.
 */
class provider extends abstract_calendar_provider {
    /** @var string Settings key for enabled flag. */
    protected string $enabledkey = 'calnager_enabled';

    /** @var string Base URL of the Nager.Date API. */
    private const API_BASE = 'https://date.nager.at/api/v3/PublicHolidays';

    /**
     * Returns the frankenstyle component name.
     *
     * @return string
     */
    public static function component(): string {
        return 'coursectrlcal_nager';
    }

    /**
     * Whether this provider is operational (settings are non-empty).
     *
     * @return bool
     */
    public function is_available(): bool {
        $code = get_config('local_coursectrl', 'calnager_countrycode');
        return $code !== false && trim((string) $code) !== '';
    }

    /**
     * Returns the categories this provider can supply.
     *
     * @return string[]
     */
    public function get_supported_categories(): array {
        return ['public_holiday'];
    }

    /**
     * Fetch public holidays for a given month from Nager.Date.
     *
     * @param int $year  Four-digit year.
     * @param int $month Month (1-12, used for filtering only).
     * @return array<string, array[]>
     */
    public function get_day_info(int $year, int $month): array {
        $countrycode = strtoupper(trim(
            (string) get_config('local_coursectrl', 'calnager_countrycode')
        ));
        if ($countrycode === '') {
            return [];
        }

        $cachekey = "nager_{$countrycode}_{$year}";
        $yeardata = $this->cache_get($cachekey);
        if ($yeardata === false) {
            $url = self::API_BASE . "/{$year}/{$countrycode}";
            $raw = $this->http_get($url);
            $yeardata = $raw !== null ? $this->parse_response($raw) : [];
            // Only cache non-empty results to avoid poisoning the cache on failure.
            if (!empty($yeardata)) {
                $this->cache_set($cachekey, $yeardata);
            }
        }

        // Filter to the requested month.
        $result = [];
        foreach ($yeardata as $datekey => $events) {
            $m = (int) date('n', strtotime($datekey));
            if ($m === $month) {
                $result[$datekey] = $events;
            }
        }
        return $result;
    }

    /**
     * Parse the Nager.Date JSON response into the normalised day map.
     *
     * @param array $raw Decoded JSON array from the API.
     * @return array<string, array[]>
     */
    private function parse_response(array $raw): array {
        $daymap = [];
        foreach ($raw as $item) {
            $datekey = (string) ($item['date'] ?? '');
            $name = (string) ($item['localName'] ?? $item['name'] ?? '');
            if ($datekey === '' || $name === '') {
                continue;
            }
            $daymap[$datekey][] = $this->make_event($name, 'public_holiday');
        }
        return $daymap;
    }
}
