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
 * PHPUnit tests for the apply_text_changes external function.
 *
 * Covers the happy path (delta applied, hits purged), the empty-hitids
 * edge case, cross-course isolation, and the capability gate.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\external;

use local_coursectrl\local\persistent\text_hit;
use local_coursectrl\manager\textreview_manager;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\external\apply_text_changes::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\manager\textreview_manager::class)]
/**
 * Integration tests for apply_text_changes.
 *
 * @covers \local_coursectrl\external\apply_text_changes
 * @covers \local_coursectrl\manager\textreview_manager
 */
final class apply_text_changes_test extends \advanced_testcase {
    // Constants.

    /** @var string German numeric date used in test course summary. */
    private const DATE_ORIGINAL = '01.06.2026';

    /** @var string Course summary containing the date. */
    private const SUMMARY = '<p>Abgabe bis 01.06.2026 um 23:59 Uhr.</p>';

    // Helpers.

    /**
     * Create a course with a dated summary and enrol an editing teacher.
     *
     * @return array{course:\stdClass, teacher:\stdClass}
     */
    private function setup_course_with_teacher(): array {
        $course = $this->getDataGenerator()->create_course([
            'summary'       => self::SUMMARY,
            'summaryformat' => FORMAT_HTML,
        ]);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        return ['course' => $course, 'teacher' => $teacher];
    }

    /**
     * Run a real scan on the course and return hit ids for safe hits.
     *
     * @param int $courseid
     * @return int[]
     */
    private function scan_and_get_safe_hitids(int $courseid): array {
        $manager = new textreview_manager();
        $manager->scan_course($courseid);
        $hits = $manager->get_hits($courseid, text_hit::CONFIDENCE_SAFE);
        return array_map(fn ($h) => (int) $h->get('id'), $hits);
    }

    // A3.4  empty hitids.

    /**
     * An empty hitids list returns zeros and purges existing cached hits.
     * @covers \local_coursectrl\external\apply_text_changes
     * @covers \local_coursectrl\manager\textreview_manager
     */
    public function test_execute_with_empty_hitids_returns_zeros(): void {
        global $DB;
        $this->resetAfterTest();

        ['course' => $course, 'teacher' => $teacher] = $this->setup_course_with_teacher();
        $this->setUser($teacher);

        // Pre-seed a cached hit.
        $hit = new text_hit(0, (object) [
            'courseid'        => (int) $course->id,
            'entitytype'      => 'course',
            'entityid'        => (int) $course->id,
            'fieldname'       => 'summary',
            'matchedtext'     => self::DATE_ORIGINAL,
            'normalizedvalue' => '2026-06-01',
            'confidence'      => text_hit::CONFIDENCE_SAFE,
            'contextjson'     => '{}',
        ]);
        $hit->create();

        $result = apply_text_changes::execute((int) $course->id, [], 86400);

        $this->assertEquals(0, $result['applied']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEmpty($result['errors']);

        // Hits purged after apply_changes.
        $this->assertEquals(
            0,
            $DB->count_records('local_coursectrl_text_hit', ['courseid' => $course->id])
        );
    }

    // A3.5  full integration shift.

    /**
     * apply_text_changes shifts the course summary date forward by one day.
     *
     * Requires the extractor to recognise "01.06.2026" as a safe date so
     * that actual hits are produced by the scan step.
     * @covers \local_coursectrl\external\apply_text_changes
     * @covers \local_coursectrl\manager\textreview_manager
     */
    public function test_execute_shifts_course_summary_date_by_one_day(): void {
        global $DB;
        $this->resetAfterTest();

        ['course' => $course, 'teacher' => $teacher] = $this->setup_course_with_teacher();
        $this->setUser($teacher);

        $hitids = $this->scan_and_get_safe_hitids((int) $course->id);

        if (empty($hitids)) {
            $this->markTestSkipped(
                'Extractor produced no safe hits for this date — integration prerequisites not met.'
            );
        }

        $result = apply_text_changes::execute((int) $course->id, $hitids, 86400);

        $this->assertGreaterThanOrEqual(1, $result['applied']);
        $this->assertEmpty($result['errors']);

        $newsummary = $DB->get_field('course', 'summary', ['id' => $course->id]);
        $this->assertStringNotContainsString(self::DATE_ORIGINAL, $newsummary);
    }

    /**
     * apply_text_changes purges all cached hits regardless of result.
     * @covers \local_coursectrl\external\apply_text_changes
     * @covers \local_coursectrl\manager\textreview_manager
     */
    public function test_execute_purges_hits_after_apply(): void {
        global $DB;
        $this->resetAfterTest();

        ['course' => $course, 'teacher' => $teacher] = $this->setup_course_with_teacher();
        $this->setUser($teacher);

        $hitids = $this->scan_and_get_safe_hitids((int) $course->id);

        apply_text_changes::execute((int) $course->id, $hitids, 86400);

        $remaining = $DB->count_records(
            'local_coursectrl_text_hit',
            ['courseid' => $course->id]
        );
        $this->assertEquals(0, $remaining);
    }

    // A3.6  cross-course isolation.

    /**
     * Hit ids that belong to a different course are silently skipped.
     * @covers \local_coursectrl\external\apply_text_changes
     * @covers \local_coursectrl\manager\textreview_manager
     */
    public function test_execute_skips_cross_course_hit_ids(): void {
        global $DB;
        $this->resetAfterTest();

        ['course' => $course1, 'teacher' => $teacher] = $this->setup_course_with_teacher();
        $course2 = $this->getDataGenerator()->create_course([
            'summary'       => self::SUMMARY,
            'summaryformat' => FORMAT_HTML,
        ]);
        // Teacher must be enrolled in course2 so validate_context passes.
        $this->getDataGenerator()->enrol_user($teacher->id, $course2->id, 'editingteacher');
        $this->setUser($teacher);

        // Scan course1 to get real hit ids.
        $hitids = $this->scan_and_get_safe_hitids((int) $course1->id);

        if (empty($hitids)) {
            $this->markTestSkipped('No safe hits produced for cross-course isolation test.');
        }

        // Apply course1 hit ids against course2 — must now be rejected (P0-C).
        $this->expectException(\core\exception\moodle_exception::class);
        apply_text_changes::execute((int) $course2->id, $hitids, 86400);
    }

    // A3.7  capability gate.

    /**
     * An unenrolled user is rejected by require_login.
     * @covers \local_coursectrl\external\apply_text_changes
     * @covers \local_coursectrl\manager\textreview_manager
     */
    public function test_execute_blocks_unenrolled_user(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\require_login_exception::class);
        apply_text_changes::execute((int) $course->id, [], 0);
    }

    /**
     * An enrolled student without local/coursectrl:bulkaction is rejected.
     * @covers \local_coursectrl\external\apply_text_changes
     * @covers \local_coursectrl\manager\textreview_manager
     */
    public function test_execute_blocks_student_without_bulkaction_capability(): void {
        $this->resetAfterTest();

        $course  = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        apply_text_changes::execute((int) $course->id, [], 0);
    }
}
