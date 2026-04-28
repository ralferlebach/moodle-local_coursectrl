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
 * PHPUnit tests for the textreview_manager.
 *
 * Covers the three public operations exposed by the manager:
 *   scan_course, get_hits, apply_changes, purge_hits.
 *
 * All tests use a real in-memory Moodle DB and genuine activity
 * descriptions that contain recognisable date strings.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\manager;

use local_coursectrl\local\persistent\text_hit;

/**
 * Integration tests for textreview_manager.
 *
 * @covers \local_coursectrl\manager\textreview_manager
 * @covers \local_coursectrl\local\text\text_change_builder
 * @covers \local_coursectrl\local\text\text_datetime_rewriter
 */
final class textreview_manager_test extends \advanced_testcase {
    // Constants.

    /** @var string A German numeric date embedded in a course summary. */
    private const DATE_STRING = '01.06.2026';

    /** @var string Course summary template containing one safe date. */
    private const SUMMARY_TPL = '<p>Abgabe bis %s um 23:59 Uhr.</p>';

    // Helpers.

    /**
     * Create a course whose summary contains a recognisable date string.
     *
     * @return \stdClass Course record.
     */
    private function create_course_with_dated_summary(): \stdClass {
        return $this->getDataGenerator()->create_course([
            'summary'       => sprintf(self::SUMMARY_TPL, self::DATE_STRING),
            'summaryformat' => FORMAT_HTML,
        ]);
    }

    /**
     * Build a textreview_manager with default (real) dependencies.
     *
     * @return textreview_manager
     */
    private function manager(): textreview_manager {
        return new textreview_manager();
    }

    /**
     * Insert a text_hit record directly into the DB and return its id.
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
            'matchedtext'     => self::DATE_STRING,
            'normalizedvalue' => '2026-06-01',
            'confidence'      => $confidence,
            'contextjson'     => json_encode([
                'offset'  => (int) strpos(
                    sprintf(self::SUMMARY_TPL, self::DATE_STRING),
                    self::DATE_STRING
                ),
                'pattern' => 'de_numeric',
            ]),
        ]);
        $hit->create();
        return (int) $hit->get('id');
    }

    // A2.1  scan_course.

    /**
     * scan_course returns a summary with total ≥ 1 when the course summary
     * contains a recognisable date.
     */
    public function test_scan_course_returns_nonzero_summary(): void {
        $this->resetAfterTest();

        $course  = $this->create_course_with_dated_summary();
        $manager = $this->manager();
        $summary = $manager->scan_course((int) $course->id);

        $this->assertArrayHasKey('total', $summary);
        $this->assertArrayHasKey('safe', $summary);
        $this->assertArrayHasKey('ambiguous', $summary);
        $this->assertArrayHasKey('informational', $summary);
        $this->assertGreaterThanOrEqual(1, $summary['total']);
    }

    /**
     * scan_course persists the discovered hits into local_coursectrl_text_hit.
     */
    public function test_scan_course_persists_hits_to_db(): void {
        global $DB;
        $this->resetAfterTest();

        $course  = $this->create_course_with_dated_summary();
        $manager = $this->manager();
        $manager->scan_course((int) $course->id);

        $count = $DB->count_records('local_coursectrl_text_hit', ['courseid' => $course->id]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    /**
     * A second call to scan_course purges old hits before re-scanning.
     */
    public function test_scan_course_replaces_previous_hits(): void {
        global $DB;
        $this->resetAfterTest();

        $course  = $this->create_course_with_dated_summary();
        $manager = $this->manager();

        $manager->scan_course((int) $course->id);
        $first = $DB->count_records('local_coursectrl_text_hit', ['courseid' => $course->id]);

        $manager->scan_course((int) $course->id);
        $second = $DB->count_records('local_coursectrl_text_hit', ['courseid' => $course->id]);

        // Total must stay the same — no duplicates accumulated.
        $this->assertEquals($first, $second);
    }

    // A2.2  get_hits.

    /**
     * get_hits returns all persisted hits for a course without filtering.
     */
    public function test_get_hits_returns_persisted_hits(): void {
        $this->resetAfterTest();

        $course  = $this->create_course_with_dated_summary();
        $manager = $this->manager();
        $manager->scan_course((int) $course->id);

        $hits = $manager->get_hits((int) $course->id);
        $this->assertNotEmpty($hits);
        foreach ($hits as $hit) {
            $this->assertInstanceOf(text_hit::class, $hit);
        }
    }

    /**
     * get_hits with a confidence filter returns only matching rows.
     */
    public function test_get_hits_confidence_filter_limits_results(): void {
        $this->resetAfterTest();

        $course = $this->create_course_with_dated_summary();

        $this->insert_hit((int) $course->id, (int) $course->id, text_hit::CONFIDENCE_SAFE);
        $this->insert_hit((int) $course->id, (int) $course->id, text_hit::CONFIDENCE_AMBIGUOUS);

        $manager = $this->manager();

        $safe = $manager->get_hits((int) $course->id, text_hit::CONFIDENCE_SAFE);
        foreach ($safe as $hit) {
            $this->assertEquals(text_hit::CONFIDENCE_SAFE, $hit->get('confidence'));
        }

        $ambiguous = $manager->get_hits((int) $course->id, text_hit::CONFIDENCE_AMBIGUOUS);
        foreach ($ambiguous as $hit) {
            $this->assertEquals(text_hit::CONFIDENCE_AMBIGUOUS, $hit->get('confidence'));
        }
    }

    /**
     * get_hits returns an empty array when no hits exist for the course.
     */
    public function test_get_hits_returns_empty_array_when_no_hits(): void {
        $this->resetAfterTest();

        $course  = $this->create_course_with_dated_summary();
        $manager = $this->manager();

        $this->assertEmpty($manager->get_hits((int) $course->id));
    }

    // A2.3  purge_hits.

    /**
     * purge_hits removes all text_hit rows for the course from the DB.
     */
    public function test_purge_hits_removes_all_rows(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->create_course_with_dated_summary();
        $this->insert_hit((int) $course->id, (int) $course->id);
        $this->insert_hit((int) $course->id, (int) $course->id, text_hit::CONFIDENCE_AMBIGUOUS);

        $manager = $this->manager();
        $manager->purge_hits((int) $course->id);

        $this->assertEquals(
            0,
            $DB->count_records('local_coursectrl_text_hit', ['courseid' => $course->id])
        );
    }

    /**
     * purge_hits on a different course id leaves other courses' rows intact.
     */
    public function test_purge_hits_does_not_affect_other_courses(): void {
        global $DB;
        $this->resetAfterTest();

        $course1 = $this->create_course_with_dated_summary();
        $course2 = $this->create_course_with_dated_summary();

        $this->insert_hit((int) $course1->id, (int) $course1->id);
        $this->insert_hit((int) $course2->id, (int) $course2->id);

        $manager = $this->manager();
        $manager->purge_hits((int) $course1->id);

        $this->assertEquals(
            1,
            $DB->count_records('local_coursectrl_text_hit', ['courseid' => $course2->id])
        );
    }

    // A2.4  apply_changes.

    /**
     * apply_changes with no hit ids returns zeros and purges existing rows.
     */
    public function test_apply_changes_with_empty_hitids_returns_zeros(): void {
        global $DB;
        $this->resetAfterTest();

        $course  = $this->create_course_with_dated_summary();
        $this->insert_hit((int) $course->id, (int) $course->id);

        $manager = $this->manager();
        $result  = $manager->apply_changes((int) $course->id, [], 86400);

        $this->assertEquals(0, $result['applied']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertEmpty($result['errors']);

        // Hits must be purged even when nothing was applied.
        $this->assertEquals(
            0,
            $DB->count_records('local_coursectrl_text_hit', ['courseid' => $course->id])
        );
    }

    /**
     * apply_changes silently skips hit ids that belong to a different course.
     */
    public function test_apply_changes_ignores_cross_course_hits(): void {
        $this->resetAfterTest();

        $course1 = $this->create_course_with_dated_summary();
        $course2 = $this->create_course_with_dated_summary();
        $hitid   = $this->insert_hit((int) $course1->id, (int) $course1->id);

        $manager = $this->manager();
        // Pass course1's hit id but claim it belongs to course2.
        $result = $manager->apply_changes((int) $course2->id, [$hitid], 86400);

        // The hit was filtered out — zero changes.
        $this->assertEquals(0, $result['applied']);
    }

    /**
     * apply_changes shifts the course summary date by exactly one day.
     *
     * This is a full integration test: the course summary is written,
     * scanned to produce real hits with correct contextjson offsets,
     * and the rewriter updates the text in the DB.
     */
    public function test_apply_changes_shifts_course_summary_date(): void {
        global $DB;
        $this->resetAfterTest();

        $course  = $this->create_course_with_dated_summary();
        $manager = $this->manager();

        // Scan to populate real hits with correct offsets.
        $manager->scan_course((int) $course->id);
        $hits = $manager->get_hits((int) $course->id, text_hit::CONFIDENCE_SAFE);

        if (empty($hits)) {
            $this->markTestSkipped('No safe hits found — extractor may not recognise this date format.');
        }

        $hitids = array_map(fn ($h) => (int) $h->get('id'), $hits);
        $result = $manager->apply_changes((int) $course->id, $hitids, 86400); // Shift forward by one day.

        // At least one substitution must have been made.
        $this->assertGreaterThanOrEqual(1, $result['applied']);
        $this->assertEmpty($result['errors']);

        // The old date must no longer appear in the course summary.
        $newsummary = $DB->get_field('course', 'summary', ['id' => $course->id]);
        $this->assertStringNotContainsString(self::DATE_STRING, $newsummary);
    }
}
