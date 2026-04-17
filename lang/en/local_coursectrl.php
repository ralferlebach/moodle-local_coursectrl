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
$string['manage_select_all_section']           = 'select all';
$string['manage_deselect_all_section']         = 'deselect all';
$string['manage_unsupported']                  = 'no adapter';
$string['nav_bulk']                            = 'Bulk Actions';
$string['nav_dashboard']                       = 'Dashboard';
$string['nav_graph']                           = 'Dependency Graph';
$string['nav_history'] = 'Logs & History';
$string['nav_risks']                           = 'Risks';
$string['nav_simulation'] = 'Plausibility & Collision Check';
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
$string['sim_assumed_completions']             = 'Assumed completion states';
$string['sim_badge_blocked']                   = 'blocked';
$string['sim_badge_hidden']                    = 'hidden';
$string['sim_badge_next']                      = 'next step';
$string['sim_blocked'] = 'Blocked';
$string['sim_col_accessible']                  = 'Accessible';
$string['sim_col_activity']                    = 'Activity';
$string['sim_col_complete']                    = 'Assumed state';
$string['sim_col_reasons']                     = 'Conditions';
$string['sim_col_tracking']                    = 'Tracking';
$string['sim_groupids']                        = 'Group IDs (comma-separated)';
$string['sim_groupingids']                     = 'Grouping IDs (comma-separated)';
$string['sim_next_steps']                      = 'Next steps';
$string['sim_reason_completion']               = 'Requires completion of cmid {$a->cmid} (expected {$a->expected})';
$string['sim_reason_date']                     = 'Date condition: {$a->direction} {$a->threshold}';
$string['sim_reason_grade']                    = 'Grade condition (not simulated)';
$string['sim_reason_group']                    = 'Group membership required (id {$a->groupid})';
$string['sim_reason_grouping']                 = 'Grouping membership required (id {$a->groupingid})';
$string['sim_reason_hidden']                   = 'Activity is hidden by teacher';
$string['sim_reason_unknown']                  = 'Unknown condition';
$string['sim_result_all']                      = 'All activities';
$string['sim_run_btn']                         = 'Run simulation';
$string['sim_scenario']                        = 'Simulation scenario';
$string['sim_simdate']                         = 'Simulated date';
$string['sim_simtime']                         = 'Simulated time';
$string['sim_state_complete']                  = 'Complete';
$string['sim_state_fail']                      = 'Complete (fail)';
$string['sim_state_incomplete']                = 'Incomplete';
$string['sim_state_pass']                      = 'Complete (pass)';
$string['sim_summary']                         = '{$a->accessible} of {$a->total} accessible · {$a->nextsteps} next steps · {$a->blocked} blocked';
$string['sim_title']                           = 'Learner Simulation';
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
$string['settings_calmoodlecal_category']         = 'Assign category';
$string['settings_calmoodlecal_category_desc']    = 'Category for matched events: public_holiday, school_holiday, or custom.';
$string['settings_calmoodlecal_desc']             = 'Read events from the Moodle site calendar and display them as day markers.';
$string['settings_calmoodlecal_enabled']          = 'Enable Moodle calendar reader';
$string['settings_calmoodlecal_eventtype']        = 'Calendar contexts';
$string['settings_calmoodlecal_eventtype_category'] = 'Category events';
$string['settings_calmoodlecal_eventtype_site']    = 'Site-wide events (recommended)';
$string['settings_calmoodlecal_eventtype_user']    = 'User events';
$string['settings_calmoodlecal_eventtype_desc']   = 'Which Moodle calendar contexts to include. Default: site (system-wide events). Avoid course — it includes assignment deadlines and creates circular references.';
$string['settings_calmoodlecal_heading']          = 'Moodle Calendar Reader';
$string['settings_calmoodlecal_namepattern']      = 'Name filter (regex)';
$string['settings_calmoodlecal_namepattern_desc'] = 'Optional PHP regex, e.g. /Ferien|Holiday/i. Empty = include all events of the selected type.';
$string['settings_calmanual_desc']                = 'Enter custom non-teaching days (institutional breaks, events, sports days, etc.) directly.';
$string['settings_calmanual_enabled']             = 'Enable manual free-day provider';
$string['settings_calmanual_entries']             = 'Non-teaching day entries';
$string['settings_calmanual_entries_desc']        = "One entry per line: YYYY-MM-DD,Name,category  or  YYYY-MM-DD/YYYY-MM-DD,Name,category (range). Categories: public_holiday, school_holiday, custom. Lines starting with # are comments.";
$string['settings_calmanual_heading']             = 'Non-teaching Days (manual)';
$string['settings_calnager_countrycode']          = 'Country code (ISO 3166-1 alpha-2)';
$string['settings_calnager_countrycode_desc']     = 'E.g. DE, AT, CH, FR, US. See https://date.nager.at for supported countries.';
$string['settings_calnager_desc']                 = 'Fetches public holidays from the free Nager.Date REST API (no registration required).';
$string['settings_calnager_enabled']              = 'Enable Nager.Date provider';
$string['settings_calnager_heading']              = 'Nager.Date (Public Holidays)';
$string['settings_calopenholidays_categories']         = 'Categories to load';
$string['settings_calopenholidays_categories_desc']    = 'Comma-separated: public_holiday, school_holiday';
$string['settings_calopenholidays_countryisocode']     = 'Country ISO code';
$string['settings_calopenholidays_countryisocode_desc'] = 'E.g. DE, AT, CH';
$string['settings_calopenholidays_desc']               = 'Fetches public holidays and school holidays from the OpenHolidays API (no registration required).';
$string['settings_calopenholidays_enabled']            = 'Enable OpenHolidays API provider';
$string['settings_calopenholidays_heading']            = 'OpenHolidays API (Public + School Holidays)';
$string['settings_calopenholidays_languageisocode']    = 'Language ISO code';
$string['settings_calopenholidays_languageisocode_desc'] = 'Preferred language for holiday names, e.g. DE, EN, FR.';
$string['settings_calopenholidays_regioncode']         = 'Region code';
$string['settings_calopenholidays_regioncode_desc']    = 'Subdivision code for school holidays, e.g. DE-BY (Bavaria), AT-7 (Tyrol), CH-ZH (Zurich). Leave empty to skip school holidays.';
$string['nav_group_check'] = 'Check';
$string['nav_group_setup'] = 'Configure';
$string['nav_manage'] = 'Activity list';
$string['cal_hide'] = 'Hide calendar';
$string['cal_show'] = 'Show calendar';
$string['dashboard_upcoming28'] = 'Upcoming (28 days)';
$string['dashboard_upcoming7'] = 'Upcoming (7 days)';
$string['filter_activitytypes'] = 'Activity types';
$string['graph_hide_independents'] = 'Hide independent activities';
$string['history_empty'] = 'No actions recorded yet.';
$string['history_items'] = 'items';
$string['history_showing'] = 'Showing';
$string['no_dates_found'] = 'No dates found for the selected criteria.';
$string['rollback_action'] = 'Undo';
$string['rollback_confirm'] = 'Are you sure you want to undo this action?';
$string['section_colon'] = 'Section:';
$string['shift_dependants'] = 'Shift this + all dependent activities';
$string['shift_following'] = 'Shift this + following activities';
$string['shift_section_following'] = 'Shift this section + following';
$string['shift_section_single'] = 'Shift this section';
$string['shift_single'] = 'Shift this activity';
$string['sim_accessible'] = 'Accessible';
$string['sim_blocked_list'] = 'Blocked activities';
$string['sim_datetime'] = 'Date & time';
$string['sim_nextsteps'] = 'Recommended next steps';
$string['sim_run'] = 'Run simulation';
$string['sim_select_groupings'] = 'Select groupings …';
$string['sim_select_groups'] = 'Select groups …';
$string['sim_set_completions'] = 'Set completions …';
$string['sim_total'] = 'Total';
$string['tab_gantt'] = 'Gantt Chart';
$string['tab_textreview'] = 'Text Review';
$string['tab_timeline'] = 'Schedule';
$string['textreview_hint'] = 'Text review scans activity descriptions and labels for date references.';
$string['textreview_open'] = 'Open Text Review';
$string['toggle_past'] = 'Show/hide past';
$string['unknownuser'] = 'Unknown user';
$string['weekend'] = 'Weekend';
$string['privacy:metadata:batch'] = 'Batch records storing which user triggered each bulk action.';
$string['privacy:metadata:batch:action'] = 'The type of bulk action performed.';
$string['privacy:metadata:batch:courseid'] = 'The course the action was performed in.';
$string['privacy:metadata:batch:status'] = 'Execution status of the batch.';
$string['privacy:metadata:batch:timecreated'] = 'When the action was created.';
$string['privacy:metadata:batch:userid'] = 'The user who triggered the action.';
$string['privacy:metadata:pref:immediateapply'] = 'Whether the user has immediate-apply mode enabled.';
$string['privacy:metadata:pref:showcalendar'] = 'Whether the user has the mini-calendar visible.';
$string['privacy:metadata:preset'] = 'Saved action presets created by users.';
$string['privacy:metadata:preset:action'] = 'The action type stored in this preset.';
$string['privacy:metadata:preset:courseid'] = 'The course the preset belongs to.';
$string['privacy:metadata:preset:name'] = 'The preset name.';
$string['privacy:metadata:preset:timecreated'] = 'When the preset was created.';
$string['privacy:metadata:preset:userid'] = 'The user who created the preset.';
$string['privacy:metadata:report'] = 'Analysis reports generated by users.';
$string['privacy:metadata:report:courseid'] = 'The course the report is for.';
$string['privacy:metadata:report:reporttype'] = 'Type of analysis report.';
$string['privacy:metadata:report:timecreated'] = 'When the report was created.';
$string['privacy:metadata:report:userid'] = 'The user who generated the report.';
$string['privacy:path:batches'] = 'Batch actions';
$string['privacy:path:presets'] = 'Saved presets';
$string['settings_adapters_desc'] = 'The following coursectrlmod_* subplugins are currently installed.';
$string['settings_adapters_heading'] = 'Installed activity adapters';
$string['settings_adapters_installed'] = 'Installed adapters';
$string['settings_adapters_none'] = 'No activity adapters installed.';
$string['settings_considersections'] = 'Consider sections';
$string['settings_considersections_desc'] = 'Include section availability date conditions in timeline and shift operations.';
$string['settings_datescope'] = 'Date / time scope';
$string['settings_datescope_activity'] = 'Activity-specific date / time fields';
$string['settings_datescope_availability'] = 'Date conditions in availability rules';
$string['settings_datescope_completion'] = 'Course completion conditions';
$string['settings_datescope_course'] = 'Course settings (start / end date)';
$string['settings_datescope_desc'] = 'Which date and time fields are considered by the plugin (default: all).';
$string['settings_datescope_enrol'] = 'Enrolment settings';
$string['settings_datescope_reminder'] = 'Reminder dates in completion';
$string['settings_general_heading'] = 'General';
$string['settings_history_heading'] = 'Logs & History';
$string['settings_history_maxcount'] = 'Maximum records to display';
$string['settings_history_maxcount_desc'] = 'Maximum number of batch records shown on the Logs & History page per course.';
$string['settings_history_maxdays'] = 'Retention period (days)';
$string['settings_history_maxdays_desc'] = 'Batch records older than this many days are removed during scheduled cleanup.';
$string['settings_useroverride_calendar'] = 'Users may change calendar visibility';
$string['settings_useroverride_desc'] = 'When enabled, users can override this site-wide default in their own session.';
$string['settings_useroverride_immediateapply'] = 'Users may change immediate-apply preference';
$string['sim_completions'] = 'Completions';

$string['sim_groups'] = 'Groups';
$string['sim_groupings'] = 'Groupings';
$string['timeline_filter_group'] = 'Group';
$string['timeline_filter_group_none'] = 'All groups';
$string['timeline_action_delete_entry'] = 'Delete this date field';
$string['timeline_shift_heading'] = 'Shift dates';
$string['timeline_shift_days'] = 'Days';
$string['timeline_shift_hours'] = 'Hours';
$string['timeline_shift_apply'] = 'Apply shift';
$string['timeline_delete_heading'] = 'Delete date';

$string['rollback_success'] = 'Rollback completed successfully.';
$string['rollback_failed'] = 'Rollback failed';

$string['shift_immediate_apply'] = 'Apply changes immediately';

// Strings added in 0.1.41.
$string['nav_dependencies']                    = 'Dependencies';
$string['timeline_shift_following_heading']    = 'Shift this and all following dates';
$string['textreview_workflow_hint_title']      = 'Text review after date shift';
$string['textreview_workflow_hint']            = 'Review detected date references in free-text fields and update them alongside your date shifts.';
$string['textreview_collision_warning']        = 'Collision warning: Please review the following conflicts before applying:';
$string['textreview_delta_days']               = 'Days';
// Strings added in 0.1.42 – text-review workflow.
$string['shift_scan_text']         = 'Show text review after shift';
$string['shift_done']              = 'Date shift applied successfully';
$string['shift_collision_generic'] = 'Date conflict detected (see log for details)';
// Strings added in 0.1.43 -- AJAX shift dialog.
$string['shift_ajax_skip']         = 'Close without text changes';
$string['shift_ajax_review_apply'] = 'Apply selected text changes';

// Strings added in 0.1.45 -- assign consistency checks.
$string['check_assign_from_after_due']    = 'Opening date is after due date (invalid combination).';
$string['check_assign_cutoff_before_due'] = 'Cut-off date is before due date.';
