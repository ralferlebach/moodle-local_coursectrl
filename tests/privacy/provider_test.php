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
     * Insert a minimal batch row and return its id.
     *
     * @param int $userid
     * @param int $courseid
     * @return int Inserted batch id.
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
     * Insert a batch_item linked to a batch row.
     *
     * @param int $batchid
     * @param int $entityid
     */
    private function insert_batch_item(int $batchid, int $entityid): void {
        global $DB;
        $DB->insert_record('local_coursectrl_batch_item', (object) [
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
     * Insert a snapshot linked to a batch row.
     *
     * @param int $batchid
     * @param int $entityid
     */
    private function insert_snapshot(int $batchid, int $entityid): void {
        global $DB;
        $DB->insert_record('local_coursectrl_snapshot', (object) [
            'batchid'     => $batchid,
            'entitytype'  => 'cm',
            'entityid'    => $entityid,
            'component'   => 'mod_assign',
            'statejson'   => '{}',
            'timecreated' => time(),
        ]);
    }

    /**
     * Insert a preset row and return its id.
     *
     * @param int $userid
     * @param int $courseid
     * @return int
     */
    private function insert_preset(int $userid, int $courseid): int {
        global $DB;
        return (int) $DB->insert_record('local_coursectrl_preset', (object) [
            'userid'       => $userid,
            'courseid'     => $courseid,
            'name'         => 'Test Preset',
            'description'  => '',
            'action'       => 'shift_dates',
            'configjson'   => '{}',
            'scope'        => 'private',
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Insert a report row and return its id.
     *
     * @param int $userid
     * @param int $courseid
     * @return int
     */
    private function insert_report(int $userid, int $courseid): int {
        global $DB;
        return (int) $DB->insert_record('local_coursectrl_report', (object) [
            'courseid'    => $courseid,
            'userid'      => $userid,
            'reporttype'  => 'checks',
            'configjson'  => '{}',
            'resultjson'  => '{}',
            'timecreated' => time(),
        ]);
    }

    /**
     * Build an approved_contextlist for the given user + context.
     *
     * @param \stdClass $user
     * @param \context  $context
     * @return approved_contextlist
     */
    private function make_contextlist(\stdClass $user, \context $context): approved_contextlist {
        return new approved_contextlist(
            $user,
            'local_coursectrl',
            [$context->id]
        );
    }

    // A1.1  get_metadata.

    /**
     * get_metadata registers the three DB tables and two user preferences.
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_get_metadata_contains_required_tables_and_preferences(): void {
        $this->resetAfterTest();

        $collection = new collection('local_coursectrl');
        $result = provider::get_metadata($collection);

        $this->assertInstanceOf(collection::class, $result);

        $names = array_map(fn ($item) => $item->get_name(), $result->get_collection());

        $this->assertContains('local_coursectrl_batch', $names);
        $this->assertContains('local_coursectrl_preset', $names);
        $this->assertContains('local_coursectrl_report', $names);
        $this->assertContains('local_coursectrl_showcalendar', $names);
        $this->assertContains('local_coursectrl_immediateapply', $names);
    }

    // A1.2  get_contexts_for_userid.

    /**
     * A user with a batch record in a course gets that course context returned.
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_get_contexts_for_userid_returns_batch_context(): void {
        $this->resetAfterTest();

        $user    = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id); // Ensure context row exists in DB.
        $this->insert_batch((int) $user->id, (int) $course->id);

        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        $contextids  = $contextlist->get_contextids();

        $this->assertContains((int) $context->id, array_map('intval', $contextids));
    }

    /**
     * A user with a preset record in a course gets that course context returned.
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_get_contexts_for_userid_returns_preset_context(): void {
        $this->resetAfterTest();

        $user    = $this->getDataGenerator()->create_user();
        $course  = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id); // Ensure context row exists in DB.
        $this->insert_preset((int) $user->id, (int) $course->id);

        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        $contextids  = $contextlist->get_contextids();

        $this->assertContains((int) $context->id, array_map('intval', $contextids));
    }

    /**
     * A user with no plugin data gets an empty context list.
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_get_contexts_for_userid_returns_empty_for_unknown_user(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $contextlist = provider::get_contexts_for_userid((int) $user->id);

        $this->assertCount(0, $contextlist->get_contextids());
    }

    // A1.3  get_users_in_context.

    /**
     * Users with batch, preset, or report data appear in the course context userlist.
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_get_users_in_context_returns_all_record_owners(): void {
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $usera   = $this->getDataGenerator()->create_user();
        $userb   = $this->getDataGenerator()->create_user();
        $userc   = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);

        $this->insert_batch((int) $usera->id, (int) $course->id);
        $this->insert_preset((int) $userb->id, (int) $course->id);
        $this->insert_report((int) $userc->id, (int) $course->id);

        $userlist = new userlist($context, 'local_coursectrl');
        provider::get_users_in_context($userlist);

        $returned = $userlist->get_userids();
        $this->assertContains((int) $usera->id, $returned);
        $this->assertContains((int) $userb->id, $returned);
        $this->assertContains((int) $userc->id, $returned);
    }

    /**
     * Non-course contexts are silently ignored by get_users_in_context.
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_get_users_in_context_ignores_system_context(): void {
        $this->resetAfterTest();

        $context  = \context_system::instance();
        $userlist = new userlist($context, 'local_coursectrl');
        provider::get_users_in_context($userlist);

        $this->assertCount(0, $userlist->get_userids());
    }

    // A1.4  export_user_data.

    /**
     * Batch rows for the user are exported; another user's rows are not.
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_export_user_data_exports_only_requesting_user(): void {
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $user    = $this->getDataGenerator()->create_user();
        $other   = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);

        $batchid = $this->insert_batch((int) $user->id, (int) $course->id);
        $this->insert_batch_item($batchid, 42);
        $this->insert_batch((int) $other->id, (int) $course->id);

        $contextlist = $this->make_contextlist($user, $context);
        provider::export_user_data($contextlist);

        $writer = writer::with_context($context);
        $exported = $writer->get_data(
            [get_string('privacy:path:batches', 'local_coursectrl')]
        );

        $this->assertNotEmpty($exported);
        $this->assertObjectHasProperty('batches', $exported);

        $uids = array_column((array) $exported->batches, 'userid');
        foreach ($uids as $uid) {
            $this->assertEquals((int) $user->id, (int) $uid);
        }
    }

    /**
     * Preset rows for the user are exported under the presets path.
     * @covers \local_coursectrl\privacy\provider
     */
    public function test_export_user_data_exports_presets(): void {
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $user    = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);

        $this->insert_preset((int) $user->id, (int) $course->id);

        $contextlist = $this->make_contextlist($user, $context);
        provider::export_user_data($contextlist);

        $writer   = writer::with_context($context);
        $exported = $writer->get_data(
            [get_string('privacy:path:presets', 'local_coursectrl')]
        );

        $this->assertNotEmpty($exported);
        $this->assertObjectHasProperty('presets', $exported);
    }

    /**
     * User preferences are exported even when there is no course context.
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
        $this->insert_preset((int) $user->id, (int) $course1->id);
        $this->insert_report((int) $user->id, (int) $course1->id);

        // Data in second course must survive.
        $batchid2 = $this->insert_batch((int) $user->id, (int) $course2->id);

        $context = \context_course::instance($course1->id);
        provider::delete_data_for_all_users_in_context($context);

        $this->assertFalse($DB->record_exists('local_coursectrl_batch', ['courseid' => $course1->id]));
        $this->assertFalse($DB->record_exists('local_coursectrl_batch_item', ['batchid' => $batchid1]));
        $this->assertFalse($DB->record_exists('local_coursectrl_snapshot', ['batchid' => $batchid1]));
        $this->assertFalse($DB->record_exists('local_coursectrl_preset', ['courseid' => $course1->id]));
        $this->assertFalse($DB->record_exists('local_coursectrl_report', ['courseid' => $course1->id]));

        // Course 2 batch must still exist.
        $this->assertTrue($DB->record_exists('local_coursectrl_batch', ['id' => $batchid2]));
    }

    /**
     * Non-course contexts are safely ignored without touching any data.
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
        $this->insert_preset((int) $user1->id, (int) $course->id);
        $this->insert_report((int) $user1->id, (int) $course->id);

        $batchid2 = $this->insert_batch((int) $user2->id, (int) $course->id);

        $contextlist = $this->make_contextlist($user1, $context);
        provider::delete_data_for_user($contextlist);

        $this->assertFalse($DB->record_exists('local_coursectrl_batch', ['userid' => $user1->id]));
        $this->assertFalse($DB->record_exists('local_coursectrl_batch_item', ['batchid' => $batchid1]));
        $this->assertFalse($DB->record_exists('local_coursectrl_snapshot', ['batchid' => $batchid1]));
        $this->assertFalse($DB->record_exists('local_coursectrl_preset', ['userid' => $user1->id]));
        $this->assertFalse($DB->record_exists('local_coursectrl_report', ['userid' => $user1->id]));

        // User 2 batch must survive.
        $this->assertTrue($DB->record_exists('local_coursectrl_batch', ['id' => $batchid2]));
    }

    /**
     * User preferences are removed when the user's data is deleted.
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
}
