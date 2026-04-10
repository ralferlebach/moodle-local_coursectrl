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
 * Field descriptor map for the mod_assign adapter.
 *
 * Provides the canonical list of bulk-editable fields exposed by the
 * coursectrlmod_assign adapter to the Course Control Hub bulk pipeline.
 * The map is intentionally a flat associative array, not an entity object,
 * because the bulk validator and preview UI consume it as JSON.
 *
 * Per-field flags:
 *   shiftable     - the field participates in shift_dates / set_dates actions.
 *   nullable_zero - a stored value of 0 means "unset" in mod_assign and must
 *                   NOT be shifted by a delta (would yield epoch + delta).
 *   sql_column    - the column name in the {assign} table.
 *
 * @package    coursectrlmod_assign
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace coursectrlmod_assign;

defined('MOODLE_INTERNAL') || die();

/**
 * Static field descriptor source for mod_assign.
 */
final class field_map {
    /**
     * Return the date field descriptors handled by this adapter.
     *
     * Keys are the canonical field names; values are descriptor arrays.
     *
     * @return array
     */
    public static function get_date_fields(): array {
        return [
            'duedate' => [
                'name'          => 'duedate',
                'label_key'     => 'field_duedate',
                'sql_column'    => 'duedate',
                'shiftable'     => true,
                'nullable_zero' => true,
            ],
            'allowsubmissionsfromdate' => [
                'name'          => 'allowsubmissionsfromdate',
                'label_key'     => 'field_allowsubmissionsfromdate',
                'sql_column'    => 'allowsubmissionsfromdate',
                'shiftable'     => true,
                'nullable_zero' => true,
            ],
            'cutoffdate' => [
                'name'          => 'cutoffdate',
                'label_key'     => 'field_cutoffdate',
                'sql_column'    => 'cutoffdate',
                'shiftable'     => true,
                'nullable_zero' => true,
            ],
            'gradingduedate' => [
                'name'          => 'gradingduedate',
                'label_key'     => 'field_gradingduedate',
                'sql_column'    => 'gradingduedate',
                'shiftable'     => true,
                'nullable_zero' => true,
            ],
        ];
    }

    /**
     * Return only the names of fields that participate in shift_dates.
     *
     * @return string[]
     */
    public static function get_shiftable_field_names(): array {
        $result = [];
        foreach (self::get_date_fields() as $name => $descriptor) {
            if (!empty($descriptor['shiftable'])) {
                $result[] = $name;
            }
        }
        return $result;
    }
}
