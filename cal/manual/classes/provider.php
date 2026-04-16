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
 * Manual calendar provider for the Course Control Hub.
 *
 * Allows administrators to define custom free days (e.g. company holidays,
 * sports events, institutional breaks) via a textarea in plugin settings.
 *
 * Format — one entry per line:
 *   YYYY-MM-DD,Name,category
 *   YYYY-MM-DD/YYYY-MM-DD,Name,category   (date range)
 *
 * Valid categories: public_holiday, school_holiday, custom
 * Lines beginning with # are treated as comments and ignored.
 *
 * Settings used (local_coursectrl plugin config):
 *   calmanual_enabled — bool
 *   calmanual_entries — multi-line text
 *
 * @package    coursectrlcal_manual
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace coursectrlcal_manual;

use local_coursectrl\local\contract\abstract_calendar_provider;

/**
 * Calendar provider for manually entered free days.
 */
class provider extends abstract_calendar_provider {
    /** @var string Settings key for enabled flag. */
    protected string $enabled_key = 'calmanual_enabled';

    /** @var string[] Allowed category values. */
    private const ALLOWED_CATS = ['public_holiday', 'school_holiday', 'custom'];

    /**
     * Returns the frankenstyle component name.
     *
     * @return string
     */
    public static function component(): string {
        return 'coursectrlcal_manual';
    }

    /**
     * Manual provider is always available if enabled.
     *
     * @return bool
     */
    public function is_available(): bool {
        return true;
    }

    /**
     * Returns all supported categories.
     *
     * @return string[]
     */
    public function get_supported_categories(): array {
        return self::ALLOWED_CATS;
    }

    /**
     * Return manually defined events for a given month.
     *
     * @param int $year  Four-digit year.
     * @param int $month Month (1-12).
     * @return array<string, array[]>
     */
    public function get_day_info(int $year, int $month): array {
        $raw = (string) get_config('local_coursectrl', 'calmanual_entries');
        if (trim($raw) === '') {
            return [];
        }

        $cachekey = "manual_{$year}_{$month}_" . md5($raw);
        $cached = $this->cache_get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        $daymap = [];
        $lines = explode("\n", str_replace("\r\n", "\n", $raw));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = array_map('trim', explode(',', $line, 3));
            if (count($parts) < 2) {
                continue;
            }
            [$datepart, $name] = $parts;
            $category = isset($parts[2]) && in_array($parts[2], self::ALLOWED_CATS, true)
                ? $parts[2]
                : 'custom';

            if (str_contains($datepart, '/')) {
                [$start, $end] = explode('/', $datepart, 2);
                $this->expand_range($daymap, trim($start), trim($end), $this->make_event($name, $category), $year, $month);
            } else {
                $m = (int) date('n', strtotime('!' . $datepart));
                $y = (int) date('Y', strtotime('!' . $datepart));
                if ($y === $year && $m === $month) {
                    $daymap[$datepart][] = $this->make_event($name, $category);
                }
            }
        }

        $this->cache_set($cachekey, $daymap);
        return $daymap;
    }
}
