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
 * Tests for risk_assessment_runner.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;

/**
 * Integration tests for risk_assessment_runner::run().
 *
 * These tests use real sub-components (dead_end_detector, escape_path_checker,
 * risk_prioritizer, consistency_runner) but inject them via the constructor to
 * keep test-injected overrides clean.
 *
 * DB tests (persistence) use resetAfterTest.
 *
 * @covers \local_coursectrl\local\analysis\risk_assessment_runner
 */
final class risk_assessment_runner_test extends \advanced_testcase {
    /** @var int Fake course id for persistence tests. */
    private const COURSE_ID = 9999;

    // ── helpers ───────────────────────────────────────────────────────────────

    private function make_cm(
        int $cmid,
        bool $visible = true,
        ?string $avail = null,
        int $completion = 2
    ): cm_item {
        return new cm_item(
            $cmid,
            self::COURSE_ID,
            1,
            'assign',
            $cmid,
            "CM $cmid",
            $visible,
            $avail,
            $completion
        );
    }

    private function avail_requires(int $req): string {
        return json_encode([
            'op' => '&', 'c' => [['type' => 'completion', 'cm' => $req, 'e' => 1]],
            'showc' => [true],
        ]);
    }

    /**
     * Build a runner with real sub-components and a small max-depth.
     *
     * @param int $maxdepth
     * @return risk_assessment_runner
     */
    private function make_runner(int $maxdepth = 10): risk_assessment_runner {
        return new risk_assessment_runner(
            new dead_end_detector($maxdepth),
            new escape_path_checker(),
            new risk_prioritizer(),
            new consistency_runner()
        );
    }

    // ── tests: basic output shape ─────────────────────────────────────────────

    /**
     * Empty CMs produce an empty result and an empty persisted state.
     */
    public function test_empty_cms_returns_empty(): void {
        $this->resetAfterTest();
        $runner = $this->make_runner();
        $result = $runner->run([], new dependency_index([]), [], self::COURSE_ID);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Each returned item has the minimum required keys.
     */
    public function test_output_items_have_required_keys(): void {
        $this->resetAfterTest();
        // A simple cycle so there is at least one finding.
        $cma = $this->make_cm(1, true, $this->avail_requires(2));
        $cmb = $this->make_cm(2, true, $this->avail_requires(1));
        $cms = [1 => $cma, 2 => $cmb];
        $depindex = new dependency_index($cms);

        $runner = $this->make_runner();
        $result = $runner->run($cms, $depindex, [], self::COURSE_ID);

        $this->assertNotEmpty($result);
        foreach ($result as $item) {
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('severity', $item);
            $this->assertArrayHasKey('cmids', $item);
            $this->assertArrayHasKey('score', $item);
        }
    }

    // ── tests: dead-end detection in pipeline ─────────────────────────────────

    /**
     * A 2-node cycle produces a circular_dep_transitive finding in the output.
     */
    public function test_cycle_produces_circular_finding(): void {
        $this->resetAfterTest();
        $cma = $this->make_cm(1, true, $this->avail_requires(2));
        $cmb = $this->make_cm(2, true, $this->avail_requires(1));
        $cms = [1 => $cma, 2 => $cmb];
        $depindex = new dependency_index($cms);

        $runner = $this->make_runner();
        $result = $runner->run($cms, $depindex, [], self::COURSE_ID);

        $types = array_column($result, 'type');
        $this->assertContains('circular_dep_transitive', $types);
    }

    /**
     * Dependency on a hidden CM → dep_on_hidden in output.
     */
    public function test_dep_on_hidden_in_output(): void {
        $this->resetAfterTest();
        $cma = $this->make_cm(1, false, null);  // hidden
        $cmb = $this->make_cm(2, true, $this->avail_requires(1));
        $cms = [1 => $cma, 2 => $cmb];
        $depindex = new dependency_index($cms);

        $runner = $this->make_runner();
        $result = $runner->run($cms, $depindex, [], self::COURSE_ID);

        $types = array_column($result, 'type');
        $this->assertContains('dep_on_hidden', $types);
    }

    // ── tests: consistency_runner merge ───────────────────────────────────────

    /**
     * Temporal conflicts from consistency_runner appear in the merged output.
     */
    public function test_consistency_warnings_merged_into_output(): void {
        $this->resetAfterTest();
        // Use a quiz CM so timeopen/timeclose R3 rule applies.
        $quizcm = new \local_coursectrl\local\entity\cm_item(
            10,
            self::COURSE_ID,
            1,
            'quiz',
            10,
            'CM 10',
            true,
            null,
            2
        );
        $cms = [10 => $quizcm];
        $depindex = new dependency_index($cms);
        $t1 = 1748736000; // 2026-06-01
        $t2 = 1749340800; // 2026-06-08
        $datesbycm = [
            10 => [
                ['cmid' => 10, 'field' => 'timeopen', 'fieldlabel' => 'timeopen',
                 'timestamp' => $t2, 'source' => 'adapter'],
                ['cmid' => 10, 'field' => 'timeclose', 'fieldlabel' => 'timeclose',
                 'timestamp' => $t1, 'source' => 'adapter'],
            ],
        ];

        $runner = $this->make_runner();
        $result = $runner->run($cms, $depindex, $datesbycm, self::COURSE_ID);

        $types = array_column($result, 'type');
        $this->assertContains('temporal_conflict', $types);
    }

    // ── tests: output ordering ────────────────────────────────────────────────

    /**
     * Items are sorted by score descending.
     */
    public function test_output_sorted_by_score_descending(): void {
        $this->resetAfterTest();
        // Cycle + hidden dep → at least two findings with different scores.
        $cma = $this->make_cm(1, false, null);            // hidden
        $cmb = $this->make_cm(2, true, $this->avail_requires(1)); // dep on hidden
        $cmc = $this->make_cm(3, true, $this->avail_requires(4));
        $cmd = $this->make_cm(4, true, $this->avail_requires(3)); // cycle C↔D
        $cms = [1 => $cma, 2 => $cmb, 3 => $cmc, 4 => $cmd];
        $depindex = new dependency_index($cms);

        $runner = $this->make_runner();
        $result = $runner->run($cms, $depindex, [], self::COURSE_ID);

        for ($i = 1; $i < count($result); $i++) {
            $this->assertGreaterThanOrEqual(
                $result[$i]['score'],
                $result[$i - 1]['score'],
                "Item $i should have score <= item " . ($i - 1)
            );
        }
    }

    // ── tests: persistence ────────────────────────────────────────────────────

    /**
     * run() persists results; load_last() returns them.
     */
    public function test_persist_and_load_last(): void {
        $this->resetAfterTest();
        $cma = $this->make_cm(1, true, $this->avail_requires(2));
        $cmb = $this->make_cm(2, true, $this->avail_requires(1));
        $cms = [1 => $cma, 2 => $cmb];
        $depindex = new dependency_index($cms);

        $runner = $this->make_runner();
        $result = $runner->run($cms, $depindex, [], self::COURSE_ID);

        $loaded = risk_assessment_runner::load_last(self::COURSE_ID);
        $this->assertCount(count($result), $loaded);
        $this->assertSame(
            array_column($result, 'type'),
            array_column($loaded, 'type')
        );
    }

    /**
     * A second run() replaces the prior persisted results (no accumulation).
     */
    public function test_second_run_replaces_first(): void {
        $this->resetAfterTest();
        $cma = $this->make_cm(1, true, $this->avail_requires(2));
        $cmb = $this->make_cm(2, true, $this->avail_requires(1));
        $cms = [1 => $cma, 2 => $cmb];
        $depindex = new dependency_index($cms);

        $runner = $this->make_runner();
        $runner->run($cms, $depindex, [], self::COURSE_ID);
        $firstcount = count(risk_assessment_runner::load_last(self::COURSE_ID));

        // Second run with empty course.
        $runner->run([], new dependency_index([]), [], self::COURSE_ID);
        $secondcount = count(risk_assessment_runner::load_last(self::COURSE_ID));

        $this->assertSame(0, $secondcount);
        $this->assertGreaterThan(0, $firstcount);
    }

    /**
     * load_last() returns [] when no assessment has been run.
     */
    public function test_load_last_empty_before_first_run(): void {
        $this->resetAfterTest();
        $result = risk_assessment_runner::load_last(self::COURSE_ID);
        $this->assertSame([], $result);
    }

    /**
     * last_run_time() returns 0 before any run and a positive timestamp after.
     */
    public function test_last_run_time(): void {
        $this->resetAfterTest();
        $before = risk_assessment_runner::last_run_time(self::COURSE_ID);
        $this->assertSame(0, $before);

        $cma = $this->make_cm(1, true, $this->avail_requires(2));
        $cmb = $this->make_cm(2, true, $this->avail_requires(1));
        $cms = [1 => $cma, 2 => $cmb];
        $runner = $this->make_runner();
        $runner->run($cms, new dependency_index($cms), [], self::COURSE_ID);

        $after = risk_assessment_runner::last_run_time(self::COURSE_ID);
        $this->assertGreaterThan(0, $after);
    }
}
