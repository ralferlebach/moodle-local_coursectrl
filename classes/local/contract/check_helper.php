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
 * Shared helper trait for adapter run_checks() implementations.
 *
 * Provides utility methods for:
 *   - reading R7 severity settings from Moodle admin config
 *   - building normalised result items
 *   - fetching CM records safely
 *
 * All R7 settings are stored as `<pluginname>_r7_<code>` and accept the values
 * 'off', 'notice', or 'warning'. Adapters declare their own default map via
 * get_r7_defaults() and pass it to r7_severity().
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\contract;

/**
 * Helper methods shared across all adapter run_checks() implementations.
 */
trait check_helper {
    /**
     * Return the effective severity for an R7 check code.
     *
     * Reads the admin setting `<pluginname>_r7_<code>`. Falls back to the
     * adapter-supplied default if no setting has been saved yet. Returns null
     * when the effective severity is 'off' (caller must skip the check).
     *
     * @param string $pluginname Frankenstyle subplugin name, e.g. 'coursectrlmod_quiz'.
     * @param string $code       R7 check code, e.g. 'timeopen_without_timeclose'.
     * @param array  $defaults   Map of code → default severity ('off'|'notice'|'warning').
     * @return string|null Effective severity, or null if check is disabled.
     */
    protected function r7_severity(string $pluginname, string $code, array $defaults): ?string {
        $setting = get_config($pluginname, 'r7_' . $code);
        // Treat empty string the same as false: use the adapter default.
        $value = ($setting !== false && $setting !== null && $setting !== '') ? (string)$setting : ($defaults[$code] ?? 'off');
        return ($value === 'off') ? null : $value;
    }

    /**
     * Build a normalised check result item.
     *
     * @param int    $cmid     Course module id.
     * @param string $name     Activity name.
     * @param string $severity 'error' | 'warning' | 'notice'.
     * @param string $code     Machine-readable check code.
     * @param string $message  Localised message string.
     * @return array
     */
    protected function check_result(
        int $cmid,
        string $name,
        string $severity,
        string $code,
        string $message
    ): array {
        return [
            'cmid'     => $cmid,
            'name'     => $name,
            'severity' => $severity,
            'code'     => $code,
            'message'  => $message,
        ];
    }

    /**
     * Bulk-load activity records for a set of cmids in exactly two SELECTs.
     *
     * Issues one join against course_modules + modules to resolve cmid ->
     * instanceid, then one get_records_list against the target module table.
     * Returns a cmid-keyed map of records that includes a 'cmid' property on
     * each record so consumers can use it directly without back-mapping.
     *
     * @param int[]  $cmids        Course module ids.
     * @param string $modname      Module name, e.g. 'quiz'.
     * @param string $selectfields Comma-separated SELECT clause for the module table.
     * @return array<int, \stdClass> Records keyed by cmid.
     */
    protected function load_check_records(array $cmids, string $modname, string $selectfields): array {
        global $DB;
        $cmids = array_values(array_unique(array_map('intval', $cmids)));
        if (empty($cmids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
        $params['modname'] = $modname;
        $sql = "SELECT cm.id AS cmid, cm.instance
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.id {$insql}
                   AND m.name = :modname";
        $cmrows = $DB->get_records_sql($sql, $params);
        if (empty($cmrows)) {
            return [];
        }

        $instancemap = [];
        foreach ($cmrows as $cmrow) {
            $instancemap[(int) $cmrow->instance][] = (int) $cmrow->cmid;
        }

        $records = $DB->get_records_list(
            $modname,
            'id',
            array_keys($instancemap),
            '',
            $selectfields
        );

        $result = [];
        foreach ($records as $record) {
            $instanceid = (int) $record->id;
            if (!isset($instancemap[$instanceid])) {
                continue;
            }
            foreach ($instancemap[$instanceid] as $cmid) {
                $clone = clone $record;
                $clone->cmid = $cmid;
                $result[$cmid] = $clone;
            }
        }
        return $result;
    }
}
