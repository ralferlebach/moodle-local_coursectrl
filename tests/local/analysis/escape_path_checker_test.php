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
 * Tests for escape_path_checker.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;

/**
 * Unit tests for escape_path_checker::analyse().
 *
 * @covers \local_coursectrl\local\analysis\escape_path_checker
 */
final class escape_path_checker_test extends \advanced_testcase {
    // ── helpers ───────────────────────────────────────────────────────────────

    private function make_cm(
        int $cmid,
        bool $visible = true,
        ?string $avail = null,
        int $completion = 2
    ): cm_item {
        return new cm_item($cmid, 1, 1, 'assign', $cmid, "CM $cmid", $visible, $avail, $completion);
    }

    private function avail_requires(int $requirecmid): string {
        return json_encode([
            'op' => '&',
            'c'  => [['type' => 'completion', 'cm' => $requirecmid, 'e' => 1]],
            'showc' => [true],
        ]);
    }

    private function make_finding(string $type, array $cmids): array {
        return [
            'type'           => $type,
            'severity'       => 'error',
            'probability'    => 1.0,
            'cmids'          => $cmids,
            'related_cmids'  => [],
            'message_key'    => 'test',
            'message_params' => [],
            'affected_count' => count($cmids),
        ];
    }

    // ── tests: empty input ────────────────────────────────────────────────────

    /**
     * No findings → no results.
     */
    public function test_empty_findings_returns_empty(): void {
        $this->resetAfterTest();
        $checker = new escape_path_checker();
        $result = $checker->analyse([], [], new dependency_index([]));
        $this->assertSame([], $result);
    }

    // ── tests: circular_dep ───────────────────────────────────────────────────

    /**
     * A circular_dep finding gets escape_type='break_cycle', has_escape=true.
     */
    public function test_circular_dep_escape_break_cycle(): void {
        $this->resetAfterTest();
        $cma = $this->make_cm(1, true, $this->avail_requires(2));
        $cmb = $this->make_cm(2, true, $this->avail_requires(1));
        $cms = [1 => $cma, 2 => $cmb];
        $depindex = new dependency_index($cms);
        $finding = $this->make_finding('circular_dep', [1, 2]);

        $checker = new escape_path_checker();
        $results = $checker->analyse([$finding], $cms, $depindex);

        $this->assertCount(1, $results);
        $r = $results[0];
        $this->assertSame('circular_dep', $r['finding_type']);
        $this->assertTrue($r['has_escape']);
        $this->assertSame('break_cycle', $r['escape_type']);
    }

    /**
     * Breaking a cycle with a downstream CM → cascade_count includes that CM.
     */
    public function test_circular_dep_cascade_includes_downstream(): void {
        $this->resetAfterTest();
        // 1↔2 cycle, CM 3 depends on 1 (would be unblocked when cycle broken).
        $cma = $this->make_cm(1, true, $this->avail_requires(2));
        $cmb = $this->make_cm(2, true, $this->avail_requires(1));
        $cmc = $this->make_cm(3, true, $this->avail_requires(1));
        $cms = [1 => $cma, 2 => $cmb, 3 => $cmc];
        $depindex = new dependency_index($cms);
        $finding = $this->make_finding('circular_dep', [1, 2]);

        $checker = new escape_path_checker();
        $results = $checker->analyse([$finding], $cms, $depindex);

        $this->assertGreaterThanOrEqual(1, $results[0]['cascade_count']);
        $this->assertContains(3, $results[0]['cascade_cmids']);
    }

    // ── tests: dep_on_hidden ──────────────────────────────────────────────────

    /**
     * dep_on_hidden finding → escape_type='unhide_cm', has_escape=true.
     */
    public function test_dep_on_hidden_escape_unhide(): void {
        $this->resetAfterTest();
        $cma = $this->make_cm(1, false, null); // hidden.
        $cmb = $this->make_cm(2, true, $this->avail_requires(1));
        $cms = [1 => $cma, 2 => $cmb];
        $depindex = new dependency_index($cms);
        $finding = $this->make_finding('dep_on_hidden', [2, 1]); // [dependent, hidden]

        $checker = new escape_path_checker();
        $results = $checker->analyse([$finding], $cms, $depindex);

        $r = $results[0];
        $this->assertSame('dep_on_hidden', $r['finding_type']);
        $this->assertTrue($r['has_escape']);
        $this->assertSame('unhide_cm', $r['escape_type']);
    }

    /**
     * Unhiding a CM that has further dependents exposes cascade_cmids.
     */
    public function test_dep_on_hidden_cascade_counted(): void {
        $this->resetAfterTest();
        // CM1 hidden. CM2 and CM3 depend on CM1.
        $cma = $this->make_cm(1, false, null);
        $cmb = $this->make_cm(2, true, $this->avail_requires(1));
        $cmc = $this->make_cm(3, true, $this->avail_requires(1));
        $cms = [1 => $cma, 2 => $cmb, 3 => $cmc];
        $depindex = new dependency_index($cms);
        $finding = $this->make_finding('dep_on_hidden', [2, 1]);

        $checker = new escape_path_checker();
        $results = $checker->analyse([$finding], $cms, $depindex);

        // Unhiding CM1 unblocks both CM2 and CM3.
        $this->assertGreaterThanOrEqual(1, $results[0]['cascade_count']);
    }

    // ── tests: completion_no_tracking ─────────────────────────────────────────

    /**
     * completion_no_tracking finding → escape_type='enable_completion', has_escape=true.
     */
    public function test_completion_no_tracking_escape_enable(): void {
        $this->resetAfterTest();
        $cma = $this->make_cm(1, true, null, 0); // no completion tracking.
        $cmb = $this->make_cm(2, true, $this->avail_requires(1));
        $cms = [1 => $cma, 2 => $cmb];
        $depindex = new dependency_index($cms);
        $finding = $this->make_finding('completion_no_tracking', [2, 1]); // [dependent, prereq]

        $checker = new escape_path_checker();
        $results = $checker->analyse([$finding], $cms, $depindex);

        $r = $results[0];
        $this->assertSame('completion_no_tracking', $r['finding_type']);
        $this->assertTrue($r['has_escape']);
        $this->assertSame('enable_completion', $r['escape_type']);
    }

    // ── tests: unknown finding type ───────────────────────────────────────────

    /**
     * Unknown finding type → has_escape=false, escape_type='none'.
     */
    public function test_unknown_type_no_escape(): void {
        $this->resetAfterTest();
        $finding = $this->make_finding('some_unknown_type', [1]);
        $checker = new escape_path_checker();
        $results = $checker->analyse([$finding], [], new dependency_index([]));
        $r = $results[0];
        $this->assertFalse($r['has_escape']);
        $this->assertSame('none', $r['escape_type']);
        $this->assertSame(0, $r['cascade_count']);
    }

    // ── tests: result shape ───────────────────────────────────────────────────

    /**
     * Every result has the required keys.
     */
    public function test_result_has_required_keys(): void {
        $this->resetAfterTest();
        $finding = $this->make_finding('circular_dep', [1]);
        $checker = new escape_path_checker();
        $results = $checker->analyse([$finding], [], new dependency_index([]));
        $r = $results[0];
        $this->assertArrayHasKey('finding_type', $r);
        $this->assertArrayHasKey('cmids', $r);
        $this->assertArrayHasKey('has_escape', $r);
        $this->assertArrayHasKey('escape_type', $r);
        $this->assertArrayHasKey('cascade_cmids', $r);
        $this->assertArrayHasKey('cascade_count', $r);
    }

    /**
     * Multiple findings produce one result per finding, in the same order.
     */
    public function test_multiple_findings_one_result_each(): void {
        $this->resetAfterTest();
        $f1 = $this->make_finding('circular_dep', [1]);
        $f2 = $this->make_finding('dep_on_hidden', [2, 3]);
        $f3 = $this->make_finding('some_unknown_type', [4]);
        $checker = new escape_path_checker();
        $results = $checker->analyse([$f1, $f2, $f3], [], new dependency_index([]));
        $this->assertCount(3, $results);
        $this->assertSame('circular_dep', $results[0]['finding_type']);
        $this->assertSame('dep_on_hidden', $results[1]['finding_type']);
        $this->assertSame('some_unknown_type', $results[2]['finding_type']);
    }

    /**
     * cascade_cmids does not include the originally broken cmids themselves.
     */
    public function test_cascade_does_not_include_broken_cmids(): void {
        $this->resetAfterTest();
        $cma = $this->make_cm(1, true, $this->avail_requires(2));
        $cmb = $this->make_cm(2, true, $this->avail_requires(1));
        $cmc = $this->make_cm(3, true, $this->avail_requires(1)); // downstream
        $cms = [1 => $cma, 2 => $cmb, 3 => $cmc];
        $depindex = new dependency_index($cms);
        $finding = $this->make_finding('circular_dep', [1, 2]);

        $checker = new escape_path_checker();
        $results = $checker->analyse([$finding], $cms, $depindex);
        $cascade = $results[0]['cascade_cmids'];

        $this->assertNotContains(1, $cascade);
        $this->assertNotContains(2, $cascade);
    }
}
