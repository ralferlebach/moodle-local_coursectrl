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
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Core.
$string['pluginname']      = 'Course Control Hub';
$string['pluginname_desc'] = 'Course-wide analysis, bulk editing, timeline visualisation, learner simulation, and risk detection for Moodle courses.';

// Subplugin types.
$string['subplugintype_coursectrlmod']        = 'Activity adapter';
$string['subplugintype_coursectrlmod_plural'] = 'Activity adapters';

// Capabilities.
$string['coursectrl:view']          = 'View the Course Control Hub';
$string['coursectrl:bulkaction']    = 'Execute bulk actions';
$string['coursectrl:viewreports']   = 'View reports and risk analyses';
$string['coursectrl:rollback']      = 'Roll back executed batch actions';
$string['coursectrl:managepresets'] = 'Create and manage action presets';
$string['coursectrl:simulate']      = 'Run learner-state simulations';

// Events.
$string['event_batch_executed'] = 'Bulk action batch executed';

// Navigation.
$string['nav_dashboard']  = 'Dashboard';
$string['nav_bulk']       = 'Bulk Actions';
$string['nav_timeline']   = 'Timeline';
$string['nav_graph']      = 'Dependency Graph';
$string['nav_simulation'] = 'Simulation';
$string['nav_risks']      = 'Risks';
$string['nav_history']    = 'History';

// Dashboard.
$string['dashboard_startdate']        = 'Start date';
$string['dashboard_enddate']          = 'End date';
$string['dashboard_visibility']       = 'Visibility';
$string['dashboard_visible']          = 'Visible';
$string['dashboard_hidden']           = 'Hidden';
$string['dashboard_sections']         = 'Sections';
$string['dashboard_activities']       = 'Activities';
$string['dashboard_activities_short'] = 'activities';
$string['dashboard_texts']            = 'Editable texts';
$string['dashboard_inventory']        = 'Course inventory';
$string['dashboard_section']          = 'Section';
$string['dashboard_completion']       = 'Completion';
$string['dashboard_availability']     = 'Restricted';
$string['dashboard_empty']            = 'This course has no inventoried sections yet.';

// Stub / placeholder.
$string['stub_placeholder'] = 'Course Control Hub – Phase 1 stub. Full interface coming in Phase 2.';

// Errors.
$string['error_no_course']     = 'No valid course context found.';
$string['error_no_capability'] = 'You do not have permission to use the Course Control Hub in this course.';
