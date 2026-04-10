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
 * Persistent for the local_coursectrl_batch_item table.
 *
 * Per-entity result record for every object touched in a batch. Mirrors the
 * schema defined in db/install.xml. previewjson and resultjson hold the
 * serialised preview_change and execution_result DTOs respectively.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\persistent;

use core\persistent;

/**
 * Persistent wrapping a single batch_item row.
 */
class batch_item extends persistent {
    /** @var string Database table name. */
    const TABLE = 'local_coursectrl_batch_item';

    /** @var string Initial item status. */
    const STATUS_PENDING = 'pending';

    /** @var string Item was deliberately not processed. */
    const STATUS_SKIPPED = 'skipped';

    /** @var string Item was processed successfully. */
    const STATUS_SUCCESS = 'success';

    /** @var string Item processing failed. */
    const STATUS_ERROR = 'error';

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
            'status' => [
                'type'    => PARAM_ALPHANUMEXT,
                'default' => self::STATUS_PENDING,
                'choices' => [
                    self::STATUS_PENDING,
                    self::STATUS_SKIPPED,
                    self::STATUS_SUCCESS,
                    self::STATUS_ERROR,
                ],
            ],
            'previewjson' => [
                'type'    => PARAM_RAW,
                'null'    => NULL_ALLOWED,
                'default' => null,
            ],
            'resultjson' => [
                'type'    => PARAM_RAW,
                'null'    => NULL_ALLOWED,
                'default' => null,
            ],
        ];
    }
}
