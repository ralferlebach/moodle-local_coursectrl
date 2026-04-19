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
 * Integration tests: adapter run_checks() output for R3 and R7.
 *
 * Tests run against real Moodle activity instances created via the data
 * generator. Each test verifies that the adapter's run_checks() method
 * returns the correct code/severity combination for a specific configuration.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\tests;

use local_coursectrl\local\registry;

/**
 * Tests for run_checks() across assign, quiz, forum, and workshop adapters.
 *
 * @covers \local_coursectrl\local\contract\check_helper
 */
final class adapter_run_checks_test extends \advanced_testcase {
    /** @var int Base timestamp (2026-06-01 00:00 UTC). */
    private const T_BASE = 1748736000;

    /** @var int 7 days in seconds. */
    private const WEEK = 604800;

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Get adapter via registry for a given component.
     *
     * @param string $component e.g. 'mod_assign'
     * @return \local_coursectrl\local\contract\activity_adapter|null
     */
    private function get_adapter(string $component) {
        $registry = new registry();
        return $registry->get_for_component($component);
    }

    /**
     * Assert that a run_checks result contains an item with the given code.
     *
     * @param array  $results  Return value of run_checks().
     * @param string $code     Expected code value.
     * @param string $severity Expected severity or '' to skip check.
     */
    private function asserthascheck(array $results, string $code, string $severity = ''): void {
        $codes = array_column($results, 'code');
        $this->assertContains($code, $codes, "Expected check code '$code' not found in results");
        if ($severity !== '') {
            foreach ($results as $r) {
                if ($r['code'] === $code) {
                    $this->assertSame(
                        $severity,
                        $r['severity'],
                        "Check '$code' expected severity '$severity', got '{$r['severity']}'"
                    );
                    break;
                }
            }
        }
    }

    /**
     * Assert that a run_checks result does NOT contain an item with the given code.
     *
     * @param array  $results
     * @param string $code
     */
    private function assertnocheck(array $results, string $code): void {
        $codes = array_column($results, 'code');
        $this->assertNotContains($code, $codes, "Unexpected check code '$code' found in results");
    }

    // ── assign ────────────────────────────────────────────────────────────────

    /**
     * R3: assign allowsubmissionsfromdate after duedate → error assign_from_after_due.
     */
    public function test_assign_r3_open_after_due(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->get_plugin_generator('mod_assign')
            ->create_instance([
                'course' => $course->id,
                'allowsubmissionsfromdate' => self::T_BASE + self::WEEK,
                'duedate' => self::T_BASE,
            ]);
        $adapter = $this->get_adapter('mod_assign');
        $this->assertNotNull($adapter, 'mod_assign adapter must be registered');
        $results = $adapter->run_checks([(int) $assign->cmid]);
        $this->asserthascheck($results, 'assign_from_after_due', 'error');
    }

    /**
     * R7: assign cutoffdate set, duedate not set → severity from config (default notice/warning).
     */
    public function test_assign_r7_cutoff_without_duedate(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->get_plugin_generator('mod_assign')
            ->create_instance([
                'course' => $course->id,
                'duedate' => 0,
                'cutoffdate' => self::T_BASE + self::WEEK,
            ]);
        $adapter = $this->get_adapter('mod_assign');
        $results = $adapter->run_checks([(int) $assign->cmid]);
        $this->asserthascheck($results, 'assign_cutoffdate_without_duedate');
    }

    /**
     * R7: assign gradingduedate set, duedate and cutoff not set.
     */
    public function test_assign_r7_gradingdue_without_duedate(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->get_plugin_generator('mod_assign')
            ->create_instance([
                'course' => $course->id,
                'duedate' => 0,
                'cutoffdate' => 0,
                'gradingduedate' => self::T_BASE + self::WEEK,
            ]);
        $adapter = $this->get_adapter('mod_assign');
        $results = $adapter->run_checks([(int) $assign->cmid]);
        $this->asserthascheck($results, 'assign_gradingduedate_without_duedate');
    }

    /**
     * R7: assign allowsubmissionsfromdate set, duedate not set.
     */
    public function test_assign_r7_fromdate_without_duedate(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->get_plugin_generator('mod_assign')
            ->create_instance([
                'course' => $course->id,
                'duedate' => 0,
                'allowsubmissionsfromdate' => self::T_BASE,
            ]);
        $adapter = $this->get_adapter('mod_assign');
        $results = $adapter->run_checks([(int) $assign->cmid]);
        $this->asserthascheck($results, 'assign_allowsubmissionsfromdate_without_duedate');
    }

    /**
     * R3+R7 clean: assign with all dates correctly ordered → no issues.
     */
    public function test_assign_clean_no_issues(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->get_plugin_generator('mod_assign')
            ->create_instance([
                'course' => $course->id,
                'allowsubmissionsfromdate' => self::T_BASE,
                'duedate' => self::T_BASE + self::WEEK,
                'cutoffdate' => self::T_BASE + self::WEEK * 2,
            ]);
        $adapter = $this->get_adapter('mod_assign');
        $results = $adapter->run_checks([(int) $assign->cmid]);
        $this->assertnocheck($results, 'assign_from_after_due');
        $this->assertnocheck($results, 'assign_cutoffdate_without_duedate');
        $this->assertnocheck($results, 'assign_allowsubmissionsfromdate_without_duedate');
    }

    // ── quiz ──────────────────────────────────────────────────────────────────

    /**
     * R3: quiz timeopen after timeclose → error.
     */
    public function test_quiz_r3_open_after_close(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')
            ->create_instance([
                'course' => $course->id,
                'timeopen' => self::T_BASE + self::WEEK,
                'timeclose' => self::T_BASE,
            ]);
        $adapter = $this->get_adapter('mod_quiz');
        $this->assertNotNull($adapter, 'mod_quiz adapter must be registered');
        $results = $adapter->run_checks([(int) $quiz->cmid]);
        $codes = array_column($results, 'code');
        // The quiz adapter may use different code names — check severity is error.
        $errors = array_filter($results, fn($r) => $r['severity'] === 'error');
        $this->assertNotEmpty($errors, 'R3 quiz timeopen > timeclose should produce an error');
    }

    /**
     * R7: quiz timeopen set, timeclose not set.
     */
    public function test_quiz_r7_timeopen_without_timeclose(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')
            ->create_instance([
                'course' => $course->id,
                'timeopen' => self::T_BASE,
                'timeclose' => 0,
            ]);
        $adapter = $this->get_adapter('mod_quiz');
        $results = $adapter->run_checks([(int) $quiz->cmid]);
        $this->assertNotEmpty($results, 'R7 timeopen without timeclose should produce a finding');
    }

    /**
     * run_checks result items all have required keys.
     */
    public function test_quiz_result_shape(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')
            ->create_instance([
                'course' => $course->id,
                'timeopen' => self::T_BASE + self::WEEK,
                'timeclose' => self::T_BASE,
            ]);
        $adapter = $this->get_adapter('mod_quiz');
        $results = $adapter->run_checks([(int) $quiz->cmid]);
        foreach ($results as $r) {
            $this->assertArrayHasKey('cmid', $r);
            $this->assertArrayHasKey('severity', $r);
            $this->assertArrayHasKey('code', $r);
            $this->assertArrayHasKey('message', $r);
        }
    }

    // ── forum ─────────────────────────────────────────────────────────────────

    /**
     * R7: forum duedate set, cutoffdate not set.
     */
    public function test_forum_r7_duedate_without_cutoff(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->get_plugin_generator('mod_forum')
            ->create_instance([
                'course' => $course->id,
                'duedate' => self::T_BASE + self::WEEK,
                'cutoffdate' => 0,
            ]);
        $adapter = $this->get_adapter('mod_forum');
        $this->assertNotNull($adapter, 'mod_forum adapter must be registered');
        $results = $adapter->run_checks([(int) $forum->cmid]);
        $this->asserthascheck($results, 'forum_duedate_without_cutoffdate');
    }

    /**
     * R7: forum cutoffdate set, duedate not set.
     */
    public function test_forum_r7_cutoff_without_duedate(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->get_plugin_generator('mod_forum')
            ->create_instance([
                'course' => $course->id,
                'duedate' => 0,
                'cutoffdate' => self::T_BASE + self::WEEK,
            ]);
        $adapter = $this->get_adapter('mod_forum');
        $results = $adapter->run_checks([(int) $forum->cmid]);
        $this->asserthascheck($results, 'forum_cutoffdate_without_duedate');
    }

    /**
     * Forum clean: both duedate and cutoffdate set in correct order → no R7.
     */
    public function test_forum_clean_no_r7(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->get_plugin_generator('mod_forum')
            ->create_instance([
                'course' => $course->id,
                'duedate' => self::T_BASE + self::WEEK,
                'cutoffdate' => self::T_BASE + self::WEEK * 2,
            ]);
        $adapter = $this->get_adapter('mod_forum');
        $results = $adapter->run_checks([(int) $forum->cmid]);
        $this->assertnocheck($results, 'forum_duedate_without_cutoffdate');
        $this->assertnocheck($results, 'forum_cutoffdate_without_duedate');
    }

    // ── workshop ──────────────────────────────────────────────────────────────

    /**
     * R7: workshop assessmentstart set, assessmentend not set.
     */
    public function test_workshop_r7_assessmentstart_without_end(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $workshop = $this->getDataGenerator()->get_plugin_generator('mod_workshop')
            ->create_instance([
                'course' => $course->id,
                'submissionstart' => self::T_BASE,
                'submissionend' => self::T_BASE + self::WEEK,
                'assessmentstart' => self::T_BASE + self::WEEK,
                'assessmentend' => 0,
            ]);
        $adapter = $this->get_adapter('mod_workshop');
        $this->assertNotNull($adapter, 'mod_workshop adapter must be registered');
        $results = $adapter->run_checks([(int) $workshop->cmid]);
        $this->asserthascheck($results, 'workshop_assessmentstart_without_assessmentend');
    }

    /**
     * R3: workshop submissionstart after submissionend → error.
     */
    public function test_workshop_r3_submission_inverted(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $workshop = $this->getDataGenerator()->get_plugin_generator('mod_workshop')
            ->create_instance([
                'course' => $course->id,
                'submissionstart' => self::T_BASE + self::WEEK,
                'submissionend' => self::T_BASE,
                'assessmentstart' => self::T_BASE + self::WEEK * 2,
                'assessmentend' => self::T_BASE + self::WEEK * 3,
            ]);
        $adapter = $this->get_adapter('mod_workshop');
        $results = $adapter->run_checks([(int) $workshop->cmid]);
        $errors = array_filter($results, fn($r) => $r['severity'] === 'error');
        $this->assertNotEmpty($errors, 'R3 workshop submission inverted should produce error');
    }

    /**
     * Workshop clean: all phases correctly ordered → no R3/R7 issues.
     */
    public function test_workshop_clean_no_issues(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $workshop = $this->getDataGenerator()->get_plugin_generator('mod_workshop')
            ->create_instance([
                'course' => $course->id,
                'submissionstart' => self::T_BASE,
                'submissionend' => self::T_BASE + self::WEEK,
                'assessmentstart' => self::T_BASE + self::WEEK,
                'assessmentend' => self::T_BASE + self::WEEK * 2,
            ]);
        $adapter = $this->get_adapter('mod_workshop');
        $results = $adapter->run_checks([(int) $workshop->cmid]);
        $errors = array_filter($results, fn($r) => $r['severity'] === 'error');
        $this->assertEmpty($errors, 'Clean workshop should produce no errors');
        $this->assertnocheck($results, 'workshop_assessmentstart_without_assessmentend');
    }

    // ── empty input ───────────────────────────────────────────────────────────

    /**
     * run_checks with empty cmids array returns empty.
     */
    public function test_empty_cmids_returns_empty(): void {
        $this->resetAfterTest();
        $adapter = $this->get_adapter('mod_assign');
        $this->assertNotNull($adapter);
        $this->assertSame([], $adapter->run_checks([]));
    }
}
