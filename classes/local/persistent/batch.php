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
 * Persistent for the local_coursectrl_batch table.
 *
 * Head record of every bulk action execution. Mirrors the schema defined in
 * db/install.xml. The table holds one row per bulk action invocation; the
 * per-entity results live in local_coursectrl_batch_item, the rollback-
 * relevant pre-action state lives in local_coursectrl_snapshot.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\persistent;

use core\persistent;

/**
 * Persistent wrapping a single bulk action batch row.
 */
class batch extends persistent {
    /** @var string Database table name. */
    const TABLE = 'local_coursectrl_batch';

    /** @var string Initial status: created but not yet previewed. */
    const STATUS_PENDING = 'pending';

    /** @var string Status after a successful preview build. */
    const STATUS_PREVIEWED = 'previewed';

    /** @var string Status after successful execute. */
    const STATUS_EXECUTED = 'executed';

    /** @var string Status after a successful rollback. */
    const STATUS_ROLLED_BACK = 'rolled_back';

    /** @var string Status after a failed execute. */
    const STATUS_FAILED = 'failed';

    /**
     * Property definitions matching the install.xml schema.
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'courseid' => [
                'type' => PARAM_INT,
            ],
            'userid' => [
                'type' => PARAM_INT,
            ],
            'action' => [
                'type' => PARAM_ALPHANUMEXT,
            ],
            'payloadjson' => [
                'type'    => PARAM_RAW,
                'default' => '',
            ],
            'status' => [
                'type'    => PARAM_ALPHANUMEXT,
                'default' => self::STATUS_PENDING,
                'choices' => [
                    self::STATUS_PENDING,
                    self::STATUS_PREVIEWED,
                    self::STATUS_EXECUTED,
                    self::STATUS_ROLLED_BACK,
                    self::STATUS_FAILED,
                ],
            ],
        ];
    }
}
