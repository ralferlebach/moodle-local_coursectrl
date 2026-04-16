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
 * Calendar manager for the Course Control Hub.
 *
 * Discovers all installed and enabled coursectrlcal_* subplugins, merges
 * their day-info results into one unified holiday map, and provides fast
 * lookup methods for use in calendar_grid_builder, gantt_dataset_builder
 * and the date-shifting preview pipeline.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\manager;

use local_coursectrl\local\contract\calendar_provider;

/**
 * Orchestrates calendar provider discovery and holiday data aggregation.
 */
class calendar_manager {
    /** @var calendar_provider[] Discovered and enabled providers. */
    private array $providers = [];

    /** @var bool Whether provider discovery has been run. */
    private bool $discovered = false;

    /** @var array<string, array[]> Merged holiday map, keyed by Y-m-d. */
    private array $cache = [];

    /**
     * Return all enabled providers.
     *
     * @return calendar_provider[]
     */
    public function get_providers(): array {
        $this->discover();
        return $this->providers;
    }

    /**
     * Return all holiday/free-period events for the given date range.
     *
     * Results are indexed by Y-m-d; each value is a list of event arrays
     * (keys: name, category, source). Days without events are not present.
     *
     * @param int $from Unix timestamp — range start (inclusive).
     * @param int $to   Unix timestamp — range end (inclusive).
     * @return array<string, array[]> Keyed by 'Y-m-d'.
     */
    public function get_holidays_for_range(int $from, int $to): array {
        $this->discover();
        if (empty($this->providers)) {
            return [];
        }

        $result = [];
        $startmonth = mktime(0, 0, 0, (int) date('n', $from), 1, (int) date('Y', $from));
        $endmonth = mktime(0, 0, 0, (int) date('n', $to), 1, (int) date('Y', $to));
        $current = $startmonth;

        while ($current <= $endmonth) {
            $year = (int) date('Y', $current);
            $month = (int) date('n', $current);
            $monthdata = $this->get_month($year, $month);
            foreach ($monthdata as $datekey => $events) {
                $ts = strtotime('!' . $datekey);
                if ($ts >= $from && $ts <= $to) {
                    if (!isset($result[$datekey])) {
                        $result[$datekey] = [];
                    }
                    $result[$datekey] = array_merge($result[$datekey], $events);
                }
            }
            $current = strtotime('+1 month', $current);
        }

        return $result;
    }

    /**
     * Check whether the given unix timestamp falls on a holiday or free period.
     *
     * @param int $timestamp Unix timestamp.
     * @return bool True if any enabled provider marks this day as a special day.
     */
    public function is_holiday(int $timestamp): bool {
        $datekey = date('Y-m-d', $timestamp);
        $year = (int) date('Y', $timestamp);
        $month = (int) date('n', $timestamp);
        $monthdata = $this->get_month($year, $month);
        return !empty($monthdata[$datekey]);
    }

    /**
     * Return holiday events for a specific day, or empty array.
     *
     * @param int $timestamp Unix timestamp of the day.
     * @return array[] List of event arrays, empty if not a special day.
     */
    public function get_events_for_day(int $timestamp): array {
        $datekey = date('Y-m-d', $timestamp);
        $year = (int) date('Y', $timestamp);
        $month = (int) date('n', $timestamp);
        $monthdata = $this->get_month($year, $month);
        return $monthdata[$datekey] ?? [];
    }

    /**
     * Get merged data for a full month from all providers.
     *
     * Uses an in-request cache to avoid duplicate provider calls.
     *
     * @param int $year  Year.
     * @param int $month Month (1-12).
     * @return array<string, array[]>
     */
    private function get_month(int $year, int $month): array {
        $cachekey = "{$year}_{$month}";
        if (isset($this->cache[$cachekey])) {
            return $this->cache[$cachekey];
        }
        $merged = [];
        foreach ($this->providers as $provider) {
            try {
                $data = $provider->get_day_info($year, $month);
                foreach ($data as $datekey => $events) {
                    if (!isset($merged[$datekey])) {
                        $merged[$datekey] = [];
                    }
                    $merged[$datekey] = array_merge($merged[$datekey], $events);
                }
            } catch (\Throwable $ignored) {
                // Skip failing providers gracefully.
                continue;
            }
        }
        $this->cache[$cachekey] = $merged;
        return $merged;
    }

    /**
     * Discover all installed coursectrlcal_* subplugins and instantiate enabled ones.
     */
    private function discover(): void {
        if ($this->discovered) {
            return;
        }
        $this->discovered = true;
        $pluginlist = \core_component::get_plugin_list('coursectrlcal');
        if (!is_array($pluginlist)) {
            return;
        }
        foreach ($pluginlist as $name => $path) {
            $classname = "coursectrlcal_{$name}\\provider";
            if (!class_exists($classname)) {
                continue;
            }
            try {
                $provider = new $classname();
                if (
                    $provider instanceof calendar_provider
                    && $provider->is_enabled()
                    && $provider->is_available()
                ) {
                    $this->providers[] = $provider;
                }
            } catch (\Throwable $ignored) {
                continue;
            }
        }
    }
}
