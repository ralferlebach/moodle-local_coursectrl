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
 * Moodle Universal Cache (MUC) definitions for local_coursectrl.
 *
 * caldata — Application-level cache for holiday and free-period data
 *            fetched from external calendar provider APIs. TTL 24 hours.
 *            Key format: {component}_{year}_{month}[_{region}][_{hash}]
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'caldata' => [
        'mode' => cache_store::MODE_APPLICATION,
        'ttl' => 86400,
        'simplekeys' => true,
        'simpledata' => false,
    ],
];
