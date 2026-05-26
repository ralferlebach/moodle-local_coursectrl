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
 * Privacy API provider for local_coursectrl.
 *
 * User-specific data stored by this plugin:
 *
 *   local_coursectrl_batch       — userid on each batch (action log).
 *   local_coursectrl_batch_item  — linked to batch via batchid (indirect).
 *   local_coursectrl_snapshot    — linked to batch via batchid (indirect).
 *   user_preferences             — local_coursectrl_showcalendar,
 *                                  local_coursectrl_immediateapply.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\privacy;


use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_coursectrl.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe all personal data this plugin stores.
     *
     * @param collection $collection Metadata collection to populate.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_coursectrl_batch',
            [
                'userid'      => 'privacy:metadata:batch:userid',
                'courseid'    => 'privacy:metadata:batch:courseid',
                'action'      => 'privacy:metadata:batch:action',
                'status'      => 'privacy:metadata:batch:status',
                'timecreated' => 'privacy:metadata:batch:timecreated',
            ],
            'privacy:metadata:batch'
        );
        $collection->add_user_preference(
            'local_coursectrl_showcalendar',
            'privacy:metadata:pref:showcalendar'
        );
        $collection->add_user_preference(
            'local_coursectrl_immediateapply',
            'privacy:metadata:pref:immediateapply'
        );
        // Text-hit records capture matched course-text fragments — no personal user data.
        $collection->add_database_table(
            'local_coursectrl_text_hit',
            [
                'courseid'        => 'privacy:metadata:local_coursectrl_text_hit:courseid',
                'entitytype'      => 'privacy:metadata:local_coursectrl_text_hit:entitytype',
                'entityid'        => 'privacy:metadata:local_coursectrl_text_hit:entityid',
                'fieldname'       => 'privacy:metadata:local_coursectrl_text_hit:fieldname',
                'matchedtext'     => 'privacy:metadata:local_coursectrl_text_hit:matchedtext',
                'normalizedvalue' => 'privacy:metadata:local_coursectrl_text_hit:normalizedvalue',
                'confidence'      => 'privacy:metadata:local_coursectrl_text_hit:confidence',
                'contextjson'     => 'privacy:metadata:local_coursectrl_text_hit:contextjson',
                'timecreated'     => 'privacy:metadata:local_coursectrl_text_hit:timecreated',
            ],
            'privacy:metadata:local_coursectrl_text_hit'
        );
        // Risk records are system-generated analysis results — no personal user data.
        $collection->add_database_table(
            'local_coursectrl_risk',
            [
                'courseid'    => 'privacy:metadata:local_coursectrl_risk:courseid',
                'risktype'    => 'privacy:metadata:local_coursectrl_risk:risktype',
                'severity'    => 'privacy:metadata:local_coursectrl_risk:severity',
                'entitytype'  => 'privacy:metadata:local_coursectrl_risk:entitytype',
                'entityid'    => 'privacy:metadata:local_coursectrl_risk:entityid',
                'detailsjson' => 'privacy:metadata:local_coursectrl_risk:detailsjson',
                'timecreated' => 'privacy:metadata:local_coursectrl_risk:timecreated',
            ],
            'privacy:metadata:local_coursectrl_risk'
        );

        // External calendar services: country code, year, and language are
        // administrative configuration values — not personal user data.
        // Documented here for transparency as required by Moodle plugin review.
        $collection->add_external_location_link(
            'nager_date_api',
            [
                'countrycode' => 'privacy:metadata:external:nager:countrycode',
                'year'        => 'privacy:metadata:external:nager:year',
            ],
            'privacy:metadata:external:nager'
        );
        $collection->add_external_location_link(
            'openholidays_api',
            [
                'countryisocode'  => 'privacy:metadata:external:openholidays:countryisocode',
                'languageisocode' => 'privacy:metadata:external:openholidays:languageisocode',
                'subdivisioncode' => 'privacy:metadata:external:openholidays:subdivisioncode',
                'validfrom'       => 'privacy:metadata:external:openholidays:validfrom',
                'validto'         => 'privacy:metadata:external:openholidays:validto',
            ],
            'privacy:metadata:external:openholidays'
        );

        return $collection;
    }

    /**
     * Return contexts that contain personal data for the given user.
     *
     * @param int $userid The user id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT DISTINCT ctx.id
                  FROM {local_coursectrl_batch} b
                  JOIN {context} ctx ON ctx.instanceid = b.courseid
                                    AND ctx.contextlevel = :ctxlevel
                 WHERE b.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'userid'   => $userid,
            'ctxlevel' => CONTEXT_COURSE,
        ]);
        return $contextlist;
    }

    /**
     * Return users in a context that have personal data stored.
     *
     * @param userlist $userlist The userlist.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!($context instanceof \context_course)) {
            return;
        }
        $params = ['courseid' => $context->instanceid];
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {local_coursectrl_batch} WHERE courseid = :courseid',
            $params
        );
    }

    /**
     * Export personal data for the given approved contextlist.
     *
     * @param approved_contextlist $contextlist Approved context list.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!($context instanceof \context_course)) {
                continue;
            }
            $courseid = $context->instanceid;

            // Export batch records.
            $batches = $DB->get_records(
                'local_coursectrl_batch',
                ['userid' => $userid, 'courseid' => $courseid]
            );
            if (!empty($batches)) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:path:batches', 'local_coursectrl')],
                    (object)['batches' => array_values($batches)]
                );
            }
        }

        // Export user preferences (stored at system level, not per-course).
        $prefs = [
            'local_coursectrl_showcalendar',
            'local_coursectrl_immediateapply',
        ];
        foreach ($prefs as $pref) {
            $val = get_user_preferences($pref, null, $userid);
            if ($val !== null) {
                writer::with_context(\context_system::instance())->export_user_preference(
                    'local_coursectrl',
                    $pref,
                    $val,
                    get_string(
                        'privacy:metadata:pref:' . str_replace('local_coursectrl_', '', $pref),
                        'local_coursectrl'
                    )
                );
            }
        }
    }

    /**
     * Delete all personal data in the given context.
     *
     * @param \context $context The context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if (!($context instanceof \context_course)) {
            return;
        }
        $courseid = $context->instanceid;
        $batchids = $DB->get_fieldset_select(
            'local_coursectrl_batch',
            'id',
            'courseid = :courseid',
            ['courseid' => $courseid]
        );
        if (!empty($batchids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($batchids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_coursectrl_batch_item', "batchid $insql", $inparams);
            $DB->delete_records_select('local_coursectrl_snapshot', "batchid $insql", $inparams);
            $DB->delete_records_select('local_coursectrl_batch', "id $insql", $inparams);
        }
        $DB->delete_records('local_coursectrl_text_hit', ['courseid' => $courseid]);
        $DB->delete_records('local_coursectrl_risk', ['courseid' => $courseid]);
    }

    /**
     * Delete personal data for a specific user in the given contexts.
     *
     * @param approved_contextlist $contextlist Approved context list.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!($context instanceof \context_course)) {
                continue;
            }
            $courseid = $context->instanceid;
            $batchids = $DB->get_fieldset_select(
                'local_coursectrl_batch',
                'id',
                'userid = :userid AND courseid = :courseid',
                ['userid' => $userid, 'courseid' => $courseid]
            );
            if (!empty($batchids)) {
                [$insql, $inparams] = $DB->get_in_or_equal($batchids, SQL_PARAMS_NAMED);
                $DB->delete_records_select('local_coursectrl_batch_item', "batchid $insql", $inparams);
                $DB->delete_records_select('local_coursectrl_snapshot', "batchid $insql", $inparams);
                $DB->delete_records_select('local_coursectrl_batch', "id $insql", $inparams);
            }
        }
        unset_user_preference('local_coursectrl_showcalendar', $userid);
        unset_user_preference('local_coursectrl_immediateapply', $userid);
    }

    /**
     * Delete data for a list of users within a single context.
     *
     * @param approved_userlist $userlist Approved user list.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if (!($context instanceof \context_course)) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            $singlecontextlist = new approved_contextlist(
                \core_user::get_user($userid),
                'local_coursectrl',
                [$context->id]
            );
            static::delete_data_for_user($singlecontextlist);
        }
    }
}
