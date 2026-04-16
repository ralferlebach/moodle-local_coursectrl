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
 * Abstract base class for coursectrlcal_* calendar provider subplugins.
 *
 * Provides shared helpers:
 *   - is_enabled()  reads config from local_coursectrl settings.
 *   - cache_get() / cache_set()  thin wrappers around Moodle MUC.
 *   - http_get()  wraps Moodle's \curl for outbound HTTP with timeout.
 *   - make_event() builds a normalised event entry array.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\contract;

/**
 * Base class for calendar providers.
 */
abstract class abstract_calendar_provider implements calendar_provider {
    /** @var string Settings key suffix for enabled flag, e.g. 'calnager_enabled'. */
    protected string $enabledkey = '';

    /**
     * Whether this provider is enabled in admin settings.
     *
     * @return bool
     */
    public function is_enabled(): bool {
        if ($this->enabledkey === '') {
            return false;
        }
        return (bool) get_config('local_coursectrl', $this->enabledkey);
    }

    /**
     * Build a normalised event entry array.
     *
     * @param string $name     Human-readable event name.
     * @param string $category One of: public_holiday, school_holiday, custom.
     * @return array
     */
    protected function make_event(string $name, string $category): array {
        return [
            'name' => $name,
            'category' => $category,
            'source' => static::component(),
        ];
    }

    /**
     * Retrieve a cached value from the MUC caldata cache.
     *
     * @param string $key Cache key.
     * @return mixed|false Cached value or false on miss.
     */
    protected function cache_get(string $key) {
        $cache = \cache::make('local_coursectrl', 'caldata');
        return $cache->get($key);
    }

    /**
     * Store a value in the MUC caldata cache.
     *
     * @param string $key   Cache key.
     * @param mixed  $value Value to cache.
     * @return bool
     */
    protected function cache_set(string $key, $value): bool {
        $cache = \cache::make('local_coursectrl', 'caldata');
        return $cache->set($key, $value);
    }

    /**
     * Perform a GET request using Moodle's \curl wrapper.
     *
     * Returns the decoded JSON as an array, or null on error.
     *
     * @param string $url     Full URL to request.
     * @param int    $timeout Timeout in seconds (default 10).
     * @return array|null Decoded JSON array or null on failure.
     */
    protected function http_get(string $url, int $timeout = 10): ?array {
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => $timeout,
            'CURLOPT_CONNECTTIMEOUT' => 5,
        ]);
        $response = $curl->get($url);
        if ($curl->get_errno() !== 0) {
            return null;
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Expand a date range into individual Y-m-d keys and add events to a map.
     *
     * @param array  $daymap    Reference to the Y-m-d → events map being built.
     * @param string $startdate 'Y-m-d' inclusive start date.
     * @param string $enddate   'Y-m-d' inclusive end date.
     * @param array  $event     Event array from make_event().
     * @param int    $year      Only include days in this year.
     * @param int    $month     Only include days in this month (0 = all months).
     */
    protected function expand_range(
        array &$daymap,
        string $startdate,
        string $enddate,
        array $event,
        int $year,
        int $month
    ): void {
        $start = strtotime('!' . $startdate);
        $end = strtotime('!' . $enddate);
        if ($start === false || $end === false || $start > $end) {
            return;
        }
        $current = $start;
        while ($current <= $end) {
            $y = (int) date('Y', $current);
            $m = (int) date('n', $current);
            if ($y === $year && ($month === 0 || $m === $month)) {
                $key = date('Y-m-d', $current);
                $daymap[$key][] = $event;
            }
            $current = strtotime('+1 day', $current);
        }
    }
}
