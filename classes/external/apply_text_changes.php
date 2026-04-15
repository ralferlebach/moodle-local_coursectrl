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
 * External function: apply_text_changes.
 *
 * Applies a delta shift to a set of confirmed text-datetime hits
 * identified by their row ids.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_coursectrl\manager\textreview_manager;

/**
 * AJAX-callable wrapper for text-datetime change application.
 */
class apply_text_changes extends external_api {
    /**
     * Declare the parameters accepted by execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'hitids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Text hit row id'),
                'IDs of confirmed text_hit rows to apply'
            ),
            'delta' => new external_value(PARAM_INT, 'Seconds to shift (positive = forward)'),
        ]);
    }

    /**
     * Apply text changes for confirmed hits.
     *
     * @param int   $courseid Course id.
     * @param int[] $hitids   Confirmed hit row ids.
     * @param int   $delta    Seconds to shift.
     * @return array
     */
    public static function execute(int $courseid, array $hitids, int $delta): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'hitids' => $hitids,
            'delta' => $delta,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/coursectrl:bulkaction', $context);

        $manager = new textreview_manager();
        $result = $manager->apply_changes(
            $params['courseid'],
            $params['hitids'],
            $params['delta']
        );

        $errors = [];
        foreach ($result['errors'] as $error) {
            $errors[] = [
                'key' => (string) ($error['key'] ?? ''),
                'code' => (string) ($error['code'] ?? 'unknown'),
                'message' => (string) ($error['message'] ?? ''),
            ];
        }

        return [
            'applied' => (int) $result['applied'],
            'skipped' => (int) $result['skipped'],
            'errors' => $errors,
        ];
    }

    /**
     * Declare the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'applied' => new external_value(PARAM_INT, 'Number of successfully applied changes'),
            'skipped' => new external_value(PARAM_INT, 'Number of skipped changes'),
            'errors' => new external_multiple_structure(
                new external_single_structure([
                    'key' => new external_value(PARAM_RAW, 'Entity key that failed'),
                    'code' => new external_value(PARAM_ALPHANUMEXT, 'Error code'),
                    'message' => new external_value(PARAM_RAW, 'Error message'),
                ])
            ),
        ]);
    }
}
