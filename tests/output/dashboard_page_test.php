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
 * Tests for the dashboard_page renderable (Modell D).
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

use local_coursectrl\local\entity\cm_item;
use local_coursectrl\local\entity\course_item;
use local_coursectrl\local\entity\section_item;
use local_coursectrl\local\inventory\inventory_snapshot;

/**
 * Unit tests for dashboard_page::export_for_template() (Modell D cockpit layout).
 *
 * Tests that inspect the inventory section explicitly set dashboard_inventory
 * to 'show' so they do not depend on the PHPUnit runner having site:config
 * capability.
 *
 * @covers \local_coursectrl\output\dashboard_page
 */
final class dashboard_page_test extends \advanced_testcase {
    /**
     * Build a small in-memory snapshot for tests.
     *
     * @return inventory_snapshot
     */
    private function build_snapshot(): inventory_snapshot {
        $course = new course_item(
            1,
            'Demo Course',
            'DEMO',
            '',
            1,
            1700000000,
            null,
            true
        );
        $sections = [
            10 => new section_item(10, 1, 0, 'General', '', 1, true),
            11 => new section_item(11, 1, 1, null, 'Week 1', 1, true),
        ];
        $cms = [
            100 => new cm_item(100, 1, 10, 'label', 1, 'Welcome', true, null, 0),
            101 => new cm_item(101, 1, 11, 'assign', 1, 'Homework 1', true, '{\"op\":\"&\",\"c\":[]}', 2),
            102 => new cm_item(102, 1, 11, 'quiz', 1, 'Quiz 1', false, null, 1),
        ];
        return new inventory_snapshot($course, $sections, $cms, []);
    }

    /**
     * The exported context must carry all course-level scalar fields.
     */
    public function test_export_includes_course_scalars(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new dashboard_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertSame(1, $data['courseid']);
        $this->assertSame('Demo Course', $data['coursefullname']);
        $this->assertSame('DEMO', $data['courseshortname']);
        $this->assertSame(1700000000, $data['coursestartdate']);
        $this->assertNull($data['courseenddate']);
        $this->assertFalse($data['hasenddate']);
        $this->assertTrue($data['coursevisible']);
    }

    /**
     * Stat counters must reflect the snapshot collection sizes.
     * Inventory is forced visible so hassections can be asserted.
     */
    public function test_export_includes_counts(): void {
        $this->resetAfterTest();
        global $PAGE;

        set_config('dashboard_inventory', 'show', 'local_coursectrl');

        $page = new dashboard_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertSame(2, $data['sectioncount']);
        $this->assertSame(3, $data['cmcount']);
        $this->assertSame(0, $data['textcount']);
        $this->assertTrue($data['showinventory']);
        $this->assertTrue($data['hassections']);
    }

    /**
     * All Modell D cockpit keys must be present in the export.
     */
    public function test_export_has_cockpit_keys(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new dashboard_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $required = [
            'hasproblems', 'totalproblems',
            'errorcount', 'warningcount', 'noticecount',
            'haserrors', 'haswarnings', 'hasnotices',
            'errorrows', 'warningrows', 'noticerows',
            'haserrormore', 'haswarningmore', 'hasnoticemore',
            'checksurl', 'deepanalysisurl',
            'hasupcomingdates', 'upcomingdates',
            'hastexthits', 'texthitsscanned', 'texthits',
            'timelineurl', 'textreviewurl',
            'showinventory', 'isinventoryadmin',
        ];
        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $data, "Missing key: {$key}");
        }
    }

    /**
     * A clean snapshot with no warnings must report hasproblems=false.
     */
    public function test_clean_snapshot_has_no_problems(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new dashboard_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertFalse($data['hasproblems']);
        $this->assertSame(0, $data['totalproblems']);
        $this->assertSame(0, $data['errorcount']);
        $this->assertSame(0, $data['warningcount']);
        $this->assertSame(0, $data['noticecount']);
        $this->assertEmpty($data['errorrows']);
        $this->assertEmpty($data['warningrows']);
        $this->assertEmpty($data['noticerows']);
    }

    /**
     * Course modules must be grouped under the section they belong to.
     * dashboard_inventory is set to 'show' so sections are populated.
     */
    public function test_export_groups_cms_under_sections(): void {
        $this->resetAfterTest();
        global $PAGE;

        set_config('dashboard_inventory', 'show', 'local_coursectrl');

        $page = new dashboard_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertCount(2, $data['sections']);

        $general = $data['sections'][0];
        $this->assertSame(10, $general['id']);
        $this->assertSame('General', $general['name']);
        $this->assertTrue($general['hasname']);
        $this->assertSame(1, $general['cmcount']);
        $this->assertCount(1, $general['cms']);
        $this->assertSame('Welcome', $general['cms'][0]['name']);

        $week1 = $data['sections'][1];
        $this->assertSame(11, $week1['id']);
        $this->assertFalse($week1['hasname']);
        $this->assertSame(2, $week1['cmcount']);
    }

    /**
     * Per-CM flags (visible, completion, availability) must be exposed.
     * dashboard_inventory is set to 'show' so sections are populated.
     */
    public function test_export_exposes_cm_flags(): void {
        $this->resetAfterTest();
        global $PAGE;

        set_config('dashboard_inventory', 'show', 'local_coursectrl');

        $page = new dashboard_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $week1 = $data['sections'][1];
        $assign = $week1['cms'][0];
        $quiz   = $week1['cms'][1];

        $this->assertTrue($assign['visible']);
        $this->assertTrue($assign['hascompletion']);
        $this->assertTrue($assign['hasavailability']);

        $this->assertFalse($quiz['visible']);
        $this->assertTrue($quiz['hascompletion']);
        $this->assertFalse($quiz['hasavailability']);
    }

    /**
     * An empty snapshot must report hassections=false and hasproblems=false.
     */
    public function test_export_handles_empty_snapshot(): void {
        $this->resetAfterTest();
        global $PAGE;

        $course = new course_item(2, 'Empty', 'EMPTY', '', 1, 0, null, true);
        $snapshot = new inventory_snapshot($course, [], [], []);

        $page = new dashboard_page($snapshot);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertFalse($data['hassections']);
        $this->assertSame(0, $data['sectioncount']);
        $this->assertSame(0, $data['cmcount']);
        $this->assertFalse($data['hasproblems']);
    }

    /**
     * Action URLs must contain the correct courseid and target pages.
     */
    public function test_export_includes_action_urls(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new dashboard_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertStringContainsString('manage.php', $data['manageurl']);
        $this->assertStringContainsString('courseid=1', $data['manageurl']);
        $this->assertStringContainsString('checks.php', $data['checksurl']);
        $this->assertStringContainsString('checks.php', $data['deepanalysisurl']);
        $this->assertStringContainsString('tab=risks', $data['deepanalysisurl']);
        $this->assertStringContainsString('timeline.php', $data['timelineurl']);
        $this->assertStringContainsString('timeline.php', $data['textreviewurl']);
        $this->assertStringContainsString('tab=textreview', $data['textreviewurl']);
    }

    /**
     * A CM whose availability JSON references a non-existent cmid must
     * surface as a warning in the problem summary rows.
     */
    public function test_dangling_dep_appears_in_warning_rows(): void {
        $this->resetAfterTest();
        global $PAGE;

        $avail = json_encode([
            'op' => '&',
            'c' => [['type' => 'completion', 'cm' => 9999, 'e' => 1]],
            'show' => false,
        ]);
        $course = new course_item(1, 'Demo', 'DEMO', '', 1, 1700000000, null, true);
        $sections = [10 => new section_item(10, 1, 0, 'General', '', 1, true)];
        $cms = [200 => new cm_item(200, 1, 10, 'assign', 200, 'Restricted', true, $avail, 2)];
        $snapshot = new inventory_snapshot($course, $sections, $cms, []);

        $page = new dashboard_page($snapshot);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['hasproblems']);
        $this->assertGreaterThan(0, $data['totalproblems']);
        $this->assertGreaterThan(0, $data['warningcount']);
        $this->assertTrue($data['haswarnings']);
        $this->assertNotEmpty($data['warningrows']);

        $row = $data['warningrows'][0];
        $this->assertArrayHasKey('cmname', $row);
        $this->assertArrayHasKey('cmurl', $row);
        $this->assertArrayHasKey('message', $row);
        $this->assertSame(200, $row['cmid']);
        $this->assertSame('Restricted', $row['cmname']);
    }

    /**
     * A CM that depends on an activity with no completion tracking must
     * appear in the warning rows as impossible_dep.
     * The problem is verified via warningrows (not sections) so this test
     * does not depend on the inventory being visible.
     */
    public function test_impossible_dep_appears_in_warning_rows(): void {
        $this->resetAfterTest();
        global $PAGE;

        $avail = json_encode([
            'op' => '&',
            'c' => [['type' => 'completion', 'cm' => 300, 'e' => 1]],
            'show' => false,
        ]);
        $course = new course_item(1, 'Demo', 'DEMO', '', 1, 1700000000, null, true);
        $sections = [10 => new section_item(10, 1, 0, 'General', '', 1, true)];
        $cms = [
            300 => new cm_item(300, 1, 10, 'label', 300, 'Intro', true, null, 0),
            301 => new cm_item(301, 1, 10, 'assign', 301, 'Task', true, $avail, 2),
        ];
        $snapshot = new inventory_snapshot($course, $sections, $cms, []);

        $page = new dashboard_page($snapshot);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['hasproblems']);
        $this->assertGreaterThan(0, $data['warningcount']);
        $this->assertNotEmpty($data['warningrows']);

        // Verify the warning row refers to cmid 301 (the dependent activity).
        $cmids = array_column($data['warningrows'], 'cmid');
        $this->assertContains(301, $cmids, 'cmid 301 must appear in warningrows');
    }

    /**
     * Each distinct warning issue is counted individually in warningcount.
     * Two issues on two different CMs → warningcount = 2.
     */
    public function test_warning_count_is_per_issue(): void {
        $this->resetAfterTest();
        global $PAGE;

        $avail400 = json_encode([
            'op' => '&',
            'c' => [['type' => 'completion', 'cm' => 9998, 'e' => 1]],
            'show' => false,
        ]);
        $avail401 = json_encode([
            'op' => '&',
            'c' => [['type' => 'completion', 'cm' => 9999, 'e' => 1]],
            'show' => false,
        ]);
        $course = new course_item(1, 'Demo', 'D', '', 1, 0, null, true);
        $sections = [10 => new section_item(10, 1, 0, 'S', '', 1, true)];
        $cms = [
            400 => new cm_item(400, 1, 10, 'assign', 400, 'A', true, $avail400, 2),
            401 => new cm_item(401, 1, 10, 'assign', 401, 'B', true, $avail401, 2),
        ];
        $snapshot = new inventory_snapshot($course, $sections, $cms, []);

        $page = new dashboard_page($snapshot);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertSame(2, $data['warningcount']);
        $this->assertSame(2, $data['totalproblems']);
    }

    /**
     * When dashboard_inventory is set to 'hide', sections must be empty.
     */
    public function test_inventory_hidden_when_setting_is_hide(): void {
        $this->resetAfterTest();
        global $PAGE;

        set_config('dashboard_inventory', 'hide', 'local_coursectrl');

        $page = new dashboard_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertFalse($data['showinventory']);
        $this->assertFalse($data['hassections']);
        $this->assertEmpty($data['sections']);
    }

    /**
     * When dashboard_inventory is set to 'show', all users see the inventory.
     */
    public function test_inventory_visible_when_setting_is_show(): void {
        $this->resetAfterTest();
        global $PAGE;

        set_config('dashboard_inventory', 'show', 'local_coursectrl');

        $page = new dashboard_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['showinventory']);
        $this->assertTrue($data['hassections']);
    }

    /**
     * Upcoming dates list must be empty when no CM dates are in the future.
     */
    public function test_no_upcoming_dates_for_past_timestamps(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new dashboard_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertFalse($data['hasupcomingdates']);
        $this->assertEmpty($data['upcomingdates']);
    }

    /**
     * Text hits from DB must be reflected in the cockpit when present.
     */
    public function test_text_hits_from_db_appear_in_cockpit(): void {
        $this->resetAfterTest();
        global $DB, $PAGE;

        $DB->insert_record('local_coursectrl_text_hit', (object)[
            'courseid'       => 1,
            'entitytype'     => 'cm',
            'entityid'       => 100,
            'fieldname'      => 'intro',
            'matchedtext'    => '15. März',
            'normalizedvalue' => '2026-03-15',
            'confidence'     => 'safe',
            'contextjson'    => '{}',
            'timecreated'    => time(),
        ]);

        $page = new dashboard_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['texthitsscanned']);
        $this->assertTrue($data['hastexthits']);
        $this->assertCount(1, $data['texthits']);
        $this->assertSame('15. März', $data['texthits'][0]['matchedtext']);
        $this->assertSame('intro', $data['texthits'][0]['fieldname']);
    }
}
