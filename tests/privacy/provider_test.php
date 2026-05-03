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
 * PHPUnit tests for the local_coursectrl privacy provider.
 *
 * Verifies all seven GDPR-relevant methods:
 *   get_metadata, get_contexts_for_userid, get_users_in_context,
 *   export_user_data, delete_data_for_all_users_in_context,
 *   delete_data_for_user, delete_data_for_users.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\privacy\provider::class)]
/**
 * Tests for the local_coursectrl privacy API provider.
 *
 * @covers \local_coursectrl\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    // Helpers.

    /**
     * Insert a batch record and return its id.
     *
     * @param int $userid
     * @param int $courseid
     * @return int
     */
    private function insert_batch(int $userid, int $courseid): int {
        global $DB;
        return (int) $DB->insert_record('local_coursectrl_batch', (object) [
            'courseid'     => $courseid,
            'userid'       => $userid,
            'action'       => 'shift_dates',
            'payloadjson'  => '{}',
            'status'       => 'executed',
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Insert a batch_item record and return its id.
     *
     * @param int $batchid
     * @param int $entityid
     * @return int
     */
    private function insert_batch_item(int $batchid, int $entityid): int {
        global $DB;
        return (int) $DB->insert_record('local_coursectrl_batch_item', (object) [
            'batchid'     => $batchid,
            'entitytype'  => 'cm',
            'entityid'    => $entityid,
            'component'   => 'mod_assign',
            'status'      => 'success',
            'previewjson' => '{}',
            'resultjson'  => '{}',
        ]);
    }

    /**
     * Insert a snapshot record and return its id.
     *
     * @param int $batchid
     * @param int $entityid
     * @return int
     */
    private function insert_snapshot(int $batchid, int $entityid): int {
        global $DB;
        return (int) $DB->insert_record('local_coursectrl_snapshot', (object) [
            'batchid'     => $batchid,
            'entitytype'  => 'cm',
            'entityid'    => $entityid,
            'component'   => 'mod_assign',
            'statejson'   => '{}',
            'timecreated' => time(),
        ]);
    }

    /**
     * Build an approved_contextlist for the given user and context.
     *
     * @param \stdClass      $user    User record.
     * @param \context       $context Context to include.
     * @return approved_contextlist
     */
    private function make_contextlist(\stdClass $user, \context $context): approved_contextlist {
        return new approved_contextlist($user, 'local_coursectrl', [$context->id]);
    }

    // A1.1  get_metadata.

    /**
     * Metadata collection lists the batch table and user preferences.
     *
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_get_metadata_contains_required_tables_and_preferences(): void {
        $this->resetAfterTest();

        $collection = new collection('local_coursectrl');
        $result = provider::get_metadata($collection);

        $this->assertInstanceOf(collection::class, $result);

        $names = array_map(fn ($item) => $item->get_name(), $result->get_collection());

        $this->assertContains('local_coursectrl_batch', $names);
        $this->assertContains('local_coursectrl_showcalendar', $names);
        $this->assertContains('local_coursectrl_immediateapply', $names);
    }

    // A1.2  get_contexts_for_userid.

    /**
     * A course context is returned for a user who has batch records in that course.
     *
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_get_contexts_for_userid_returns_batch_context(): void {
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $user    = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);

        $this->insert_batch((int) $user->id, (int) $course->id);

        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        $this->assertContainsEquals(
            $context->id,
            array_map('intval', $contextlist->get_contextids())
        );
    }

    // A1.3  get_users_in_context.

    /**
     * All users with batch records in a context are returned.
     *
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_get_users_in_context_returns_all_record_owners(): void {
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $user1   = $this->getDataGenerator()->create_user();
        $user2   = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);

        $this->insert_batch((int) $user1->id, (int) $course->id);
        $this->insert_batch((int) $user2->id, (int) $course->id);

        $userlist = new userlist($context, 'local_coursectrl');
        provider::get_users_in_context($userlist);

        $ids = $userlist->get_userids();
        $this->assertContains((int) $user1->id, $ids);
        $this->assertContains((int) $user2->id, $ids);
    }

    // A1.4  export_user_data.

    /**
     * Batch records are exported into the course context.
     *
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_export_user_data_exports_batch_records(): void {
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $user    = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);

        $this->insert_batch((int) $user->id, (int) $course->id);

        $contextlist = $this->make_contextlist($user, $context);
        provider::export_user_data($contextlist);

        $data = writer::with_context($context)->get_data(
            [get_string('privacy:path:batches', 'local_coursectrl')]
        );
        $this->assertNotEmpty($data->batches);
    }

    /**
     * User preferences are exported even when there is no course context.
     *
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_export_user_data_exports_user_preferences(): void {
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $user    = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);

        set_user_preference('local_coursectrl_showcalendar', '1', $user->id);

        $contextlist = $this->make_contextlist($user, $context);
        provider::export_user_data($contextlist);

        $systemwriter = writer::with_context(\context_system::instance());
        $prefs = $systemwriter->get_user_preferences('local_coursectrl');
        $this->assertNotEmpty($prefs);
        $this->assertObjectHasProperty('local_coursectrl_showcalendar', $prefs);
    }

    // A1.5  delete_data_for_all_users_in_context.

    /**
     * All plugin data in the course context is removed; other courses are unaffected.
     *
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_delete_data_for_all_users_removes_course_data(): void {
        global $DB;
        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $user    = $this->getDataGenerator()->create_user();

        $batchid1 = $this->insert_batch((int) $user->id, (int) $course1->id);
        $this->insert_batch_item($batchid1, 10);
        $this->insert_snapshot($batchid1, 10);

        // Data in second course must survive.
        $batchid2 = $this->insert_batch((int) $user->id, (int) $course2->id);

        $context = \context_course::instance($course1->id);
        provider::delete_data_for_all_users_in_context($context);

        $this->assertFalse($DB->record_exists('local_coursectrl_batch', ['courseid' => $course1->id]));
        $this->assertFalse($DB->record_exists('local_coursectrl_batch_item', ['batchid' => $batchid1]));
        $this->assertFalse($DB->record_exists('local_coursectrl_snapshot', ['batchid' => $batchid1]));

        // Course 2 batch must still exist.
        $this->assertTrue($DB->record_exists('local_coursectrl_batch', ['id' => $batchid2]));
    }

    /**
     * Non-course contexts are safely ignored without touching any data.
     *
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_delete_data_for_all_users_ignores_system_context(): void {
        global $DB;
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $user    = $this->getDataGenerator()->create_user();
        $batchid = $this->insert_batch((int) $user->id, (int) $course->id);

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertTrue($DB->record_exists('local_coursectrl_batch', ['id' => $batchid]));
    }

    // A1.6  delete_data_for_user.

    /**
     * Only the approved user's data is removed; a second user's data remains.
     *
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_delete_data_for_user_removes_only_that_user(): void {
        global $DB;
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $user1   = $this->getDataGenerator()->create_user();
        $user2   = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);

        $batchid1 = $this->insert_batch((int) $user1->id, (int) $course->id);
        $this->insert_batch_item($batchid1, 20);
        $this->insert_snapshot($batchid1, 20);

        $batchid2 = $this->insert_batch((int) $user2->id, (int) $course->id);

        $contextlist = $this->make_contextlist($user1, $context);
        provider::delete_data_for_user($contextlist);

        $this->assertFalse($DB->record_exists('local_coursectrl_batch', ['userid' => $user1->id]));
        $this->assertFalse($DB->record_exists('local_coursectrl_batch_item', ['batchid' => $batchid1]));
        $this->assertFalse($DB->record_exists('local_coursectrl_snapshot', ['batchid' => $batchid1]));

        // User 2 batch must survive.
        $this->assertTrue($DB->record_exists('local_coursectrl_batch', ['id' => $batchid2]));
    }

    /**
     * User preferences are removed when the user's data is deleted.
     *
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_delete_data_for_user_removes_preferences(): void {
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $user    = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);

        set_user_preference('local_coursectrl_showcalendar', '1', $user->id);
        set_user_preference('local_coursectrl_immediateapply', '0', $user->id);

        $contextlist = $this->make_contextlist($user, $context);
        provider::delete_data_for_user($contextlist);

        $this->assertNull(get_user_preferences('local_coursectrl_showcalendar', null, $user->id));
        $this->assertNull(get_user_preferences('local_coursectrl_immediateapply', null, $user->id));
    }

    // A1.7  delete_data_for_users.

    /**
     * A list of approved users has their data deleted; an unapproved user's data survives.
     *
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_delete_data_for_users_removes_listed_users(): void {
        global $DB;
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $user1   = $this->getDataGenerator()->create_user();
        $user2   = $this->getDataGenerator()->create_user();
        $user3   = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);

        $this->insert_batch((int) $user1->id, (int) $course->id);
        $this->insert_batch((int) $user2->id, (int) $course->id);
        $batchid3 = $this->insert_batch((int) $user3->id, (int) $course->id);

        $userlist = new approved_userlist(
            $context,
            'local_coursectrl',
            [(int) $user1->id, (int) $user2->id]
        );
        provider::delete_data_for_users($userlist);

        $this->assertFalse($DB->record_exists('local_coursectrl_batch', ['userid' => $user1->id]));
        $this->assertFalse($DB->record_exists('local_coursectrl_batch', ['userid' => $user2->id]));
        $this->assertTrue($DB->record_exists('local_coursectrl_batch', ['id' => $batchid3]));
    }

    /**
     * A non-course context is silently ignored by delete_data_for_users.
     *
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_delete_data_for_users_ignores_system_context(): void {
        global $DB;
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $user    = $this->getDataGenerator()->create_user();
        $batchid = $this->insert_batch((int) $user->id, (int) $course->id);

        $userlist = new approved_userlist(
            \context_system::instance(),
            'local_coursectrl',
            [(int) $user->id]
        );
        provider::delete_data_for_users($userlist);

        $this->assertTrue($DB->record_exists('local_coursectrl_batch', ['id' => $batchid]));
    }

    // A1.1b  get_metadata covers text_hit and risk.

    /**
     * Metadata collection declares local_coursectrl_text_hit and local_coursectrl_risk.
     * These tables hold course-level data only (no userid); they are declared to
     * document their existence and data categories, not because they are exported
     * per-user.
     *
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_get_metadata_contains_text_hit_and_risk_tables(): void {
        $this->resetAfterTest();

        $collection = new collection('local_coursectrl');
        $result = provider::get_metadata($collection);

        $names = array_map(fn ($item) => $item->get_name(), $result->get_collection());

        $this->assertContains('local_coursectrl_text_hit', $names);
        $this->assertContains('local_coursectrl_risk', $names);
    }

    // A1.5b  delete_data_for_all_users_in_context removes text_hit and risk rows.

    /**
     * Deleting all data in a course context also removes text_hit and risk rows.
     * These tables do not carry a userid but belong to the course context and
     * must be purged when the context is deleted.
     *
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_delete_data_for_all_users_removes_text_hits_and_risks(): void {
        global $DB;
        $this->resetAfterTest();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();

        // Insert a text_hit and a risk record for course1.
        $DB->insert_record('local_coursectrl_text_hit', (object) [
            'courseid'        => $course1->id,
            'entitytype'      => 'cm',
            'entityid'        => 1,
            'fieldname'       => 'intro',
            'matchedtext'     => '01.06.2026',
            'normalizedvalue' => '2026-06-01',
            'confidence'      => 'safe',
            'contextjson'     => '{}',
        ]);
        $DB->insert_record('local_coursectrl_risk', (object) [
            'courseid'    => $course1->id,
            'risktype'    => 'date_inversion',
            'severity'    => 'warning',
            'entitytype'  => 'cm',
            'entityid'    => 1,
            'detailsjson' => '{}',
            'timecreated' => time(),
        ]);

        // Control rows in course2 — must survive.
        $DB->insert_record('local_coursectrl_text_hit', (object) [
            'courseid'        => $course2->id,
            'entitytype'      => 'cm',
            'entityid'        => 2,
            'fieldname'       => 'intro',
            'matchedtext'     => '01.06.2026',
            'normalizedvalue' => '2026-06-01',
            'confidence'      => 'safe',
            'contextjson'     => '{}',
        ]);

        $context1 = \context_course::instance($course1->id);
        provider::delete_data_for_all_users_in_context($context1);

        $this->assertFalse(
            $DB->record_exists('local_coursectrl_text_hit', ['courseid' => $course1->id]),
            'text_hit rows for course1 must be deleted'
        );
        $this->assertFalse(
            $DB->record_exists('local_coursectrl_risk', ['courseid' => $course1->id]),
            'risk rows for course1 must be deleted'
        );
        // Course2 text_hit must survive.
        $this->assertTrue(
            $DB->record_exists('local_coursectrl_text_hit', ['courseid' => $course2->id]),
            'text_hit rows for course2 must survive'
        );
    }

    // A1.4b  export_user_data does NOT export text_hit/risk (course-level, no userid).

    /**
     * export_user_data must not export text_hit or risk records because these
     * tables contain course-level analysis data and carry no userid column.
     * This test documents the deliberate non-export decision.
     *
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_export_user_data_does_not_export_text_hits_or_risks(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();

        $DB->insert_record('local_coursectrl_text_hit', (object) [
            'courseid'        => $course->id,
            'entitytype'      => 'cm',
            'entityid'        => 1,
            'fieldname'       => 'intro',
            'matchedtext'     => '01.06.2026',
            'normalizedvalue' => '2026-06-01',
            'confidence'      => 'safe',
            'contextjson'     => '{}',
        ]);

        $contextlist = $this->make_contextlist($user, \context_course::instance($course->id));
        provider::export_user_data($contextlist);

        // Exporting a user that has no batch records must produce no export data.
        // text_hit data is course-level and must not appear in the user export.
        $context = \context_course::instance($course->id);
        $writer  = writer::with_context($context);
        $this->assertFalse(
            $writer->has_any_data(),
            'No user-specific data should be exported for text_hit/risk tables'
        );
    }
}
