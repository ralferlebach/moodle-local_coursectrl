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
 * Tests for temporal_conflict_detector.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;

/**
 * Unit tests for temporal_conflict_detector::detect().
 *
 * @covers \local_coursectrl\local\analysis\temporal_conflict_detector
 */
final class temporal_conflict_detector_test extends \advanced_testcase {
    /** @var int Base timestamp used in tests (2026-06-01 00:00:00 UTC). */
    private const T1 = 1748736000;

    /** @var int T1 + 7 days. */
    private const T2 = 1749340800;

    /** @var int T1 + 14 days. */
    private const T3 = 1749945600;

    /**
     * Build a cm_item for a given modname.
     *
     * @param int    $cmid    CM id.
     * @param string $modname Module short name.
     * @return cm_item
     */
    private function make_cm(int $cmid, string $modname): cm_item {
        return new cm_item($cmid, 1, 10, $modname, $cmid, 'Activity ' . $cmid, true, null, 2);
    }

    /**
     * Build a datesbycm entry for a single adapter-sourced field.
     *
     * @param int    $cmid      CM id.
     * @param string $field     Field name.
     * @param int    $timestamp Unix timestamp.
     * @return array
     */
    private function make_entry(int $cmid, string $field, int $timestamp): array {
        return [
            'cmid' => $cmid,
            'field' => $field,
            'fieldlabel' => $field,
            'timestamp' => $timestamp,
            'source' => 'adapter',
        ];
    }

    /**
     * No conflicts when there are no adapter-sourced date entries.
     */
    public function test_no_entries_returns_empty(): void {
        $this->resetAfterTest();
        $detector = new temporal_conflict_detector();
        $cms = [1 => $this->make_cm(1, 'assign')];
        $result = $detector->detect($cms, []);
        $this->assertEmpty($result);
    }

    /**
     * No conflicts when only one of the two paired fields is set.
     */
    public function test_single_field_set_no_conflict(): void {
        $this->resetAfterTest();
        $detector = new temporal_conflict_detector();
        $cms = [1 => $this->make_cm(1, 'assign')];
        $dates = [1 => [$this->make_entry(1, 'duedate', self::T2)]];
        $result = $detector->detect($cms, $dates);
        $this->assertEmpty($result);
    }

    /**
     * assign: no conflict when allowsubmissionsfromdate < duedate (correct order).
     */
    public function test_assign_correct_order_no_conflict(): void {
        $this->resetAfterTest();
        $detector = new temporal_conflict_detector();
        $cms = [1 => $this->make_cm(1, 'assign')];
        $dates = [
            1 => [
                $this->make_entry(1, 'allowsubmissionsfromdate', self::T1),
                $this->make_entry(1, 'duedate', self::T2),
                $this->make_entry(1, 'cutoffdate', self::T3),
            ],
        ];
        $result = $detector->detect($cms, $dates);
        $this->assertEmpty($result);
    }

    /**
     * assign: conflict when duedate < allowsubmissionsfromdate.
     */
    public function test_assign_duedate_before_open_is_conflict(): void {
        $this->resetAfterTest();
        $detector = new temporal_conflict_detector();
        $cms = [1 => $this->make_cm(1, 'assign')];
        $dates = [
            1 => [
                $this->make_entry(1, 'allowsubmissionsfromdate', self::T2),
                $this->make_entry(1, 'duedate', self::T1),
            ],
        ];
        $result = $detector->detect($cms, $dates);
        $this->assertArrayHasKey(1, $result);
        $this->assertCount(1, $result[1]);
        $conflict = $result[1][0];
        $this->assertSame('temporal_conflict', $conflict['type'] ?? 'temporal_conflict');
        $this->assertSame('allowsubmissionsfromdate', $conflict['field_early']);
        $this->assertSame('duedate', $conflict['field_late']);
        $this->assertSame(self::T2, $conflict['ts_early']);
        $this->assertSame(self::T1, $conflict['ts_late']);
    }

    /**
     * assign: conflict when cutoffdate < duedate.
     */
    public function test_assign_cutoffdate_before_duedate_is_conflict(): void {
        $this->resetAfterTest();
        $detector = new temporal_conflict_detector();
        $cms = [1 => $this->make_cm(1, 'assign')];
        $dates = [
            1 => [
                $this->make_entry(1, 'duedate', self::T2),
                $this->make_entry(1, 'cutoffdate', self::T1),
            ],
        ];
        $result = $detector->detect($cms, $dates);
        $this->assertArrayHasKey(1, $result);
        $found = array_column($result[1], 'field_early');
        $this->assertContains('duedate', $found);
    }

    /**
     * assign: conflict when gradingduedate < duedate.
     */
    public function test_assign_gradingduedate_before_duedate_is_conflict(): void {
        $this->resetAfterTest();
        $detector = new temporal_conflict_detector();
        $cms = [1 => $this->make_cm(1, 'assign')];
        $dates = [
            1 => [
                $this->make_entry(1, 'duedate', self::T2),
                $this->make_entry(1, 'gradingduedate', self::T1),
            ],
        ];
        $result = $detector->detect($cms, $dates);
        $this->assertArrayHasKey(1, $result);
        $found = array_column($result[1], 'field_late');
        $this->assertContains('gradingduedate', $found);
    }

    /**
     * quiz: conflict when timeclose < timeopen.
     */
    public function test_quiz_close_before_open_is_conflict(): void {
        $this->resetAfterTest();
        $detector = new temporal_conflict_detector();
        $cms = [2 => $this->make_cm(2, 'quiz')];
        $dates = [
            2 => [
                $this->make_entry(2, 'timeopen', self::T2),
                $this->make_entry(2, 'timeclose', self::T1),
            ],
        ];
        $result = $detector->detect($cms, $dates);
        $this->assertArrayHasKey(2, $result);
        $this->assertSame('timeopen', $result[2][0]['field_early']);
        $this->assertSame('timeclose', $result[2][0]['field_late']);
    }

    /**
     * feedback: conflict when timeclose < timeopen.
     */
    public function test_feedback_close_before_open_is_conflict(): void {
        $this->resetAfterTest();
        $detector = new temporal_conflict_detector();
        $cms = [3 => $this->make_cm(3, 'feedback')];
        $dates = [
            3 => [
                $this->make_entry(3, 'timeopen', self::T3),
                $this->make_entry(3, 'timeclose', self::T1),
            ],
        ];
        $result = $detector->detect($cms, $dates);
        $this->assertArrayHasKey(3, $result);
        $this->assertSame('timeopen', $result[3][0]['field_early']);
    }

    /**
     * Non-adapter entries (source !== 'adapter') must be ignored.
     */
    public function test_non_adapter_source_ignored(): void {
        $this->resetAfterTest();
        $detector = new temporal_conflict_detector();
        $cms = [1 => $this->make_cm(1, 'assign')];
        // Both entries out of order, but source is not 'adapter'.
        $dates = [
            1 => [
                [
                    'cmid' => 1,
                    'field' => 'allowsubmissionsfromdate',
                    'fieldlabel' => 'allowsubmissionsfromdate',
                    'timestamp' => self::T2,
                    'source' => 'cm',
                ],
                [
                    'cmid' => 1,
                    'field' => 'duedate',
                    'fieldlabel' => 'duedate',
                    'timestamp' => self::T1,
                    'source' => 'availability',
                ],
            ],
        ];
        $result = $detector->detect($cms, $dates);
        $this->assertEmpty($result);
    }

    /**
     * Modules without rules (e.g. mod_label) are skipped entirely.
     */
    public function test_unknown_component_skipped(): void {
        $this->resetAfterTest();
        $detector = new temporal_conflict_detector();
        $cms = [5 => $this->make_cm(5, 'label')];
        $dates = [
            5 => [
                $this->make_entry(5, 'somefield', self::T2),
                $this->make_entry(5, 'otherfield', self::T1),
            ],
        ];
        $result = $detector->detect($cms, $dates);
        $this->assertEmpty($result);
    }
}
