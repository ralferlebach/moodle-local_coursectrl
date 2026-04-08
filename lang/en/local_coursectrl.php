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
 * English language strings for local_coursectrl.
 *
 * @package    local_coursectrl
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Core.
$string['pluginname']      = 'Course Control Hub';
$string['pluginname_desc'] = 'Course-wide analysis, bulk editing, timeline visualisation, learner simulation, and risk detection for Moodle courses.';

// Capabilities.
$string['coursectrl:view']          = 'View the Course Control Hub';
$string['coursectrl:bulkaction']    = 'Execute bulk actions';
$string['coursectrl:viewreports']   = 'View reports and risk analyses';
$string['coursectrl:rollback']      = 'Roll back executed batch actions';
$string['coursectrl:managepresets'] = 'Create and manage action presets';
$string['coursectrl:simulate']      = 'Run learner-state simulations';

// Navigation.
$string['nav_dashboard']   = 'Dashboard';
$string['nav_bulk']        = 'Bulk Actions';
$string['nav_timeline']    = 'Timeline';
$string['nav_graph']       = 'Dependency Graph';
$string['nav_simulation']  = 'Simulation';
$string['nav_risks']       = 'Risks';
$string['nav_history']     = 'History';

// Stub / placeholder.
$string['stub_placeholder'] = 'Course Control Hub – Phase 1 stub. Full interface coming in Phase 2.';

// Errors.
$string['error_no_course']    = 'No valid course context found.';
$string['error_no_capability'] = 'You do not have permission to use the Course Control Hub in this course.';
