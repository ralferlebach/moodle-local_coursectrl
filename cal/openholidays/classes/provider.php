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
 * OpenHolidays API provider for the Course Control Hub.
 *
 * Fetches both public holidays and school holidays from the free
 * OpenHolidays API (https://openholidaysapi.org).
 *
 * Settings used (local_coursectrl plugin config):
 *   calopenholidays_enabled         — bool
 *   calopenholidays_countryisocode  — e.g. 'DE'
 *   calopenholidays_languageisocode — e.g. 'DE'
 *   calopenholidays_regioncode      — subdivision code, e.g. 'DE-BY' (optional)
 *   calopenholidays_categories      — comma list: public_holiday,school_holiday
 *
 * @package    coursectrlcal_openholidays
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace coursectrlcal_openholidays;

use local_coursectrl\local\contract\abstract_calendar_provider;

/**
 * Calendar provider backed by the OpenHolidays REST API.
 */
class provider extends abstract_calendar_provider {
    /** @var string Settings key for enabled flag. */
    protected string $enabledkey = 'calopenholidays_enabled';

    /** @var string Base URL of the OpenHolidays API. */
    private const API_BASE = 'https://openholidaysapi.org';

    /**
     * Returns the frankenstyle component name.
     *
     * @return string
     */
    public static function component(): string {
        return 'coursectrlcal_openholidays';
    }

    /**
     * Whether this provider is operational.
     *
     * @return bool
     */
    public function is_available(): bool {
        $code = get_config('local_coursectrl', 'calopenholidays_countryisocode');
        return $code !== false && trim((string) $code) !== '';
    }

    /**
     * Returns the categories this provider can supply.
     *
     * @return string[]
     */
    public function get_supported_categories(): array {
        return ['public_holiday', 'school_holiday'];
    }

    /**
     * Fetch holiday data for a given month.
     *
     * @param int $year  Four-digit year.
     * @param int $month Month (1-12).
     * @return array<string, array[]>
     */
    public function get_day_info(int $year, int $month): array {
        $country = strtoupper(trim(
            (string) get_config('local_coursectrl', 'calopenholidays_countryisocode')
        ));
        $language = strtoupper(trim(
            (string) (get_config('local_coursectrl', 'calopenholidays_languageisocode') ?: 'EN')
        ));
        $region = strtoupper(trim(
            (string) get_config('local_coursectrl', 'calopenholidays_regioncode')
        ));

        // Normalise region code: accept short codes like 'NW', 'BY', 'NRW'.
        // OpenHolidays API expects the ISO 3166-2 format 'CC-XX' (e.g. 'DE-NW').
        if ($region !== '' && $country !== '' && !str_contains($region, '-')) {
            // Map common German state abbreviations that differ from ISO codes.
            $aliasmap = ['NRW' => 'NW', 'BAWUE' => 'BW', 'RHEINLANDPFALZ' => 'RP'];
            $normalised = $aliasmap[$region] ?? $region;
            $region = $country . '-' . $normalised;
        }
        $categories = $this->get_active_categories();

        if ($country === '') {
            return [];
        }

        $cachekey = "openholidays_{$country}_{$language}_{$region}_{$year}_{$month}";
        $cached = $this->cache_get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        $dayfrom = sprintf('%04d-%02d-01', $year, $month);
        $lastday = date('t', mktime(0, 0, 0, $month, 1, $year));
        $dayto = sprintf('%04d-%02d-%02d', $year, $month, $lastday);

        $daymap = [];

        if (in_array('public_holiday', $categories, true)) {
            $this->fetch_public($daymap, $country, $language, $dayfrom, $dayto, $year, $month);
        }
        if (in_array('school_holiday', $categories, true) && $region !== '') {
            $this->fetch_school($daymap, $country, $language, $region, $dayfrom, $dayto, $year, $month);
        }

        $this->cache_set($cachekey, $daymap);
        return $daymap;
    }

    /**
     * Return the list of enabled categories from settings.
     *
     * @return string[]
     */
    private function get_active_categories(): array {
        $raw = (string) get_config('local_coursectrl', 'calopenholidays_categories');
        if ($raw === '') {
            return ['public_holiday', 'school_holiday'];
        }
        return array_map('trim', explode(',', $raw));
    }

    /**
     * Fetch and merge public holidays from the OpenHolidays API.
     *
     * @param array  $daymap   Reference to the day map being built.
     * @param string $country  Country ISO code.
     * @param string $language Language ISO code.
     * @param string $dayfrom  ISO date range start.
     * @param string $dayto    ISO date range end.
     * @param int    $year     Year for expand_range filter.
     * @param int    $month    Month for expand_range filter.
     */
    private function fetch_public(
        array &$daymap,
        string $country,
        string $language,
        string $dayfrom,
        string $dayto,
        int $year,
        int $month
    ): void {
        $params = http_build_query([
            'countryIsoCode' => $country,
            'languageIsoCode' => $language,
            'validFrom' => $dayfrom,
            'validTo' => $dayto,
        ]);
        $raw = $this->http_get(self::API_BASE . '/PublicHolidays?' . $params);
        if ($raw === null) {
            return;
        }
        foreach ($raw as $item) {
            $name = $this->extract_name($item, $language);
            $start = (string) ($item['startDate'] ?? '');
            $end = (string) ($item['endDate'] ?? $start);
            if ($name === '' || $start === '') {
                continue;
            }
            $this->expand_range($daymap, $start, $end, $this->make_event($name, 'public_holiday'), $year, $month);
        }
    }

    /**
     * Fetch and merge school holidays from the OpenHolidays API.
     *
     * @param array  $daymap    Reference to the day map being built.
     * @param string $country   Country ISO code.
     * @param string $language  Language ISO code.
     * @param string $region    Subdivision code.
     * @param string $dayfrom   ISO date range start.
     * @param string $dayto     ISO date range end.
     * @param int    $year      Year for expand_range filter.
     * @param int    $month     Month for expand_range filter.
     */
    private function fetch_school(
        array &$daymap,
        string $country,
        string $language,
        string $region,
        string $dayfrom,
        string $dayto,
        int $year,
        int $month
    ): void {
        $params = http_build_query([
            'countryIsoCode' => $country,
            'languageIsoCode' => $language,
            'subdivisionCode' => $region,
            'validFrom' => $dayfrom,
            'validTo' => $dayto,
        ]);
        $raw = $this->http_get(self::API_BASE . '/SchoolHolidays?' . $params);
        if ($raw === null) {
            return;
        }
        foreach ($raw as $item) {
            $name = $this->extract_name($item, $language);
            $start = (string) ($item['startDate'] ?? '');
            $end = (string) ($item['endDate'] ?? $start);
            if ($name === '' || $start === '') {
                continue;
            }
            $this->expand_range($daymap, $start, $end, $this->make_event($name, 'school_holiday'), $year, $month);
        }
    }

    /**
     * Extract the localised name from an OpenHolidays API item.
     *
     * @param array  $item     Decoded holiday object.
     * @param string $language Preferred language ISO code.
     * @return string
     */
    private function extract_name(array $item, string $language): string {
        $names = $item['name'] ?? [];
        if (!is_array($names)) {
            return '';
        }
        // Prefer the requested language, fall back to first available.
        foreach ($names as $entry) {
            if (strtoupper((string) ($entry['language'] ?? '')) === $language) {
                return (string) ($entry['text'] ?? '');
            }
        }
        return isset($names[0]) ? (string) ($names[0]['text'] ?? '') : '';
    }
}
