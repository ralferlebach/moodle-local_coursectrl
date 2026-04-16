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

$string['action_shift_dates']                  = 'Shift dates';
$string['coursectrl:bulkaction']               = 'Execute bulk actions';
$string['coursectrl:managepresets']            = 'Create and manage action presets';
$string['coursectrl:rollback']                 = 'Roll back executed batch actions';
$string['coursectrl:simulate']                 = 'Run learner-state simulations';
$string['coursectrl:view']                     = 'View the Course Control Hub';
$string['coursectrl:viewreports']              = 'View reports and risk analyses';
$string['dashboard_activities']                = 'Activities';
$string['dashboard_activities_short']          = 'activities';
$string['dashboard_availability']              = 'Restricted';
$string['dashboard_completion']                = 'Completion';
$string['dashboard_completion_auto']           = 'Auto';
$string['dashboard_completion_manual']         = 'Manual';
$string['dashboard_empty']                     = 'This course has no inventoried sections yet.';
$string['dashboard_enddate']                   = 'End date';
$string['dashboard_hidden']                    = 'Hidden';
$string['dashboard_inventory']                 = 'Course inventory';
$string['dashboard_requires']                  = 'Requires:';
$string['dashboard_section']                   = 'Section';
$string['dashboard_sections']                  = 'Sections';
$string['dashboard_startdate']                 = 'Start date';
$string['dashboard_texts']                     = 'Editable texts';
$string['dashboard_unlocks']                   = 'Unlocks:';
$string['dashboard_visibility']                = 'Visibility';
$string['dashboard_visible']                   = 'Visible';
$string['dashboard_warnings']                  = 'Warnings';
$string['error_no_capability']                 = 'You do not have permission to use the Course Control Hub in this course.';
$string['error_no_course']                     = 'No valid course context found.';
$string['event_batch_executed']                = 'Bulk action batch executed';
$string['graph_edges']                         = 'Edges';
$string['graph_empty']                         = 'No activities found in this course.';
$string['graph_gantt_activities']              = 'Activities with dates';
$string['graph_gantt_empty']                   = 'No timed activities found in this course.';
$string['graph_legend_circular']               = '🔴 = circular dependency';
$string['graph_loading']                       = 'Building visualisation…';
$string['graph_nodes']                         = 'Nodes';
$string['graph_tab_deps']                      = 'Dependency Graph';
$string['graph_tab_gantt']                     = 'Gantt';
$string['graph_title']                         = 'Dependency Graph &amp; Gantt';
$string['invalidaction']                       = 'Invalid action requested.';
$string['manage_action']                       = 'Action';
$string['manage_action_config']                = 'Action configuration';
$string['manage_delta']                        = 'Time shift';
$string['manage_delta_help']                   = 'Positive values shift dates forward, negative values shift them back.';
$string['manage_deselect_all']                 = 'Deselect all';
$string['manage_no_activities']                = 'No activities in this section.';
$string['manage_no_selection']                 = 'Please select at least one activity.';
$string['manage_preview_btn']                  = 'Preview changes';
$string['manage_select_activities']            = 'Select activities';
$string['manage_select_all']                   = 'Select all supported';
$string['manage_supported_hint']               = 'Supported activities with a registered adapter:';
$string['manage_toggle_section']               = 'Toggle section';
$string['manage_unsupported']                  = 'no adapter';
$string['nav_bulk']                            = 'Bulk Actions';
$string['nav_dashboard']                       = 'Dashboard';
$string['nav_graph']                           = 'Dependency Graph';
$string['nav_history']                         = 'History';
$string['nav_risks']                           = 'Risks';
$string['nav_simulation']                      = 'Simulation';
$string['nav_textreview']                      = 'Text Review';
$string['nav_timeline']                        = 'Timeline';
$string['pluginname']                          = 'Course Control Hub';
$string['pluginname_desc']                     = 'Course-wide analysis, bulk editing, timeline visualisation, learner simulation, and risk detection for Moodle courses.';
$string['preview_changes']                     = 'Previewed changes';
$string['preview_col_activity']                = 'Activity';
$string['preview_col_field']                   = 'Field';
$string['preview_col_new']                     = 'New value';
$string['preview_col_old']                     = 'Current value';
$string['preview_col_status']                  = 'Status';
$string['preview_errors']                      = 'Errors';
$string['preview_execute_btn']                 = 'Execute changes';
$string['preview_skipped']                     = 'Skipped';
$string['preview_status_changed']              = 'Changed';
$string['preview_status_unchanged']            = 'Unchanged';
$string['preview_status_unset']                = 'Unset';
$string['preview_summary']                     = 'Preview summary';
$string['preview_title']                       = 'Preview';
$string['preview_total']                       = 'Total items';
$string['preview_with_changes']                = 'With changes';
$string['result_batch_id']                     = 'Batch ID';
$string['result_col_error']                    = 'Errors';
$string['result_col_success']                  = 'Successful';
$string['result_failed']                       = 'Batch execution failed';
$string['result_new_action']                   = 'New bulk action';
$string['result_success']                      = 'Batch executed successfully';
$string['result_summary']                      = 'Execution summary';
$string['result_title']                        = 'Execution result';
$string['shift_no_change']                     = 'Nothing to shift (no activities selected or delta is zero).';
$string['stub_placeholder']                    = 'Course Control Hub – Phase 1 stub. Full interface coming in Phase 2.';
$string['subplugintype_coursectrlmod']         = 'Activity adapter';
$string['subplugintype_coursectrlmod_plural']  = 'Activity adapters';
$string['textreview_ambiguous']                = 'Ambiguous';
$string['textreview_apply_btn']                = 'Apply selected changes';
$string['textreview_apply_config']             = 'Shift configuration';
$string['textreview_applied_result']           = '{$a->applied} change(s) applied, {$a->skipped} skipped, {$a->errors} error(s).';
$string['textreview_col_confidence']           = 'Confidence';
$string['textreview_col_context']              = 'Context';
$string['textreview_col_location']             = 'Location';
$string['textreview_col_match']                = 'Matched text';
$string['textreview_col_normalized']           = 'Parsed value';
$string['textreview_hits']                     = 'Detected date/time references';
$string['textreview_informational']            = 'Informational';
$string['textreview_no_hits']                  = 'No date or time references found in course texts.';
$string['textreview_safe']                     = 'Safe';
$string['textreview_select_safe']              = 'Select all safe';
$string['textreview_summary']                  = 'Scan summary';
$string['textreview_title']                    = 'Text Review';
$string['timeline_action_delete']              = 'Delete this date';
$string['timeline_action_shift_entry']         = 'Shift this entry';
$string['timeline_action_shift_slot']          = 'Shift all entries at this time';
$string['timeline_action_view']                = 'View in dashboard';
$string['timeline_calendar_title']             = 'Month overview';
$string['timeline_days']                       = 'days';
$string['timeline_delete_dialog_title']        = 'Delete date';
$string['timeline_delete_warning']             = 'This will clear the selected date field. This action can be rolled back from the batch history.';
$string['timeline_empty']                      = 'No timed activities match the current filters.';
$string['timeline_entries']                    = 'entries';
$string['timeline_filter_components']          = 'Activity types:';
$string['timeline_immediate_apply']            = 'Apply immediately (skip confirmation)';
$string['timeline_only_with_deps']             = 'Only entries with dependents';
$string['timeline_shift']                      = 'Shift';
$string['timeline_shift_dialog_title']         = 'Shift dates';
$string['timeline_shift_followdeps']           = 'Also shift all dependent activities';
$string['timeline_show_past']                  = 'Show past entries';
$string['timeline_title']                      = 'Timeline by date';
$string['warning_circular_dep']                = 'Circular dependency detected';
$string['warning_dangling_dep']                = 'Prerequisite references a non-existent activity (ID {$a->cmid})';
$string['warning_impossible_dep']              = 'Prerequisite \'{$a->name}\' has completion tracking disabled';
$string['warning_temporal_conflict']           = 'Date conflict: {$a->field_early} is set later than {$a->field_late}';
