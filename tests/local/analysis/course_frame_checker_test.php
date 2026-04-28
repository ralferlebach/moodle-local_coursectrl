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
 * Tests for course_frame_checker.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;

/**
 * Unit tests for course_frame_checker::check().
 *
 * Timestamps are constructed relative to a fixed anchor so the test does
 * not rely on wall-clock time — except for R0c (deadline in the past),
 * which intentionally uses a past timestamp.
 *
 * @covers \local_coursectrl\local\analysis\course_frame_checker
 */
final class course_frame_checker_test extends \advanced_testcase {
    /** @var int Course start: 2026-05-01 00:00 UTC */
    private const COURSE_START = 1746057600;

    /** @var int Course end: 2026-10-31 23:59 UTC */
    private const COURSE_END = 1762041540;

    /** @var int A date safely inside the course: 2026-07-01 */
    private const DATE_INSIDE = 1751328000;

    /** @var int A date after course end: 2026-11-15 */
    private const DATE_AFTER_END = 1763164800;

    /** @var int A date before course start: 2026-04-01 */
    private const DATE_BEFORE_START = 1743465600;

    /** @var int A timestamp safely in the past (2020-01-01). */
    private const DATE_PAST = 1577836800;

    // Helpers.

    /**
     * Build a minimal cm_item.
     *
     * @param int         $cmid
     * @param string      $modname
     * @param int         $completion 0=none, 1=manual, 2=auto.
     * @param bool        $visible
     * @param string|null $avail
     * @return cm_item
     */
    private function make_cm(
        int $cmid,
        string $modname = 'assign',
        int $completion = 0,
        bool $visible = true,
        ?string $avail = null
    ): cm_item {
        return new cm_item($cmid, 1, 1, $modname, $cmid, "CM $cmid", $visible, $avail, $completion);
    }

    /**
     * Build a fake course object.
     *
     * @param int $startdate
     * @param int $enddate
     * @return \stdClass
     */
    private function make_course(int $startdate = 0, int $enddate = 0): \stdClass {
        $c = new \stdClass();
        $c->startdate = $startdate;
        $c->enddate   = $enddate;
        return $c;
    }

    /**
     * Build a date entry for datesbycm.
     *
     * @param int    $cmid
     * @param string $field
     * @param int    $ts
     * @param string $source
     * @return array
     */
    private function make_date(int $cmid, string $field, int $ts, string $source = 'adapter'): array {
        return ['cmid' => $cmid, 'field' => $field, 'fieldlabel' => $field,
                'timestamp' => $ts, 'source' => $source];
    }

    // Tests.

    /**
     * Empty input produces empty output.
     */
    public function test_empty_returns_empty(): void {
        $this->resetAfterTest();
        $checker = new course_frame_checker();
        $result = $checker->check([], [], $this->make_course());
        $this->assertSame([], $result);
    }

    /**
     * CM with no dates produces no issues.
     */
    public function test_cm_without_dates_produces_no_issue(): void {
        $this->resetAfterTest();
        $checker = new course_frame_checker();
        $cm = $this->make_cm(10);
        $result = $checker->check([10 => $cm], [], $this->make_course(self::COURSE_START, self::COURSE_END));
        $this->assertArrayNotHasKey(10, $result);
    }

    /**
     * Date inside course window produces no R0 issue.
     */
    public function test_date_inside_course_frame_no_issue(): void {
        $this->resetAfterTest();
        $checker = new course_frame_checker();
        $cm = $this->make_cm(10, 'assign', 2);
        // Use a future date (30 days from now) so R0c (past deadline) does not fire.
        $future = time() + 30 * 86400;
        $start  = time() - 30 * 86400;
        $end    = time() + 180 * 86400;
        $dates  = [10 => [$this->make_date(10, 'duedate', $future)]];
        $result = $checker->check([10 => $cm], $dates, $this->make_course($start, $end));
        $this->assertArrayNotHasKey(10, $result);
    }

    /**
     * R0a: date after course end → error r0_after_course_end.
     */
    public function test_r0a_date_after_course_end(): void {
        $this->resetAfterTest();
        $checker = new course_frame_checker();
        $cm = $this->make_cm(10, 'assign', 2);
        $dates = [10 => [$this->make_date(10, 'duedate', self::DATE_AFTER_END)]];
        $result = $checker->check([10 => $cm], $dates, $this->make_course(self::COURSE_START, self::COURSE_END));

        $this->assertArrayHasKey(10, $result);
        $types = array_column($result[10], 'type');
        $this->assertContains('r0_after_course_end', $types);
        $issue = current(array_filter($result[10], fn ($i) => $i['type'] === 'r0_after_course_end'));
        $this->assertSame('error', $issue['severity']);
        $this->assertSame('duedate', $issue['field']);
    }

    /**
     * R0a fires only when course end is set; no end → no issue.
     */
    public function test_r0a_skipped_when_no_course_end(): void {
        $this->resetAfterTest();
        $checker = new course_frame_checker();
        $cm = $this->make_cm(10, 'assign');
        $dates = [10 => [$this->make_date(10, 'duedate', self::DATE_AFTER_END)]];
        // No course end date set.
        $result = $checker->check([10 => $cm], $dates, $this->make_course(self::COURSE_START, 0));
        $this->assertArrayNotHasKey(10, $result);
    }

    /**
     * R0b: date before course start → error r0_before_course_start.
     */
    public function test_r0b_date_before_course_start(): void {
        $this->resetAfterTest();
        $checker = new course_frame_checker();
        $cm = $this->make_cm(20, 'quiz', 2);
        $dates = [20 => [$this->make_date(20, 'timeopen', self::DATE_BEFORE_START)]];
        $result = $checker->check([20 => $cm], $dates, $this->make_course(self::COURSE_START, self::COURSE_END));

        $this->assertArrayHasKey(20, $result);
        $types = array_column($result[20], 'type');
        $this->assertContains('r0_before_course_start', $types);
        $issue = current(array_filter($result[20], fn ($i) => $i['type'] === 'r0_before_course_start'));
        $this->assertSame('error', $issue['severity']);
    }

    /**
     * R0b fires only when course start is set; no start → no issue.
     */
    public function test_r0b_skipped_when_no_course_start(): void {
        $this->resetAfterTest();
        $checker = new course_frame_checker();
        $cm = $this->make_cm(20, 'quiz');
        $dates = [20 => [$this->make_date(20, 'timeopen', self::DATE_BEFORE_START)]];
        $result = $checker->check([20 => $cm], $dates, $this->make_course(0, self::COURSE_END));
        $this->assertArrayNotHasKey(20, $result);
    }

    /**
     * R0c: assign duedate in past + completion tracking active → warning.
     */
    public function test_r0c_deadline_in_past_with_completion(): void {
        $this->resetAfterTest();
        $checker = new course_frame_checker();
        // Completion mode is automatic tracking (Moodle constant value 2).
        $cm = $this->make_cm(30, 'assign', 2);
        $dates = [30 => [$this->make_date(30, 'duedate', self::DATE_PAST)]];
        $result = $checker->check([30 => $cm], $dates, $this->make_course(0, 0));

        $this->assertArrayHasKey(30, $result);
        $types = array_column($result[30], 'type');
        $this->assertContains('r0_deadline_in_past', $types);
        $issue = current(array_filter($result[30], fn ($i) => $i['type'] === 'r0_deadline_in_past'));
        $this->assertSame('warning', $issue['severity']);
    }

    /**
     * R0c does NOT fire when completion tracking is off (completion=0).
     */
    public function test_r0c_skipped_without_completion_tracking(): void {
        $this->resetAfterTest();
        $checker = new course_frame_checker();
        $cm = $this->make_cm(30, 'assign', 0); // No completion tracking.
        $dates = [30 => [$this->make_date(30, 'duedate', self::DATE_PAST)]];
        $result = $checker->check([30 => $cm], $dates, $this->make_course(0, 0));

        if (isset($result[30])) {
            $types = array_column($result[30], 'type');
            $this->assertNotContains('r0_deadline_in_past', $types);
        } else {
            $this->assertArrayNotHasKey(30, $result);
        }
    }

    /**
     * R0c uses the primary deadline field per component.
     * quiz uses timeclose, not duedate.
     */
    public function test_r0c_uses_component_deadline_field(): void {
        $this->resetAfterTest();
        $checker = new course_frame_checker();
        $cm = $this->make_cm(40, 'quiz', 2);
        $dates = [40 => [
            $this->make_date(40, 'timeopen', self::DATE_PAST),
            $this->make_date(40, 'timeclose', self::DATE_PAST),
        ]];
        $result = $checker->check([40 => $cm], $dates, $this->make_course(0, 0));

        $this->assertArrayHasKey(40, $result);
        $types = array_column($result[40], 'type');
        $this->assertContains('r0_deadline_in_past', $types);
        $issue = current(array_filter($result[40], fn ($i) => $i['type'] === 'r0_deadline_in_past'));
        $this->assertSame('timeclose', $issue['field']);
    }

    /**
     * Multiple CMs: each gets its own issues independently.
     */
    public function test_multiple_cms_independent(): void {
        $this->resetAfterTest();
        $checker = new course_frame_checker();
        $cm10 = $this->make_cm(10, 'assign', 2);
        $cm20 = $this->make_cm(20, 'quiz', 2);
        $cm30 = $this->make_cm(30, 'forum', 0);

        $dates = [
            10 => [$this->make_date(10, 'duedate', self::DATE_AFTER_END)], // R0a.
            20 => [$this->make_date(20, 'timeopen', self::DATE_BEFORE_START)], // R0b.
            30 => [$this->make_date(30, 'duedate', self::DATE_INSIDE)], // Valid.
        ];

        $cms = [10 => $cm10, 20 => $cm20, 30 => $cm30];
        $result = $checker->check($cms, $dates, $this->make_course(self::COURSE_START, self::COURSE_END));

        $this->assertArrayHasKey(10, $result);
        $this->assertContains('r0_after_course_end', array_column($result[10], 'type'));

        $this->assertArrayHasKey(20, $result);
        $this->assertContains('r0_before_course_start', array_column($result[20], 'type'));

        $this->assertArrayNotHasKey(30, $result);
    }

    /**
     * A CM with both R0a and R0b issues (one date after end, another before start)
     * reports both independently.
     */
    public function test_cm_can_have_r0a_and_r0b(): void {
        $this->resetAfterTest();
        $checker = new course_frame_checker();
        $cm = $this->make_cm(10, 'assign', 0);
        $dates = [10 => [
            $this->make_date(10, 'duedate', self::DATE_AFTER_END),
            $this->make_date(10, 'cutoffdate', self::DATE_BEFORE_START),
        ]];
        $result = $checker->check([10 => $cm], $dates, $this->make_course(self::COURSE_START, self::COURSE_END));

        $this->assertArrayHasKey(10, $result);
        $types = array_column($result[10], 'type');
        $this->assertContains('r0_after_course_end', $types);
        $this->assertContains('r0_before_course_start', $types);
    }

    /**
     * Dates with timestamp=0 are ignored (no issue raised).
     */
    public function test_zero_timestamp_ignored(): void {
        $this->resetAfterTest();
        $checker = new course_frame_checker();
        $cm = $this->make_cm(10, 'assign', 2);
        $dates = [10 => [$this->make_date(10, 'duedate', 0)]];
        $result = $checker->check([10 => $cm], $dates, $this->make_course(self::COURSE_START, self::COURSE_END));
        $this->assertArrayNotHasKey(10, $result);
    }

    /**
     * R0c: only fires for entries with source='adapter', not source='text'.
     */
    public function test_r0c_ignores_text_source(): void {
        $this->resetAfterTest();
        $checker = new course_frame_checker();
        $cm = $this->make_cm(10, 'assign', 2);
        // Past date but from a text hit, not from the adapter.
        $dates = [10 => [$this->make_date(10, 'duedate', self::DATE_PAST, 'text')]];
        $result = $checker->check([10 => $cm], $dates, $this->make_course(0, 0));

        if (isset($result[10])) {
            $this->assertNotContains('r0_deadline_in_past', array_column($result[10], 'type'));
        } else {
            $this->assertArrayNotHasKey(10, $result);
        }
    }

    // Tests: course completion criteria severity escalation (C3).

    /**
     * R0a warning stays 'error'; R0c 'warning' → 'error' when cmid is in critcmids.
     */
    public function test_r0c_escalated_to_error_for_completion_critical_activity(): void {
        $this->resetAfterTest();
        $checker = new course_frame_checker();
        $cm = $this->make_cm(30, 'assign', 2);
        $dates = [30 => [$this->make_date(30, 'duedate', self::DATE_PAST)]];
        // Pass cmid 30 as completion-critical.
        $result = $checker->check([30 => $cm], $dates, $this->make_course(0, 0), [30]);

        $this->assertArrayHasKey(30, $result);
        $issue = current(array_filter($result[30], fn ($i) => $i['type'] === 'r0_deadline_in_past'));
        $this->assertNotFalse($issue, 'r0_deadline_in_past must be present');
        $this->assertSame('error', $issue['severity'], 'Severity must be escalated to error');
        $this->assertTrue($issue['completion_escalated'] ?? false, 'completion_escalated flag must be set');
    }

    /**
     * Activities NOT in critcmids keep their original severity.
     */
    public function test_r0c_not_escalated_when_not_in_critcmids(): void {
        $this->resetAfterTest();
        $checker = new course_frame_checker();
        $cm = $this->make_cm(30, 'assign', 2);
        $dates = [30 => [$this->make_date(30, 'duedate', self::DATE_PAST)]];
        // Pass an empty critcmids — no escalation.
        $result = $checker->check([30 => $cm], $dates, $this->make_course(0, 0), []);

        $this->assertArrayHasKey(30, $result);
        $issue = current(array_filter($result[30], fn ($i) => $i['type'] === 'r0_deadline_in_past'));
        $this->assertSame('warning', $issue['severity']);
        $this->assertArrayNotHasKey('completion_escalated', $issue);
    }

    /**
     * R0a (error-level) stays at 'error' — escalation beyond error is not applied.
     */
    public function test_r0a_error_not_double_escalated(): void {
        $this->resetAfterTest();
        $checker = new course_frame_checker();
        $cm = $this->make_cm(10, 'assign', 0);
        $future = self::DATE_FUTURE + 10 * 86400;
        $dates = [10 => [$this->make_date(10, 'duedate', $future)]];
        $result = $checker->check([10 => $cm], $dates, $this->make_course(0, self::DATE_FUTURE), [10]);

        $issue = current(array_filter($result[10] ?? [], fn ($i) => $i['type'] === 'r0_after_course_end'));
        $this->assertNotFalse($issue);
        $this->assertSame('error', $issue['severity']); // Stays error, not beyond.
    }
}
