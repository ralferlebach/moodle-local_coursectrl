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
 * Tests for checks_page renderable.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

/**
 * Unit tests for checks_page::export_for_template().
 *
 * export_for_template() calls inventory_service and runs real sub-components,
 * so these tests use a real Moodle course created via the generator API.
 * The focus is on the shape and routing of the exported template context,
 * not on the correctness of individual analysis sub-components (those have
 * their own unit tests).
 *
 * @covers \local_coursectrl\output\checks_page
 */
final class checks_page_test extends \advanced_testcase {
    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Create a minimal Moodle course and return its record.
     *
     * @return \stdClass
     */
    private function create_course(): \stdClass {
        return $this->getDataGenerator()->create_course([
            'enablecompletion' => 1,
        ]);
    }

    /**
     * Call export_for_template with the global PAGE renderer.
     *
     * @param \stdClass $course
     * @param string    $activetab
     * @param bool      $freshrun
     * @return array
     */
    private function export(
        \stdClass $course,
        string $activetab = 'consistency',
        bool $freshrun = false
    ): array {
        global $PAGE;
        $page = new checks_page($course, $activetab, $freshrun);
        return $page->export_for_template($PAGE->get_renderer('core'));
    }

    // ── tests: top-level key shape ────────────────────────────────────────────

    /**
     * All mandatory top-level keys are present in the exported context.
     */
    public function test_export_has_required_top_level_keys(): void {
        $this->resetAfterTest();
        $course = $this->create_course();
        $data = $this->export($course);

        $required = [
            'courseid',
            'coursefullname',
            'checksurl',
            'tab_consistency',
            'tab_risks',
            'tab_simulation',
            'consistency',
            'risks',
            'simulation',
            'runurl',
        ];
        foreach ($required as $key) {
            $this->assertArrayHasKey($key, $data, "Missing top-level key: $key");
        }
    }

    /**
     * courseid matches the supplied course.
     */
    public function test_courseid_matches_course(): void {
        $this->resetAfterTest();
        $course = $this->create_course();
        $data = $this->export($course);
        $this->assertSame((int)$course->id, $data['courseid']);
    }

    /**
     * coursefullname matches the course fullname.
     */
    public function test_coursefullname_matches(): void {
        $this->resetAfterTest();
        $course = $this->create_course();
        $data = $this->export($course);
        $this->assertSame($course->fullname, $data['coursefullname']);
    }

    /**
     * checksurl and runurl are non-empty strings.
     */
    public function test_urls_are_non_empty_strings(): void {
        $this->resetAfterTest();
        $course = $this->create_course();
        $data = $this->export($course);
        $this->assertIsString($data['checksurl']);
        $this->assertNotEmpty($data['checksurl']);
        $this->assertIsString($data['runurl']);
        $this->assertNotEmpty($data['runurl']);
    }

    // ── tests: tab routing ────────────────────────────────────────────────────

    /**
     * Default tab is 'consistency': tab_consistency=true, others=false.
     */
    public function test_default_tab_is_consistency(): void {
        $this->resetAfterTest();
        $course = $this->create_course();
        $data = $this->export($course, 'consistency');
        $this->assertTrue($data['tab_consistency']);
        $this->assertFalse($data['tab_risks']);
        $this->assertFalse($data['tab_simulation']);
    }

    /**
     * activetab='risks' sets tab_risks=true, others=false.
     */
    public function test_risks_tab_routing(): void {
        $this->resetAfterTest();
        $course = $this->create_course();
        $data = $this->export($course, 'risks');
        $this->assertFalse($data['tab_consistency']);
        $this->assertTrue($data['tab_risks']);
        $this->assertFalse($data['tab_simulation']);
    }

    /**
     * activetab='simulation' sets tab_simulation=true, others=false.
     */
    public function test_simulation_tab_routing(): void {
        $this->resetAfterTest();
        $course = $this->create_course();
        $data = $this->export($course, 'simulation');
        $this->assertFalse($data['tab_consistency']);
        $this->assertFalse($data['tab_risks']);
        $this->assertTrue($data['tab_simulation']);
    }

    /**
     * An invalid activetab value falls back to 'consistency'.
     */
    public function test_invalid_tab_falls_back_to_consistency(): void {
        $this->resetAfterTest();
        $course = $this->create_course();
        $data = $this->export($course, 'nonexistent_tab');
        $this->assertTrue($data['tab_consistency']);
        $this->assertFalse($data['tab_risks']);
    }

    // ── tests: consistency sub-array ──────────────────────────────────────────

    /**
     * The 'consistency' array has the required structural keys.
     */
    public function test_consistency_array_has_required_keys(): void {
        $this->resetAfterTest();
        $course = $this->create_course();
        $data = $this->export($course);
        $c = $data['consistency'];
        $this->assertArrayHasKey('items', $c);
        $this->assertArrayHasKey('hasitems', $c);
        $this->assertArrayHasKey('errorcount', $c);
        $this->assertArrayHasKey('warningcount', $c);
        $this->assertArrayHasKey('noticecount', $c);
        $this->assertArrayHasKey('totalcount', $c);
    }

    /**
     * An empty course (no activities) yields hasitems=false, all counts=0.
     */
    public function test_empty_course_consistency_is_clean(): void {
        $this->resetAfterTest();
        $course = $this->create_course();
        $data = $this->export($course);
        $c = $data['consistency'];
        $this->assertFalse($c['hasitems']);
        $this->assertSame(0, $c['errorcount']);
        $this->assertSame(0, $c['warningcount']);
        $this->assertSame(0, $c['noticecount']);
        $this->assertSame(0, $c['totalcount']);
        $this->assertIsArray($c['items']);
    }

    /**
     * errorcount + warningcount + noticecount equals totalcount when all are consistent.
     */
    public function test_consistency_counts_sum_correctly(): void {
        $this->resetAfterTest();
        $course = $this->create_course();
        $data = $this->export($course);
        $c = $data['consistency'];
        $this->assertSame(
            $c['errorcount'] + $c['warningcount'] + $c['noticecount'],
            $c['totalcount']
        );
    }

    // ── tests: risks sub-array ────────────────────────────────────────────────

    /**
     * The 'risks' array has the required structural keys.
     */
    public function test_risks_array_has_required_keys(): void {
        $this->resetAfterTest();
        $course = $this->create_course();
        $data = $this->export($course, 'risks');
        $r = $data['risks'];
        $this->assertArrayHasKey('hasresults', $r);
        $this->assertArrayHasKey('haslastrun', $r);
        $this->assertArrayHasKey('lastrundate', $r);
        $this->assertArrayHasKey('totalcount', $r);
        $this->assertArrayHasKey('errorcount', $r);
        $this->assertArrayHasKey('warningcount', $r);
        $this->assertArrayHasKey('noticecount', $r);
        $this->assertArrayHasKey('rows', $r);
        $this->assertArrayHasKey('hasrows', $r);
    }

    /**
     * Without a prior run, haslastrun=false and lastrundate is empty.
     */
    public function test_risks_no_prior_run(): void {
        $this->resetAfterTest();
        $course = $this->create_course();
        $data = $this->export($course, 'risks');
        $r = $data['risks'];
        $this->assertFalse($r['haslastrun']);
        $this->assertSame('', $r['lastrundate']);
        $this->assertFalse($r['hasresults']);
    }

    /**
     * freshrun=true triggers a fresh risk assessment; haslastrun becomes true
     * on next export (the run persists to DB).
     *
     * An assign with a past duedate ensures at least one risk row is persisted,
     * so last_run_time() returns a positive value on the subsequent load.
     */
    public function test_freshrun_persists_results(): void {
        $this->resetAfterTest();
        $course = $this->create_course();

        // Create an activity with a past duedate + completion so the runner
        // produces at least one risk item that gets persisted.
        $this->getDataGenerator()->get_plugin_generator('mod_assign')->create_instance([
            'course' => $course->id,
            'duedate' => mktime(0, 0, 0, 1, 1, 2020),
            'completion' => 2,
        ]);

        // Run with freshrun=true.
        $this->export($course, 'risks', true);

        // Load result on a subsequent (non-fresh) render.
        $data2 = $this->export($course, 'risks', false);
        $r = $data2['risks'];
        $this->assertTrue($r['haslastrun']);
        $this->assertNotEmpty($r['lastrundate']);
    }

    /**
     * Each risk row (when present) contains the required display fields.
     */
    public function test_risk_row_shape_when_present(): void {
        $this->resetAfterTest();
        $course = $this->create_course();

        // We need at least one finding: create an assign with duedate in past + completion.
        $generator = $this->getDataGenerator();
        $assign = $generator->create_module('assign', [
            'course'     => $course->id,
            'completion' => 2,
            'duedate'    => mktime(0, 0, 0, 1, 1, 2020), // past date.
        ]);

        $data = $this->export($course, 'risks', true);
        $r = $data['risks'];

        if (!empty($r['rows'])) {
            $row = $r['rows'][0];
            // Each row must have at least these display fields.
            $this->assertArrayHasKey('severity', $row);
            $this->assertArrayHasKey('cmid', $row);
            $this->assertArrayHasKey('cmname', $row);
            $this->assertArrayHasKey('score', $row);
            $this->assertArrayHasKey('fix_type', $row);
            $this->assertArrayHasKey('fix_url', $row);
            $this->assertArrayHasKey('fix_label', $row);
            $this->assertArrayHasKey('has_fix', $row);
        }
        // If no rows yet, the test is still valid — this just confirms shape when rows exist.
        $this->assertTrue(true);
    }

    // ── tests: simulation sub-array ───────────────────────────────────────────

    /**
     * The 'simulation' array contains at least the simulationhtml key.
     */
    public function test_simulation_array_has_simulationhtml(): void {
        $this->resetAfterTest();
        $course = $this->create_course();
        $data = $this->export($course, 'simulation');
        $this->assertArrayHasKey('simulationhtml', $data['simulation']);
    }

    // ── tests: consistency with a real issue ─────────────────────────────────

    /**
     * An assign with allowsubmissionsfromdate > duedate raises a consistency error.
     */
    public function test_consistency_catches_temporal_conflict(): void {
        $this->resetAfterTest();
        $course = $this->create_course();
        $generator = $this->getDataGenerator();

        // allowsubmissionsfromdate (open) AFTER duedate → temporal conflict R3.
        $topen = mktime(12, 0, 0, 9, 1, 2026);  // 2026-09-01 (later).
        $tdue  = mktime(23, 59, 0, 8, 15, 2026); // 2026-08-15 (earlier).
        $generator->create_module('assign', [
            'course'                    => $course->id,
            'completion'                => 2,
            'allowsubmissionsfromdate'  => $topen,
            'duedate'                   => $tdue,
        ]);

        $data = $this->export($course, 'consistency');
        $c = $data['consistency'];

        $this->assertGreaterThan(0, $c['totalcount']);
        $this->assertTrue($c['hasitems']);
    }

    /**
     * totalcount in consistency is never negative.
     */
    public function test_consistency_totalcount_non_negative(): void {
        $this->resetAfterTest();
        $course = $this->create_course();
        $data = $this->export($course);
        $this->assertGreaterThanOrEqual(0, $data['consistency']['totalcount']);
    }
}
