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
 * Moodle calendar provider for the Course Control Hub.
 *
 * Reads events from the Moodle event system ({event} table) and exposes
 * them as calendar day markers in the Course Control Hub visualisations.
 *
 * Settings used (local_coursectrl plugin config):
 *   calmoodlecal_enabled      — bool
 *   calmoodlecal_eventtype    — comma-separated event types to include
 *                               (default: 'site'); possible values:
 *                               site, category, user, course, group
 *   calmoodlecal_namepattern  — optional regex applied to event name
 *                               (empty = no filter)
 *   calmoodlecal_category     — category assigned to matched events
 *                               (default: 'custom')
 *
 * Scope note: 'course' eventtype includes assignment/quiz deadlines and
 * similar activity events, which would create circular references in
 * the timeline. Default is 'site' to avoid this.
 *
 * @package    coursectrlcal_moodlecal
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace coursectrlcal_moodlecal;

use local_coursectrl\local\contract\abstract_calendar_provider;

/**
 * Calendar provider that reads from the Moodle event system.
 */
class provider extends abstract_calendar_provider {
    /** @var string Settings key for enabled flag. */
    protected string $enabled_key = 'calmoodlecal_enabled';

    /**
     * Returns the frankenstyle component name.
     *
     * @return string
     */
    public static function component(): string {
        return 'coursectrlcal_moodlecal';
    }

    /**
     * Moodle calendar provider is always available.
     *
     * @return bool
     */
    public function is_available(): bool {
        return true;
    }

    /**
     * Returns categories this provider can supply.
     *
     * @return string[]
     */
    public function get_supported_categories(): array {
        return ['custom'];
    }

    /**
     * Return Moodle calendar events for a given month.
     *
     * @param int $year  Four-digit year.
     * @param int $month Month (1-12).
     * @return array<string, array[]>
     */
    public function get_day_info(int $year, int $month): array {
        global $DB;

        $eventtypes = $this->get_event_types();
        $namepattern = trim((string) get_config('local_coursectrl', 'calmoodlecal_namepattern'));
        $category = trim((string) get_config('local_coursectrl', 'calmoodlecal_category'));
        if ($category === '') {
            $category = 'custom';
        }

        $from = mktime(0, 0, 0, $month, 1, $year);
        $to = mktime(23, 59, 59, $month, (int) date('t', $from), $year);

        $cachekey = "moodlecal_{$year}_{$month}_" . implode('_', $eventtypes) . '_' . md5($namepattern . $category);
        $cached = $this->cache_get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        if (empty($eventtypes)) {
            $this->cache_set($cachekey, []);
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($eventtypes, SQL_PARAMS_NAMED, 'evtype');
        $params = array_merge($inparams, ['tfrom' => $from, 'tto' => $to]);
        $records = $DB->get_records_sql(
            "SELECT id, name, timestart
               FROM {event}
              WHERE eventtype $insql
                AND timestart >= :tfrom
                AND timestart <= :tto",
            $params
        );

        $daymap = [];
        foreach ($records as $rec) {
            $name = (string) $rec->name;
            if ($namepattern !== '' && !preg_match($namepattern, $name)) {
                continue;
            }
            $datekey = date('Y-m-d', (int) $rec->timestart);
            $daymap[$datekey][] = $this->make_event($name, $category);
        }

        $this->cache_set($cachekey, $daymap);
        return $daymap;
    }

    /**
     * Return configured event types as an array.
     *
     * @return string[]
     */
    private function get_event_types(): array {
        $raw = (string) get_config('local_coursectrl', 'calmoodlecal_eventtype');
        if (trim($raw) === '') {
            return ['site'];
        }
        return array_filter(array_map('trim', explode(',', $raw)));
    }
}
