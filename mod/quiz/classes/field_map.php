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
 * Field descriptor map for the mod_quiz adapter.
 *
 * Provides the canonical list of bulk-editable fields exposed by the
 * coursectrlmod_quiz adapter. Only true date fields are listed; the
 * duration columns timelimit and graceperiod are intentionally excluded
 * because they describe spans of time, not absolute moments, and must
 * not participate in shift_dates.
 *
 * Per-field flags:
 *   shiftable     - the field participates in shift_dates / set_dates actions.
 *   nullable_zero - a stored value of 0 means "unset" in mod_quiz and must
 *                   NOT be shifted by a delta (would yield epoch + delta).
 *   sql_column    - the column name in the {quiz} table.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace coursectrlmod_quiz;

/**
 * Static field descriptor source for mod_quiz.
 */
final class field_map
{
    /**
     * Return the date field descriptors handled by this adapter.
     */
    public static function get_date_fields(): array
    {
        return [
            'timeopen' => [
                'name' => 'timeopen',
                'label_key' => 'field_timeopen',
                'sql_column' => 'timeopen',
                'shiftable' => true,
                'nullable_zero' => true,
            ],
            'timeclose' => [
                'name' => 'timeclose',
                'label_key' => 'field_timeclose',
                'sql_column' => 'timeclose',
                'shiftable' => true,
                'nullable_zero' => true,
            ],
        ];
    }

    /**
     * Return only the names of fields that participate in shift_dates.
     *
     * @return string[]
     */
    public static function get_shiftable_field_names(): array
    {
        $result = [];
        foreach (self::get_date_fields() as $name => $descriptor) {
            if (!empty($descriptor['shiftable'])) {
                $result[] = $name;
            }
        }

        return $result;
    }
}
