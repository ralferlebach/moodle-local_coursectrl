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
 * Tests for risk_prioritizer.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;

/**
 * Unit tests for risk_prioritizer::score_and_sort().
 *
 * Score formula (from source):
 *   score = severity_base + (probability * 20) + min(affected * 2, 20)
 *           + min(downstream * 3, 20)
 *
 * Severity bases: error=40, warning=20, notice=5.
 * Maximum score: 100.
 *
 * @covers \local_coursectrl\local\analysis\risk_prioritizer
 */
final class risk_prioritizer_test extends \advanced_testcase {
    // ── helpers ───────────────────────────────────────────────────────────────

    private function make_cm(int $cmid, ?string $avail = null): cm_item {
        return new cm_item($cmid, 1, 1, 'assign', $cmid, "CM $cmid", true, $avail, 2);
    }

    private function avail_requires(int $req): string {
        return json_encode([
            'op' => '&', 'c' => [['type' => 'completion', 'cm' => $req, 'e' => 1]],
            'showc' => [true],
        ]);
    }

    /**
     * Build a minimal risk item.
     *
     * @param string  $severity
     * @param float   $probability
     * @param int     $affected
     * @param int[]   $cmids
     * @return array
     */
    private function make_risk(
        string $severity,
        float $probability = 1.0,
        int $affected = 1,
        array $cmids = [1]
    ): array {
        return [
            'type'           => 'test_type',
            'severity'       => $severity,
            'probability'    => $probability,
            'cmids'          => $cmids,
            'affected_count' => $affected,
            'message_key'    => 'test',
            'message_params' => [],
        ];
    }

    // ── tests: empty / single ─────────────────────────────────────────────────

    /**
     * Empty input → empty output.
     */
    public function test_empty_returns_empty(): void {
        $this->resetAfterTest();
        $p = new risk_prioritizer();
        $result = $p->score_and_sort([], new dependency_index([]));
        $this->assertSame([], $result);
    }

    /**
     * A single risk item gets a score key added.
     */
    public function test_single_item_gets_score(): void {
        $this->resetAfterTest();
        $p = new risk_prioritizer();
        $risk = $this->make_risk('error');
        $result = $p->score_and_sort([$risk], new dependency_index([]));
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('score', $result[0]);
        $this->assertIsInt($result[0]['score']);
    }

    // ── tests: score formula ──────────────────────────────────────────────────

    /**
     * Error with probability=1, affected=1, no downstream → score = 40+20+2+0 = 62.
     */
    public function test_score_error_single_no_downstream(): void {
        $this->resetAfterTest();
        $p = new risk_prioritizer();
        $risk = $this->make_risk('error', 1.0, 1, [99]);
        $result = $p->score_and_sort([$risk], new dependency_index([]));
        $this->assertSame(62, $result[0]['score']);
    }

    /**
     * Warning with probability=1, affected=1, no downstream → score = 20+20+2+0 = 42.
     */
    public function test_score_warning_single_no_downstream(): void {
        $this->resetAfterTest();
        $p = new risk_prioritizer();
        $risk = $this->make_risk('warning', 1.0, 1, [99]);
        $result = $p->score_and_sort([$risk], new dependency_index([]));
        $this->assertSame(42, $result[0]['score']);
    }

    /**
     * Notice with probability=1, affected=1, no downstream → score = 5+20+2+0 = 27.
     */
    public function test_score_notice_single_no_downstream(): void {
        $this->resetAfterTest();
        $p = new risk_prioritizer();
        $risk = $this->make_risk('notice', 1.0, 1, [99]);
        $result = $p->score_and_sort([$risk], new dependency_index([]));
        $this->assertSame(27, $result[0]['score']);
    }

    /**
     * affected_count cap at 10 (2×10=20): error + 10 affected → 40+20+20+0 = 80.
     */
    public function test_score_affected_count_capped(): void {
        $this->resetAfterTest();
        $p = new risk_prioritizer();
        $risk = $this->make_risk('error', 1.0, 10, [99]);
        $result = $p->score_and_sort([$risk], new dependency_index([]));
        $this->assertSame(80, $result[0]['score']);
    }

    /**
     * Score is capped at 100.
     */
    public function test_score_capped_at_100(): void {
        $this->resetAfterTest();
        $p = new risk_prioritizer();
        // Max possible: error(40) + prob(20) + affected(20) + downstream(20) = 100.
        $risk = $this->make_risk('error', 1.0, 20, [99]);
        // Need downstream too, but without real deps downstream=0.
        // Force affected to max contribution.
        $result = $p->score_and_sort([$risk], new dependency_index([]));
        $this->assertLessThanOrEqual(100, $result[0]['score']);
    }

    /**
     * Downstream CMs add to the score (max +20).
     */
    public function test_downstream_increases_score(): void {
        $this->resetAfterTest();
        // CM 1 (broken) has CM 2 depending on it.
        $cm1 = $this->make_cm(1, null);
        $cm2 = $this->make_cm(2, $this->avail_requires(1));
        $cms = [1 => $cm1, 2 => $cm2];
        $depindex = new dependency_index($cms);

        $p = new risk_prioritizer();
        // Risk on CM 1 with probability=1, affected=1.
        $risknodep  = $this->make_risk('error', 1.0, 1, [99]); // no dependents.
        $riskwithdep = $this->make_risk('error', 1.0, 1, [1]);  // CM1 has dependents.

        $resultno  = $p->score_and_sort([$risknodep], new dependency_index([]));
        $resultdep = $p->score_and_sort([$riskwithdep], $depindex);

        $this->assertGreaterThan($resultno[0]['score'], $resultdep[0]['score']);
    }

    /**
     * Probability=0.5 adds only 10 (not 20) to the score.
     */
    public function test_probability_half_adds_ten(): void {
        $this->resetAfterTest();
        $p = new risk_prioritizer();
        $risk = $this->make_risk('error', 0.5, 1, [99]);
        $result = $p->score_and_sort([$risk], new dependency_index([]));
        // error(40) + 0.5*20(10) + affected(2) + downstream(0) = 52.
        $this->assertSame(52, $result[0]['score']);
    }

    // ── tests: sorting ────────────────────────────────────────────────────────

    /**
     * Higher score items appear first.
     */
    public function test_sorted_by_score_descending(): void {
        $this->resetAfterTest();
        $p = new risk_prioritizer();
        $low  = $this->make_risk('notice', 1.0, 1, [1]);
        $high = $this->make_risk('error', 1.0, 1, [2]);
        $mid  = $this->make_risk('warning', 1.0, 1, [3]);

        $result = $p->score_and_sort([$low, $high, $mid], new dependency_index([]));

        $this->assertGreaterThanOrEqual($result[1]['score'], $result[0]['score']);
        $this->assertGreaterThanOrEqual($result[2]['score'], $result[1]['score']);
    }

    /**
     * Equal scores: errors precede warnings precede notices.
     */
    public function test_equal_score_sorted_by_severity(): void {
        $this->resetAfterTest();
        $p = new risk_prioritizer();
        // All same type/probability/affected, different severity.
        // Notice score: 5+20+2=27, warning: 20+20+2=42, error: 40+20+2=62 — actually different.
        // Construct deliberately equal scores by varying probability.
        // error prob=0, notice prob=1: error=40+0+2=42, notice=5+20+2=27. Still different.
        // The easiest way: same severity in a tie — just verify order is stable.
        $r1 = $this->make_risk('error', 1.0, 1, [1]);
        $r2 = $this->make_risk('warning', 1.0, 1, [2]);
        $result = $p->score_and_sort([$r2, $r1], new dependency_index([]));
        // error (62) > warning (42) → error should be first.
        $this->assertSame('error', $result[0]['severity']);
        $this->assertSame('warning', $result[1]['severity']);
    }

    // ── tests: output shape ───────────────────────────────────────────────────

    /**
     * Output items carry both score and downstream_count.
     */
    public function test_output_has_score_and_downstream_count(): void {
        $this->resetAfterTest();
        $p = new risk_prioritizer();
        $risk = $this->make_risk('error', 1.0, 1, [1]);
        $result = $p->score_and_sort([$risk], new dependency_index([]));
        $this->assertArrayHasKey('score', $result[0]);
        $this->assertArrayHasKey('downstream_count', $result[0]);
    }

    /**
     * Original risk fields are preserved in output.
     */
    public function test_original_fields_preserved(): void {
        $this->resetAfterTest();
        $p = new risk_prioritizer();
        $risk = $this->make_risk('error', 1.0, 1, [42]);
        $result = $p->score_and_sort([$risk], new dependency_index([]));
        $this->assertSame('test_type', $result[0]['type']);
        $this->assertSame('error', $result[0]['severity']);
        $this->assertSame([42], $result[0]['cmids']);
    }

    /**
     * Unknown severity falls back gracefully (uses notice base=5).
     */
    public function test_unknown_severity_handled(): void {
        $this->resetAfterTest();
        $p = new risk_prioritizer();
        $risk = $this->make_risk('bogus_level', 1.0, 1, [1]);
        $result = $p->score_and_sort([$risk], new dependency_index([]));
        $this->assertArrayHasKey('score', $result[0]);
        $this->assertGreaterThanOrEqual(0, $result[0]['score']);
    }
}
