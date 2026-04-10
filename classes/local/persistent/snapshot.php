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
 * Persistent for the local_coursectrl_snapshot table.
 *
 * Pre-action state snapshot used as the rollback basis. Each row stores the
 * full serialised state of one entity (course module, section, label or
 * text field) as captured by the corresponding adapter's export_state()
 * call before the bulk action mutated it.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\persistent;

use core\persistent;

/**
 * Persistent wrapping a single snapshot row.
 */
class snapshot extends persistent {
    /** @var string Database table name. */
    const TABLE = 'local_coursectrl_snapshot';

    /**
     * Property definitions matching the install.xml schema.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'batchid' => [
                'type' => PARAM_INT,
            ],
            'entitytype' => [
                'type' => PARAM_ALPHANUMEXT,
            ],
            'entityid' => [
                'type' => PARAM_INT,
            ],
            'component' => [
                'type'    => PARAM_COMPONENT,
                'null'    => NULL_ALLOWED,
                'default' => null,
            ],
            'statejson' => [
                'type' => PARAM_RAW,
            ],
        ];
    }
}
