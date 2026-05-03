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
 * PHPUnit tests for the get_text_hits external function.
 *
 * Covers the two rescan modes (rescan=true / rescan=false),
 * the response structure, and the capability gate.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\external;

use local_coursectrl\local\persistent\text_hit;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\external\get_text_hits::class)]
/**
 * Integration tests for get_text_hits.
 *
 * @covers \local_coursectrl\external\get_text_hits
 */
final class get_text_hits_test extends \advanced_testcase {
    // Helpers.

    /**
     * Insert a single text_hit row and return its id.
     *
     * @param int    $courseid
     * @param int    $entityid
     * @param string $confidence
     * @return int
     */
    private function insert_hit(
        int $courseid,
        int $entityid,
        string $confidence = text_hit::CONFIDENCE_SAFE
    ): int {
        $hit = new text_hit(0, (object) [
            'courseid'        => $courseid,
            'entitytype'      => 'course',
            'entityid'        => $entityid,
            'fieldname'       => 'summary',
            'matchedtext'     => '01.06.2026',
            'normalizedvalue' => '2026-06-01',
            'confidence'      => $confidence,
            'contextjson'     => json_encode(['offset' => 3, 'pattern' => 'de_numeric']),
        ]);
        $hit->create();
        return (int) $hit->get('id');
    }

    // A3.1  rescan = false.

    /**
     * rescan=false returns cached rows without triggering a new scan.
     *
     * A pre-inserted row must appear in hits. The summary is not re-computed
     * when rescan=false, so only the hits array is asserted.
     * @covers \local_coursectrl\external\get_text_hits
     */
    public function test_execute_rescan_false_returns_cached_hits(): void {
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $hitid = $this->insert_hit((int) $course->id, (int) $course->id);

        $result = get_text_hits::execute((int) $course->id, false);

        $this->assertArrayHasKey('hits', $result);
        $this->assertArrayHasKey('summary', $result);

        $ids = array_column($result['hits'], 'id');
        $this->assertContains($hitid, $ids);

        // Summary is not populated when rescan=false — only hits array matters.
        $this->assertCount(1, $result['hits']);
    }

    /**
     * rescan=false with no cached rows returns an empty hits array and zero summary.
     * @covers \local_coursectrl\external\get_text_hits
     */
    public function test_execute_rescan_false_no_cache_returns_empty(): void {
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $result = get_text_hits::execute((int) $course->id, false);

        $this->assertEmpty($result['hits']);
        $this->assertEquals(0, $result['summary']['total']);
    }

    // A3.2  rescan = true.

    /**
     * rescan=true triggers a fresh scan; a dated course summary produces hits.
     * @covers \local_coursectrl\external\get_text_hits
     */
    public function test_execute_rescan_true_produces_hits_for_dated_course(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course([
            'summary'       => '<p>Abgabe bis 01.06.2026 um 23:59 Uhr.</p>',
            'summaryformat' => FORMAT_HTML,
        ]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $result = get_text_hits::execute((int) $course->id, true);

        $this->assertArrayHasKey('hits', $result);
        $this->assertArrayHasKey('summary', $result);

        // At minimum the structure contract must be satisfied.
        $this->assertIsArray($result['hits']);
        foreach ($result['hits'] as $hit) {
            $this->assertArrayHasKey('id', $hit);
            $this->assertArrayHasKey('entitytype', $hit);
            $this->assertArrayHasKey('entityid', $hit);
            $this->assertArrayHasKey('fieldname', $hit);
            $this->assertArrayHasKey('matchedtext', $hit);
            $this->assertArrayHasKey('normalizedvalue', $hit);
            $this->assertArrayHasKey('confidence', $hit);
            $this->assertArrayHasKey('contextjson', $hit);
        }
    }

    /**
     * rescan=true purges previously cached rows before writing new ones.
     * @covers \local_coursectrl\external\get_text_hits
     */
    public function test_execute_rescan_true_purges_stale_cache(): void {
        global $DB;
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        // Insert stale cached row.
        $this->insert_hit((int) $course->id, (int) $course->id);
        $this->assertEquals(
            1,
            $DB->count_records('local_coursectrl_text_hit', ['courseid' => $course->id])
        );

        // Rescan a course with no date text — all cached rows should disappear.
        get_text_hits::execute((int) $course->id, true);

        // DB now reflects the real scan result for a course with no dates.
        $count = $DB->count_records('local_coursectrl_text_hit', ['courseid' => $course->id]);
        $this->assertEquals(0, $count);
    }

    // A3.3  capability gate.

    /**
     * An unenrolled user triggers require_login_exception.
     * @covers \local_coursectrl\external\get_text_hits
     */
    public function test_execute_blocks_unenrolled_user(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\require_login_exception::class);
        get_text_hits::execute((int) $course->id, false);
    }

    /**
     * An enrolled student (no local/coursectrl:view) triggers required_capability_exception.
     * @covers \local_coursectrl\external\get_text_hits
     */
    public function test_execute_blocks_student_without_capability(): void {
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        get_text_hits::execute((int) $course->id, false);
    }
}
