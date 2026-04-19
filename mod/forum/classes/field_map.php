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
 * Field descriptor map for the forum adapter.
 *
 * @package    coursectrlmod_forum
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace coursectrlmod_forum;

/**
 * Static field descriptor source for mod_forum.
 */
final class field_map {
    /**
     * Return the date field descriptors handled by this adapter.
     *
     * @return array
     */
    public static function get_date_fields(): array {
        return [
            'cutoffdate' => [
                'name'          => 'cutoffdate',
                'label_key'     => 'field_cutoffdate',
                'sql_column'    => 'cutoffdate',
                'shiftable'     => true,
                'nullable_zero' => true,
            ],
            'duedate' => [
                'name'          => 'duedate',
                'label_key'     => 'field_duedate',
                'sql_column'    => 'duedate',
                'shiftable'     => true,
                'nullable_zero' => true,
            ],
            'assesstimestart' => [
                'name'          => 'assesstimestart',
                'label_key'     => 'field_assesstimestart',
                'sql_column'    => 'assesstimestart',
                'shiftable'     => true,
                'nullable_zero' => true,
            ],
            'assesstimefinish' => [
                'name'          => 'assesstimefinish',
                'label_key'     => 'field_assesstimefinish',
                'sql_column'    => 'assesstimefinish',
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
