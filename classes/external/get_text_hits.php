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
 * External function: get_text_hits.
 *
 * Triggers a fresh text-datetime scan for a course and returns all
 * detected hits grouped by confidence level.
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
 * AJAX-callable wrapper for text-datetime scanning.
 */
class get_text_hits extends external_api {
    /**
     * Declare the parameters accepted by execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'rescan' => new external_value(
                PARAM_BOOL,
                'Whether to run a fresh scan before returning hits',
                VALUE_DEFAULT,
                true
            ),
        ]);
    }

    /**
     * Scan and return text-datetime hits for a course.
     *
     * @param int  $courseid Course id.
     * @param bool $rescan   Whether to trigger a fresh scan.
     * @return array
     */
    public static function execute(int $courseid, bool $rescan = true): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'rescan' => $rescan,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/coursectrl:view', $context);

        $manager = new textreview_manager();

        $summary = ['total' => 0, 'safe' => 0, 'ambiguous' => 0, 'informational' => 0];
        if ($params['rescan']) {
            $summary = $manager->scan_course($params['courseid']);
        }

        $rawhits = $manager->get_hits($params['courseid']);
        $hits = [];
        foreach ($rawhits as $hit) {
            $hits[] = [
                'id' => (int) $hit->get('id'),
                'entitytype' => $hit->get('entitytype'),
                'entityid' => (int) $hit->get('entityid'),
                'fieldname' => $hit->get('fieldname'),
                'matchedtext' => $hit->get('matchedtext'),
                'normalizedvalue' => $hit->get('normalizedvalue') ?? '',
                'confidence' => $hit->get('confidence'),
                'contextjson' => $hit->get('contextjson') ?? '',
            ];
        }

        return [
            'hits' => $hits,
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
            'hits' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Text hit row id'),
                    'entitytype' => new external_value(PARAM_ALPHANUMEXT, 'Owner entity type'),
                    'entityid' => new external_value(PARAM_INT, 'Owner entity id'),
                    'fieldname' => new external_value(PARAM_ALPHANUMEXT, 'Field name'),
                    'matchedtext' => new external_value(PARAM_RAW, 'Matched date substring'),
                    'normalizedvalue' => new external_value(PARAM_RAW, 'ISO 8601 normalised value'),
                    'confidence' => new external_value(PARAM_ALPHANUMEXT, 'Confidence: safe, ambiguous, informational'),
                    'contextjson' => new external_value(PARAM_RAW, 'JSON context with offset and excerpts'),
                ])
            ),
            'summary' => new external_single_structure([
                'total' => new external_value(PARAM_INT, 'Total hits found'),
                'safe' => new external_value(PARAM_INT, 'Safe hits'),
                'ambiguous' => new external_value(PARAM_INT, 'Ambiguous hits'),
                'informational' => new external_value(PARAM_INT, 'Informational hits'),
            ]),
        ]);
    }
}
