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
 * Tests for dead_end_detector.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\analysis\dead_end_detector::class)]
/**
 * Unit tests for dead_end_detector::detect().
 *
 * @covers \local_coursectrl\local\analysis\dead_end_detector
 */
final class dead_end_detector_test extends \advanced_testcase {
    // Helpers.

    /**
     * Build a cm_item with optional availability JSON and completion.
     *
     * @param int         $cmid
     * @param bool        $visible
     * @param string|null $avail       JSON availability tree.
     * @param int         $completion  0=none, 1=manual, 2=auto.
     * @param int         $complexp    completionexpected timestamp.
     * @param string      $modname
     * @return cm_item
     */
    private function make_cm(
        int $cmid,
        bool $visible = true,
        ?string $avail = null,
        int $completion = 2,
        int $complexp = 0,
        string $modname = 'assign'
    ): cm_item {
        return new cm_item(
            $cmid,
            1,
            1,
            $modname,
            $cmid,
            "CM $cmid",
            $visible,
            $avail,
            $completion,
            $complexp
        );
    }

    /**
     * Build a completion-availability JSON requiring a single CM.
     *
     * @param int $requirecmid
     * @return string
     */
    private function avail_requires(int $requirecmid): string {
        return json_encode([
            'op' => '&',
            'c'  => [['type' => 'completion', 'cm' => $requirecmid, 'e' => 1]],
            'showc' => [true],
        ]);
    }

    /**
     * Build a completion-availability JSON requiring a single CM to NOT be completed (e=0).
     *
     * This models a lock/gate-closing pattern: the activity is accessible
     * only while the given CM has NOT been completed yet.
     *
     * @param int $requirecmid
     * @return string
     */
    private function avail_requires_not(int $requirecmid): string {
        return json_encode([
            'op' => '&',
            'c'  => [['type' => 'completion', 'cm' => $requirecmid, 'e' => 0]],
            'showc' => [false],
        ]);
    }

    // Tests: no issues.

    /**
     * Empty CMs produce no findings.
     * @covers \local_coursectrl\local\analysis\dead_end_detector
     */
    public function test_empty_cms_returns_empty(): void {
        $this->resetAfterTest();
        $detector = new dead_end_detector(10);
        $result = $detector->detect([], new dependency_index([]));
        $this->assertSame([], $result);
    }

    /**
     * A linear chain A→B→C with no cycle produces no circular finding.
     * @covers \local_coursectrl\local\analysis\dead_end_detector
     */
    public function test_linear_chain_no_cycle(): void {
        $this->resetAfterTest();
        $cma = $this->make_cm(1, true, null);
        $cmb = $this->make_cm(2, true, $this->avail_requires(1));
        $cmc = $this->make_cm(3, true, $this->avail_requires(2));
        $cms = [1 => $cma, 2 => $cmb, 3 => $cmc];
        $depindex = new dependency_index($cms);
        $detector = new dead_end_detector(10);
        $risks = $detector->detect($cms, $depindex);
        $types = array_column($risks, 'type');
        $this->assertNotContains('circular_dep_transitive', $types);
    }

    // Tests: circular_dep_transitive.

    /**
     * 2-node cycle A↔B → circular_dep_transitive.
     * @covers \local_coursectrl\local\analysis\dead_end_detector
     */
    public function test_two_node_cycle_detected(): void {
        $this->resetAfterTest();
        $cma = $this->make_cm(1, true, $this->avail_requires(2));
        $cmb = $this->make_cm(2, true, $this->avail_requires(1));
        $cms = [1 => $cma, 2 => $cmb];
        $depindex = new dependency_index($cms);
        $detector = new dead_end_detector(10);
        $risks = $detector->detect($cms, $depindex);
        $types = array_column($risks, 'type');
        $this->assertContains('circular_dep_transitive', $types);
        $circ = current(array_filter($risks, fn ($r) => $r['type'] === 'circular_dep_transitive'));
        $this->assertSame('error', $circ['severity']);
        $this->assertSame(1.0, $circ['probability']);
    }

    /**
     * 3-node cycle A→B→C→A → circular_dep_transitive, all three cmids present.
     * @covers \local_coursectrl\local\analysis\dead_end_detector
     */
    public function test_three_node_cycle_detected(): void {
        $this->resetAfterTest();
        $cma = $this->make_cm(1, true, $this->avail_requires(3));
        $cmb = $this->make_cm(2, true, $this->avail_requires(1));
        $cmc = $this->make_cm(3, true, $this->avail_requires(2));
        $cms = [1 => $cma, 2 => $cmb, 3 => $cmc];
        $depindex = new dependency_index($cms);
        $detector = new dead_end_detector(10);
        $risks = $detector->detect($cms, $depindex);
        $types = array_column($risks, 'type');
        $this->assertContains('circular_dep_transitive', $types);
        $circ = current(array_filter($risks, fn ($r) => $r['type'] === 'circular_dep_transitive'));
        $this->assertCount(3, $circ['cmids']);
        $this->assertEqualsCanonicalizing([1, 2, 3], $circ['cmids']);
    }

    /**
     * Self-referencing CM (requires own completion) → circular_dep_transitive.
     * @covers \local_coursectrl\local\analysis\dead_end_detector
     */
    public function test_self_reference_cycle_detected(): void {
        $this->resetAfterTest();
        $cma = $this->make_cm(1, true, $this->avail_requires(1));
        $cms = [1 => $cma];
        $depindex = new dependency_index($cms);
        $detector = new dead_end_detector(10);
        $risks = $detector->detect($cms, $depindex);
        $types = array_column($risks, 'type');
        $this->assertContains('circular_dep_transitive', $types);
    }

    /**
     * An isolated CM (no deps, no cycle) outside the cycle is not flagged.
     * @covers \local_coursectrl\local\analysis\dead_end_detector
     */
    public function test_isolated_cm_not_flagged_as_cycle(): void {
        $this->resetAfterTest();
        $cma = $this->make_cm(1, true, $this->avail_requires(2));
        $cmb = $this->make_cm(2, true, $this->avail_requires(1)); // Cycle with A.
        $cmc = $this->make_cm(3, true, null);                      // Isolated.
        $cms = [1 => $cma, 2 => $cmb, 3 => $cmc];
        $depindex = new dependency_index($cms);
        $detector = new dead_end_detector(10);
        $risks = $detector->detect($cms, $depindex);
        $circ = array_filter($risks, fn ($r) => $r['type'] === 'circular_dep_transitive');
        foreach ($circ as $c) {
            $this->assertNotContains(3, $c['cmids']);
        }
    }

    /**
     * A lock-pattern (B requires A completed; A requires B NOT completed)
     * must NOT be flagged as a circular dependency.
     *
     * This is the standard "show A to introduce the task, hide A once B is
     * submitted" design and is explicitly intended, not a deadlock.
     * @covers \local_coursectrl\local\analysis\dead_end_detector
     */
    public function test_lock_dep_pattern_not_flagged_as_circular(): void {
        $this->resetAfterTest();
        // B depends on A completed (e=1): A's completion unlocks B.
        $cmb = $this->make_cm(2, true, $this->avail_requires(1));
        // A depends on B NOT completed (e=0): A is hidden once B is done.
        $cma = $this->make_cm(1, true, $this->avail_requires_not(2));
        $cms = [1 => $cma, 2 => $cmb];
        $depindex = new dependency_index($cms);
        $detector = new dead_end_detector(10);
        $risks = $detector->detect($cms, $depindex);
        $types = array_column($risks, 'type');
        $this->assertNotContains('circular_dep_transitive', $types);
    }

    /**
     * A genuine mutual unlock cycle (A requires B completed, B requires A
     * completed) must still be detected even after the lock-dep fix.
     * @covers \local_coursectrl\local\analysis\dead_end_detector
     */
    public function test_mutual_unlock_cycle_still_detected(): void {
        $this->resetAfterTest();
        $cma = $this->make_cm(1, true, $this->avail_requires(2));
        $cmb = $this->make_cm(2, true, $this->avail_requires(1));
        $cms = [1 => $cma, 2 => $cmb];
        $depindex = new dependency_index($cms);
        $detector = new dead_end_detector(10);
        $risks = $detector->detect($cms, $depindex);
        $types = array_column($risks, 'type');
        $this->assertContains('circular_dep_transitive', $types);
    }

    // Tests: dep_on_hidden / hidden_with_dependents.

    /**
     * CM B depends on hidden CM A → dep_on_hidden (error).
     * @covers \local_coursectrl\local\analysis\dead_end_detector
     */
    public function test_dep_on_hidden_detected(): void {
        $this->resetAfterTest();
        $cma = $this->make_cm(1, false, null); // Hidden.
        $cmb = $this->make_cm(2, true, $this->avail_requires(1));
        $cms = [1 => $cma, 2 => $cmb];
        $depindex = new dependency_index($cms);
        $detector = new dead_end_detector(10);
        $risks = $detector->detect($cms, $depindex);
        $types = array_column($risks, 'type');
        $this->assertContains('dep_on_hidden', $types);
        $r = current(array_filter($risks, fn ($r) => $r['type'] === 'dep_on_hidden'));
        $this->assertSame('error', $r['severity']);
        $this->assertContains(2, $r['cmids']);
    }

    /**
     * Hidden CM A with dependents → hidden_with_dependents (warning).
     * @covers \local_coursectrl\local\analysis\dead_end_detector
     */
    public function test_hidden_with_dependents_detected(): void {
        $this->resetAfterTest();
        $cma = $this->make_cm(1, false, null); // Hidden, has dependents.
        $cmb = $this->make_cm(2, true, $this->avail_requires(1));
        $cms = [1 => $cma, 2 => $cmb];
        $depindex = new dependency_index($cms);
        $detector = new dead_end_detector(10);
        $risks = $detector->detect($cms, $depindex);
        $types = array_column($risks, 'type');
        $this->assertContains('hidden_with_dependents', $types);
        $r = current(array_filter($risks, fn ($r) => $r['type'] === 'hidden_with_dependents'));
        $this->assertSame('warning', $r['severity']);
        $this->assertContains(1, $r['cmids']);
    }

    /**
     * Visible CM with no dependents → neither dep_on_hidden nor hidden_with_dependents.
     * @covers \local_coursectrl\local\analysis\dead_end_detector
     */
    public function test_visible_cm_no_hidden_findings(): void {
        $this->resetAfterTest();
        $cma = $this->make_cm(1, true, null);
        $cmb = $this->make_cm(2, true, $this->avail_requires(1));
        $cms = [1 => $cma, 2 => $cmb];
        $depindex = new dependency_index($cms);
        $detector = new dead_end_detector(10);
        $risks = $detector->detect($cms, $depindex);
        $types = array_column($risks, 'type');
        $this->assertNotContains('dep_on_hidden', $types);
        $this->assertNotContains('hidden_with_dependents', $types);
    }

    // Tests: completion_required_no_tracking.

    /**
     * completionexpected set but completion=0 → completion_required_no_tracking.
     * @covers \local_coursectrl\local\analysis\dead_end_detector
     */
    public function test_completionexpected_without_tracking(): void {
        $this->resetAfterTest();
        $cm = $this->make_cm(1, true, null, 0, 1748736000); // Completion disabled (0) but expected date set.
        $cms = [1 => $cm];
        $depindex = new dependency_index($cms);
        $detector = new dead_end_detector(10);
        $risks = $detector->detect($cms, $depindex);
        $types = array_column($risks, 'type');
        $this->assertContains('completion_required_no_tracking', $types);
        $r = current(array_filter($risks, fn ($r) => $r['type'] === 'completion_required_no_tracking'));
        $this->assertSame('warning', $r['severity']);
    }

    /**
     * completionexpected=0 (not set) with completion=0 → no mismatch finding.
     * @covers \local_coursectrl\local\analysis\dead_end_detector
     */
    public function test_no_completionexpected_no_mismatch(): void {
        $this->resetAfterTest();
        $cm = $this->make_cm(1, true, null, 0, 0);
        $cms = [1 => $cm];
        $depindex = new dependency_index($cms);
        $detector = new dead_end_detector(10);
        $risks = $detector->detect($cms, $depindex);
        $types = array_column($risks, 'type');
        $this->assertNotContains('completion_required_no_tracking', $types);
    }

    /**
     * completionexpected set AND completion=2 → no mismatch finding.
     * @covers \local_coursectrl\local\analysis\dead_end_detector
     */
    public function test_completionexpected_with_tracking_no_mismatch(): void {
        $this->resetAfterTest();
        $cm = $this->make_cm(1, true, null, 2, 1748736000); // Completion mode is automatic tracking (value 2).
        $cms = [1 => $cm];
        $depindex = new dependency_index($cms);
        $detector = new dead_end_detector(10);
        $risks = $detector->detect($cms, $depindex);
        $types = array_column($risks, 'type');
        $this->assertNotContains('completion_required_no_tracking', $types);
    }

    // Tests: long_dep_chain.

    /**
     * Chain longer than maxchaindepth → long_dep_chain notice.
     * @covers \local_coursectrl\local\analysis\dead_end_detector
     */
    public function test_long_chain_detected(): void {
        $this->resetAfterTest();
        // Chain has 5 nodes; max-depth limit is 3.
        $cms = [];
        $cms[1] = $this->make_cm(1, true, null);
        for ($i = 2; $i <= 5; $i++) {
            $cms[$i] = $this->make_cm($i, true, $this->avail_requires($i - 1));
        }
        $depindex = new dependency_index($cms);
        $detector = new dead_end_detector(3); // Max-depth limit set to 3.
        $risks = $detector->detect($cms, $depindex);
        $types = array_column($risks, 'type');
        $this->assertContains('long_dep_chain', $types);
        $r = current(array_filter($risks, fn ($r) => $r['type'] === 'long_dep_chain'));
        $this->assertSame('notice', $r['severity']);
    }

    /**
     * Chain shorter than maxchaindepth → no long_dep_chain.
     * @covers \local_coursectrl\local\analysis\dead_end_detector
     */
    public function test_short_chain_no_long_dep(): void {
        $this->resetAfterTest();
        // Short chain of 3 nodes; limit of 10 allows full traversal.
        $cms[1] = $this->make_cm(1, true, null);
        $cms[2] = $this->make_cm(2, true, $this->avail_requires(1));
        $cms[3] = $this->make_cm(3, true, $this->avail_requires(2));
        $depindex = new dependency_index($cms);
        $detector = new dead_end_detector(10);
        $risks = $detector->detect($cms, $depindex);
        $types = array_column($risks, 'type');
        $this->assertNotContains('long_dep_chain', $types);
    }

    /**
     * maxchaindepth=0 disables long-chain detection entirely.
     * @covers \local_coursectrl\local\analysis\dead_end_detector
     */
    public function test_maxdepth_zero_disables_long_chain(): void {
        $this->resetAfterTest();
        $cms[1] = $this->make_cm(1, true, null);
        $cms[2] = $this->make_cm(2, true, $this->avail_requires(1));
        $cms[3] = $this->make_cm(3, true, $this->avail_requires(2));
        $depindex = new dependency_index($cms);
        $detector = new dead_end_detector(0); // Disabled.
        $risks = $detector->detect($cms, $depindex);
        $types = array_column($risks, 'type');
        $this->assertNotContains('long_dep_chain', $types);
    }

    // Tests: combinations.

    /**
     * A course with both a cycle and a hidden-dep issue produces both findings.
     * @covers \local_coursectrl\local\analysis\dead_end_detector
     */
    public function test_cycle_and_hidden_dep_coexist(): void {
        $this->resetAfterTest();
        // CM 1↔2 form a cycle. CM 3 depends on hidden CM 4.
        $cma = $this->make_cm(1, true, $this->avail_requires(2));
        $cmb = $this->make_cm(2, true, $this->avail_requires(1));
        $cmc = $this->make_cm(3, true, $this->avail_requires(4));
        $cmd = $this->make_cm(4, false, null);
        $cms = [1 => $cma, 2 => $cmb, 3 => $cmc, 4 => $cmd];
        $depindex = new dependency_index($cms);
        $detector = new dead_end_detector(10);
        $risks = $detector->detect($cms, $depindex);
        $types = array_column($risks, 'type');
        $this->assertContains('circular_dep_transitive', $types);
        $this->assertContains('dep_on_hidden', $types);
    }
}
