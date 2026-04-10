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
 * External function: execute_bulk_action.
 *
 * Executes a course-wide bulk action through the batch_manager pipeline.
 * Persists batch, batch_items and snapshots, then returns the batch id
 * and a per-status summary for the UI to render a result page.
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
use local_coursectrl\local\persistent\batch;
use local_coursectrl\local\persistent\batch_item;
use local_coursectrl\manager\batch_manager;

/**
 * AJAX-callable wrapper around batch_manager::execute().
 *
 * @covers \local_coursectrl\manager\batch_manager
 */
class execute_bulk_action extends external_api {
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
     * Execute a course-wide bulk action.
     *
     * @param int    $courseid    Moodle course id.
     * @param string $action      Canonical action identifier.
     * @param string $payloadjson JSON-encoded action parameters.
     * @param int[]  $cmids       Target cmids.
     * @return array Execution result in the shape declared by execute_returns().
     */
    public static function execute(int $courseid, string $action, string $payloadjson, array $cmids = []): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid'    => $courseid,
            'action'      => $action,
            'payloadjson' => $payloadjson,
            'cmids'       => $cmids,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/coursectrl:bulkaction', $context);

        $payload = json_decode($params['payloadjson'], true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $manager = new batch_manager();
        $batchid = $manager->execute(
            $params['courseid'],
            $params['action'],
            $payload,
            $params['cmids'],
            (int)$USER->id
        );

        $batchrow = new batch($batchid);
        $items = batch_item::get_records(['batchid' => $batchid]);

        $summary = [
            'total'   => count($items),
            'success' => 0,
            'skipped' => 0,
            'error'   => 0,
        ];
        foreach ($items as $item) {
            $status = $item->get('status');
            if ($status === batch_item::STATUS_SUCCESS) {
                $summary['success']++;
            } else if ($status === batch_item::STATUS_SKIPPED) {
                $summary['skipped']++;
            } else if ($status === batch_item::STATUS_ERROR) {
                $summary['error']++;
            }
        }

        return [
            'batchid' => $batchid,
            'status'  => $batchrow->get('status'),
            'summary' => $summary,
        ];
    }

    /**
     * Declare the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'batchid' => new external_value(PARAM_INT, 'Persisted batch row id'),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Batch status: executed or failed'),
            'summary' => new external_single_structure([
                'total' => new external_value(PARAM_INT, 'Total items processed'),
                'success' => new external_value(PARAM_INT, 'Successful items'),
                'skipped' => new external_value(PARAM_INT, 'Skipped items'),
                'error' => new external_value(PARAM_INT, 'Failed items'),
            ]),
        ]);
    }
}
