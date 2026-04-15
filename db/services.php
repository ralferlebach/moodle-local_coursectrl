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
 * External function declarations for local_coursectrl.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_coursectrl_get_inventory' => [
        'classname'    => 'local_coursectrl\\external\\get_inventory',
        'methodname'   => 'execute',
        'description'  => 'Returns the normalised inventory snapshot for a course.',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/coursectrl:view',
    ],
    'local_coursectrl_preview_bulk_action' => [
        'classname'    => 'local_coursectrl\\external\\preview_bulk_action',
        'methodname'   => 'execute',
        'description'  => 'Returns a course-wide preview of a bulk action without mutating state.',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/coursectrl:view',
    ],
    'local_coursectrl_execute_bulk_action' => [
        'classname'    => 'local_coursectrl\\external\\execute_bulk_action',
        'methodname'   => 'execute',
        'description'  => 'Executes a course-wide bulk action and returns the batch id with summary.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/coursectrl:bulkaction',
    ],
    'local_coursectrl_get_text_hits' => [
        'classname'    => 'local_coursectrl\\external\\get_text_hits',
        'methodname'   => 'execute',
        'description'  => 'Scans course texts for date/time references and returns detected hits.',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'local/coursectrl:view',
    ],
    'local_coursectrl_apply_text_changes' => [
        'classname'    => 'local_coursectrl\\external\\apply_text_changes',
        'methodname'   => 'execute',
        'description'  => 'Applies a delta shift to confirmed text-datetime hits.',
        'type'         => 'write',
        'ajax'         => true,
        'capabilities' => 'local/coursectrl:bulkaction',
    ],
];
