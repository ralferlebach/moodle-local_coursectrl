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
 * Compatibility redirect: simulation.php → checks.php?tab=simulation.
 *
 * The simulation view has been moved into the unified checks page.
 * This file ensures that any external links (e.g. from simulation overlay
 * in the dependency graph) continue to work.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);

// Preserve all query parameters except courseid so the simulation form
// state (simdate, simtime, completions[], groupids[], run) is forwarded.
$params = ['courseid' => $courseid, 'tab' => 'simulation'];
$forwardkeys = ['simdate', 'simtime', 'run'];
foreach ($forwardkeys as $key) {
    $val = optional_param($key, null, PARAM_RAW);
    if ($val !== null && $val !== '') {
        $params[$key] = $val;
    }
}
// Forward array params.
$completions = optional_param_array('completions', [], PARAM_INT);
if (!empty($completions)) {
    $params['completions'] = $completions;
}
$groupids = optional_param_array('groupids', [], PARAM_INT);
if (!empty($groupids)) {
    $params['groupids'] = $groupids;
}

redirect(new moodle_url('/local/coursectrl/checks.php', $params));
