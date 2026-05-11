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
 * Tests for the timeline_page renderable.
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

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\output\timeline_page::class)]
/**
 * Unit tests for timeline_page::export_for_template().
 *
 * @covers \local_coursectrl\output\timeline_page
 */
final class timeline_page_test extends \advanced_testcase {
    /**
     * Build a snapshot without dates (adapter data not available in unit tests).
     *
     * @return inventory_snapshot
     */
    private function build_snapshot(): inventory_snapshot {
        $course = new course_item(1, 'Demo Course', 'DEMO', '', 1, 1700000000, null, true);
        $sections = [
            10 => new section_item(10, 1, 0, 'General', '', 1, true),
        ];
        // CM with completionexpected in future.
        $cms = [
            100 => new cm_item(100, 1, 10, 'assign', 1, 'Task 1', true, null, 2, 1800000000),
            101 => new cm_item(101, 1, 10, 'quiz', 1, 'Quiz 1', true, null, 1, 1800100000),
        ];
        return new inventory_snapshot($course, $sections, $cms, []);
    }

    /**
     * The export must include course id, sesskey and URLs.
     * @covers \local_coursectrl\output\timeline_page
     */
    public function test_export_scalars(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new timeline_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertSame(1, $data['courseid']);
        $this->assertNotEmpty($data['sesskey']);
        $this->assertStringContainsString('index.php', $data['dashboardurl']);
        $this->assertStringContainsString('manage.php', $data['manageurl']);
        $this->assertStringContainsString('timeline.php', $data['timelineurl']);
    }

    /**
     * Default filters must be showpast=true, onlywithdeps=false.
     * @covers \local_coursectrl\output\timeline_page
     */
    public function test_default_filters(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new timeline_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['showpast']);
        $this->assertFalse($data['onlywithdeps']);
    }

    /**
     * Explicit filter values must be preserved.
     * @covers \local_coursectrl\output\timeline_page
     */
    public function test_explicit_filters(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new timeline_page($this->build_snapshot(), [
            'showpast' => false,
            'onlywithdeps' => true,
            'components' => ['mod_assign'],
        ]);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertFalse($data['showpast']);
        $this->assertTrue($data['onlywithdeps']);
    }

    /**
     * CMs with completionexpected must produce day groups.
     * @covers \local_coursectrl\output\timeline_page
     */
    public function test_days_contain_entries(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new timeline_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['hasdays']);
        $this->assertGreaterThan(0, $data['totalentries']);
    }

    /**
     * Entries must carry activity and edit URLs.
     * @covers \local_coursectrl\output\timeline_page
     */
    public function test_entry_urls(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new timeline_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertNotEmpty($data['days']);
        $firstday = $data['days'][0];
        $firstslot = $firstday['slots'][0];
        $firstentry = $firstslot['entries'][0];

        $this->assertStringContainsString('/mod/', $firstentry['activityurl']);
        $this->assertStringContainsString('modedit.php', $firstentry['editurl']);
        $this->assertStringContainsString('#cm-', $firstentry['dashboardanchor']);
    }

    /**
     * Context must expose the shift endpoint URL and the immediateapply flag.
     * @covers \local_coursectrl\output\timeline_page
     */
    public function test_export_exposes_shifturl_and_immediateapply(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new timeline_page($this->build_snapshot(), ['immediateapply' => true]);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertStringContainsString('shift.php', $data['shifturl']);
        $this->assertTrue($data['immediateapply']);
    }

    /**
     * Entries backed by adapter fields must be marked deletable.
     * @covers \local_coursectrl\output\timeline_page
     */
    public function test_entries_have_deletable_flag(): void {
        $this->resetAfterTest();
        global $PAGE;

        $page = new timeline_page($this->build_snapshot());
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertNotEmpty($data['days']);
        foreach ($data['days'] as $day) {
            foreach ($day['slots'] as $slot) {
                foreach ($slot['entries'] as $entry) {
                    // Every entry must carry a 'deletable' boolean.
                    $this->assertArrayHasKey('deletable', $entry);
                    $this->assertIsBool($entry['deletable']);
                }
            }
        }
    }

    /**
     * Empty snapshot must report hasdays=false.
     * @covers \local_coursectrl\output\timeline_page
     */
    public function test_empty_snapshot(): void {
        $this->resetAfterTest();
        global $PAGE;

        $course = new course_item(2, 'Empty', 'EMPTY', '', 1, 0, null, true);
        $snapshot = new inventory_snapshot($course, [], [], []);

        $page = new timeline_page($snapshot);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertFalse($data['hasdays']);
        $this->assertSame(0, $data['totalentries']);
        $this->assertSame(0, $data['totaldays']);
    }

    /**
     * Every timeline entry must export fieldkey (raw field name), source, and timestamp.
     *
     * These attributes feed the data-field / data-source / data-timestamp HTML attributes
     * used by timeline.js to build structured shift targets.
     *
     * @covers \local_coursectrl\output\timeline_page
     */
    public function test_entries_carry_fieldkey_source_timestamp(): void {
        $this->resetAfterTest();
        global $PAGE, $DB;

        // Create a real course with a forum with completionexpected so
        // The date_collector produces a cm-source entry.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $forum  = $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_instance([
            'course' => $course->id,
            'name'   => 'Forum X',
        ]);
        // Set completionexpected directly in course_modules.
        $expectedts = 1800000000;
        $DB->set_field('course_modules', 'completionexpected', $expectedts, ['id' => $forum->cmid]);

        $service  = new \local_coursectrl\local\inventory\inventory_service();
        $snapshot = $service->build_for_course((int) $course->id);
        $page     = new timeline_page($snapshot);
        $data     = $page->export_for_template($PAGE->get_renderer('core'));

        // Collect all entries across all days/slots.
        $entries = [];
        foreach ($data['days'] as $day) {
            foreach ($day['slots'] as $slot) {
                foreach ($slot['entries'] as $entry) {
                    $entries[] = $entry;
                }
            }
        }

        $this->assertNotEmpty($entries, 'Expected at least one timeline entry from completionexpected');

        foreach ($entries as $entry) {
            // Fieldkey must be present and non-empty.
            $this->assertArrayHasKey('fieldkey', $entry, 'Entry must have fieldkey');
            $this->assertNotEmpty($entry['fieldkey'], 'fieldkey must not be empty');

            // Field (the visible label) must differ from fieldkey for non-English strings,
            // Or at least be present.
            $this->assertArrayHasKey('field', $entry, 'Entry must have field (label)');

            // Source must be one of the three valid values.
            $this->assertArrayHasKey('source', $entry, 'Entry must have source');
            $this->assertContains(
                $entry['source'],
                ['adapter', 'cm', 'availability'],
                'Entry source must be adapter, cm, or availability'
            );

            // Timestamp must be integer matching the slot timekey.
            $this->assertArrayHasKey('timestamp', $entry, 'Entry must have timestamp');
            $this->assertIsInt($entry['timestamp']);
        }

        // Find the completionexpected entry specifically.
        $cmentry = null;
        foreach ($entries as $entry) {
            if ($entry['fieldkey'] === 'completionexpected') {
                $cmentry = $entry;
                break;
            }
        }
        $this->assertNotNull($cmentry, 'Timeline must contain a completionexpected entry');
        $this->assertSame('cm', $cmentry['source'], 'completionexpected must have source=cm');
        $this->assertSame($expectedts, $cmentry['timestamp']);
        // Fieldkey must be the raw key, not the localized label.
        $this->assertSame('completionexpected', $cmentry['fieldkey']);
        // Field (label) should NOT be the raw key in a properly configured environment.
        // It may equal the raw key only when get_string returns the identifier fallback.
        $this->assertNotEmpty($cmentry['field']);
    }

    /**
     * Autoopen context for entry mode must produce a valid targets_json.
     *
     * @covers \local_coursectrl\output\timeline_page
     */
    public function test_autoopen_entry_produces_targets_json(): void {
        $this->resetAfterTest();
        global $PAGE, $DB;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $forum  = $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_instance([
            'course' => $course->id,
        ]);
        $expectedts = 1800000000;
        $DB->set_field('course_modules', 'completionexpected', $expectedts, ['id' => $forum->cmid]);

        $service  = new \local_coursectrl\local\inventory\inventory_service();
        $snapshot = $service->build_for_course((int) $course->id);
        $filters  = [
            'autoopen_mode'  => 'entry',
            'autoopen_cmid'  => (int) $forum->cmid,
            'autoopen_field' => 'completionexpected',
        ];
        $page = new timeline_page($snapshot, $filters);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['autoopen'], 'autoopen must be true when autoopen_mode is set');
        $this->assertArrayHasKey('autoopen_targets_json', $data);

        $targets = json_decode($data['autoopen_targets_json'], true);
        $this->assertIsArray($targets);
        $this->assertCount(1, $targets, 'Entry autoopen must produce exactly one target');
        $this->assertSame((int) $forum->cmid, $targets[0]['cmid']);
        $this->assertSame('completionexpected', $targets[0]['field']);
        $this->assertSame('cm', $targets[0]['source']);
    }
}
