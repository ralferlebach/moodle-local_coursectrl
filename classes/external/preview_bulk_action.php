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
 * External function: preview_bulk_action.
 *
 * Returns a course-wide preview of a bulk action without persisting or
 * mutating anything. The result is suitable for rendering a confirmation
 * table in the Course Control Hub bulk UI.
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
use local_coursectrl\manager\preview_manager;

/**
 * AJAX-callable wrapper around preview_manager::build().
 *
 */
class preview_bulk_action extends external_api {
    /**
     * Declare the parameters accepted by execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'action' => new external_value(PARAM_ALPHANUMEXT, 'Action identifier, e.g. shift_dates'),
            'payloadjson' => new external_value(PARAM_RAW, 'JSON-encoded action parameters'),
            'cmids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course module id'),
                'Target cmids; empty means all supported CMs in the course',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Build and return a course-wide preview.
     *
     * @param int    $courseid    Moodle course id.
     * @param string $action      Canonical action identifier.
     * @param string $payloadjson JSON-encoded action parameters.
     * @param int[]  $cmids       Target cmids.
     * @return array Preview result in the shape declared by execute_returns().
     */
    public static function execute(int $courseid, string $action, string $payloadjson, array $cmids = []): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'    => $courseid,
            'action'      => $action,
            'payloadjson' => $payloadjson,
            'cmids'       => $cmids,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/coursectrl:view', $context);

        $payload = json_decode($params['payloadjson'], true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $manager = new preview_manager();
        $result = $manager->build(
            $params['courseid'],
            $params['action'],
            $payload,
            $params['cmids']
        );

        $changes = [];
        foreach ($result['changes'] as $change) {
            $changes[] = [
                'cmid'       => $change->get_cmid(),
                'component'  => $change->get_component(),
                'name'       => $change->get_name(),
                'haschanges' => $change->has_changes(),
                'fieldsjson' => json_encode($change->get_fields()),
            ];
        }

        $skipped = [];
        foreach ($result['skipped'] as $skip) {
            $skipped[] = [
                'cmid'   => (int)($skip['cmid'] ?? 0),
                'reason' => (string)($skip['reason'] ?? 'unknown'),
            ];
        }

        $errors = [];
        foreach ($result['errors'] as $error) {
            $errors[] = [
                'cmid'    => (int)($error['cmid'] ?? 0),
                'code'    => (string)($error['code'] ?? 'unknown'),
                'message' => (string)($error['message'] ?? ''),
            ];
        }

        return [
            'action'      => $result['action'],
            'payloadjson' => $params['payloadjson'],
            'changes'     => $changes,
            'skipped'     => $skipped,
            'errors'      => $errors,
            'summary'     => [
                'total'   => (int)$result['summary']['total'],
                'changes' => (int)$result['summary']['changes'],
                'skipped' => (int)$result['summary']['skipped'],
                'errors'  => (int)$result['summary']['errors'],
            ],
        ];
    }

    /**
     * Declare the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'action' => new external_value(PARAM_ALPHANUMEXT, 'Action identifier'),
            'payloadjson' => new external_value(PARAM_RAW, 'JSON-encoded action parameters'),
            'changes' => new external_multiple_structure(
                new external_single_structure([
                    'cmid' => new external_value(PARAM_INT, 'Course module id'),
                    'component' => new external_value(PARAM_COMPONENT, 'Frankenstyle component'),
                    'name' => new external_value(PARAM_TEXT, 'Instance display name'),
                    'haschanges' => new external_value(PARAM_BOOL, 'Whether any field would actually change'),
                    'fieldsjson' => new external_value(PARAM_RAW, 'JSON-encoded per-field preview descriptors'),
                ])
            ),
            'skipped' => new external_multiple_structure(
                new external_single_structure([
                    'cmid' => new external_value(PARAM_INT, 'Course module id'),
                    'reason' => new external_value(PARAM_ALPHANUMEXT, 'Skip reason code'),
                ])
            ),
            'errors' => new external_multiple_structure(
                new external_single_structure([
                    'cmid' => new external_value(PARAM_INT, 'Course module id'),
                    'code' => new external_value(PARAM_ALPHANUMEXT, 'Error code'),
                    'message' => new external_value(PARAM_RAW, 'Error message'),
                ])
            ),
            'summary' => new external_single_structure([
                'total' => new external_value(PARAM_INT, 'Total cmids processed'),
                'changes' => new external_value(PARAM_INT, 'Count of cmids with preview changes'),
                'skipped' => new external_value(PARAM_INT, 'Count of skipped cmids'),
                'errors' => new external_value(PARAM_INT, 'Count of errored cmids'),
            ]),
        ]);
    }
}
