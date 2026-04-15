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
 * Persistent for the local_coursectrl_text_hit table.
 *
 * Stores detected date/time references inside free-text fields. Each row
 * represents one matched substring together with its parsed normalised
 * value and a confidence classification.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\persistent;

use core\persistent;

/**
 * Persistent wrapping a single text-hit row.
 */
class text_hit extends persistent {
    /** @var string Database table name. */
    const TABLE = 'local_coursectrl_text_hit';

    /** @var string Confidently parseable – safe for automatic transformation. */
    const CONFIDENCE_SAFE = 'safe';

    /** @var string Ambiguous match – requires manual review before transformation. */
    const CONFIDENCE_AMBIGUOUS = 'ambiguous';

    /** @var string Informational only – not suitable for automatic transformation. */
    const CONFIDENCE_INFORMATIONAL = 'informational';

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
            'entitytype' => [
                'type' => PARAM_ALPHANUMEXT,
            ],
            'entityid' => [
                'type' => PARAM_INT,
            ],
            'fieldname' => [
                'type' => PARAM_ALPHANUMEXT,
            ],
            'matchedtext' => [
                'type' => PARAM_RAW,
            ],
            'normalizedvalue' => [
                'type'    => PARAM_RAW,
                'default' => null,
                'null'    => NULL_ALLOWED,
            ],
            'confidence' => [
                'type'    => PARAM_ALPHANUMEXT,
                'default' => self::CONFIDENCE_AMBIGUOUS,
                'choices' => [
                    self::CONFIDENCE_SAFE,
                    self::CONFIDENCE_AMBIGUOUS,
                    self::CONFIDENCE_INFORMATIONAL,
                ],
            ],
            'contextjson' => [
                'type'    => PARAM_RAW,
                'default' => null,
                'null'    => NULL_ALLOWED,
            ],
        ];
    }
}
