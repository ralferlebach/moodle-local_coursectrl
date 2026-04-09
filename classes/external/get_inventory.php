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
 * External function: get_inventory.
 *
 * Returns the normalised inventory snapshot for a course as the
 * Course Control Hub selector and dashboard UIs expect it.
 *
 * @package    local_coursectrl
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_coursectrl\local\inventory\inventory_service;

/**
 * AJAX-callable wrapper around inventory_service::build_for_course().
 *
 * The function performs context resolution, login enforcement and a
 * course read capability check, then delegates to inventory_service.
 * The return value is the array form of an inventory_snapshot.
 */
class get_inventory extends external_api {
    /**
     * Declare the parameters accepted by execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id to inventory', VALUE_REQUIRED),
        ]);
    }

    /**
     * Build and return the inventory snapshot for a course.
     *
     * @param int $courseid Moodle course id.
     * @return array Snapshot in the shape declared by execute_returns().
     */
    public static function execute(int $courseid): array {
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['courseid' => $courseid]
        );

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/course:view', $context);

        $service = new inventory_service();
        $snapshot = $service->build_for_course((int) $params['courseid']);
        return $snapshot->to_array();
    }

    /**
     * Declare the return shape of execute().
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'course' => self::course_structure(),
            'sections' => new external_multiple_structure(self::section_structure()),
            'cms' => new external_multiple_structure(self::cm_structure()),
            'texts' => new external_multiple_structure(self::text_structure()),
        ]);
    }

    /**
     * Schema fragment for a single course entity.
     *
     * @return external_single_structure
     */
    private static function course_structure(): external_single_structure {
        return new external_single_structure([
            'type' => new external_value(PARAM_ALPHA, 'Entity type discriminator'),
            'id' => new external_value(PARAM_INT, 'Course id'),
            'fullname' => new external_value(PARAM_TEXT, 'Course full name'),
            'shortname' => new external_value(PARAM_TEXT, 'Course short name'),
            'summary' => new external_value(PARAM_RAW, 'Course summary content'),
            'summaryformat' => new external_value(PARAM_INT, 'Format constant for the summary'),
            'startdate' => new external_value(PARAM_INT, 'Course start timestamp'),
            'enddate' => new external_value(
                PARAM_INT,
                'Course end timestamp',
                VALUE_REQUIRED,
                null,
                NULL_ALLOWED
            ),
            'visible' => new external_value(PARAM_BOOL, 'Whether the course is visible'),
        ]);
    }

    /**
     * Schema fragment for a single section entity.
     *
     * @return external_single_structure
     */
    private static function section_structure(): external_single_structure {
        return new external_single_structure([
            'type' => new external_value(PARAM_ALPHA, 'Entity type discriminator'),
            'id' => new external_value(PARAM_INT, 'Section id'),
            'courseid' => new external_value(PARAM_INT, 'Parent course id'),
            'sectionnum' => new external_value(PARAM_INT, '0-based section number'),
            'name' => new external_value(
                PARAM_TEXT,
                'Explicit section name',
                VALUE_REQUIRED,
                null,
                NULL_ALLOWED
            ),
            'summary' => new external_value(PARAM_RAW, 'Section summary content'),
            'summaryformat' => new external_value(PARAM_INT, 'Format constant for the summary'),
            'visible' => new external_value(PARAM_BOOL, 'Whether the section is visible'),
        ]);
    }

    /**
     * Schema fragment for a single course module entity.
     *
     * @return external_single_structure
     */
    private static function cm_structure(): external_single_structure {
        return new external_single_structure([
            'type' => new external_value(PARAM_ALPHA, 'Entity type discriminator'),
            'id' => new external_value(PARAM_INT, 'Course module id'),
            'courseid' => new external_value(PARAM_INT, 'Parent course id'),
            'sectionid' => new external_value(PARAM_INT, 'Parent section id'),
            'modname' => new external_value(PARAM_PLUGIN, 'Module short name'),
            'instance' => new external_value(PARAM_INT, 'Module-specific row id'),
            'name' => new external_value(PARAM_TEXT, 'Activity display name'),
            'visible' => new external_value(PARAM_BOOL, 'Visibility flag'),
            'availability' => new external_value(
                PARAM_RAW,
                'JSON availability tree',
                VALUE_REQUIRED,
                null,
                NULL_ALLOWED
            ),
            'completion' => new external_value(PARAM_INT, 'Completion tracking constant'),
        ]);
    }

    /**
     * Schema fragment for a single editable text entity.
     *
     * @return external_single_structure
     */
    private static function text_structure(): external_single_structure {
        return new external_single_structure([
            'type' => new external_value(PARAM_ALPHA, 'Entity type discriminator'),
            'entitytype' => new external_value(PARAM_ALPHA, 'Owner entity type'),
            'entityid' => new external_value(PARAM_INT, 'Owner entity id'),
            'fieldname' => new external_value(PARAM_ALPHANUMEXT, 'Field name on the owner entity'),
            'content' => new external_value(PARAM_RAW, 'Raw text content'),
            'format' => new external_value(PARAM_INT, 'Format constant for the content'),
        ]);
    }
}
