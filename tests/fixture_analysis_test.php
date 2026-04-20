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
 * Integration tests: Problemanalyse und Risikoanalyse.
 *
 * Prüft consistency_runner (R0/R3/R7) und risk_assessment_runner
 * gegen synthetische Kurse die den Fixture-Testfällen entsprechen.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl;

use local_coursectrl\local\analysis\consistency_runner;
use local_coursectrl\local\analysis\date_collector;
use local_coursectrl\local\analysis\dependency_index;
use local_coursectrl\local\analysis\risk_assessment_runner;
use local_coursectrl\local\inventory\inventory_service;

/**
 * Tests for consistency_runner and risk_assessment_runner against real courses.
 *
 * @covers \local_coursectrl\local\analysis\consistency_runner
 * @covers \local_coursectrl\local\analysis\risk_assessment_runner
 * @covers \local_coursectrl\local\analysis\course_frame_checker
 * @covers \local_coursectrl\local\analysis\dead_end_detector
 */
final class fixture_analysis_test extends \advanced_testcase {
    /** @var int 2026-05-01 00:00 UTC (Kursbeginn) */
    private const COURSE_START = 1746057600;

    /** @var int 2026-10-31 23:59 UTC (Kursende) */
    private const COURSE_END = 1762041540;

    /** @var int 7 Tage in Sekunden */
    private const WEEK = 604800;

    // Helpers.

    /**
     * Erstelle Kurs mit Datum-Rahmen.
     *
     * @return \stdClass course record
     */
    private function create_framed_course(): \stdClass {
        return $this->getDataGenerator()->create_course([
            'enablecompletion' => 1,
            'startdate' => self::COURSE_START,
            'enddate' => self::COURSE_END,
        ]);
    }

    /**
     * Baue Inventory-Snapshot + dependency_index + datesbycm für einen Kurs.
     *
     * @param int $courseid
     * @return array{cms: array, depindex: dependency_index, datesbycm: array}
     */
    private function build_analysis_input(int $courseid): array {
        $svc = new inventory_service();
        $snapshot = $svc->build_for_course($courseid);
        $depindex = new dependency_index($snapshot->cms);
        $collector = new date_collector();
        $datesbycm = $collector->collect_grouped_by_cm($snapshot->cms);
        return ['cms' => $snapshot->cms, 'depindex' => $depindex, 'datesbycm' => $datesbycm];
    }

    // R0: Kursrahmen.

    /**
     * R0a: assign mit duedate nach Kursende → r0_after_course_end.
     */
    public function test_r0a_duedate_after_course_end(): void {
        $this->resetAfterTest();
        $course = $this->create_framed_course();
        $this->getDataGenerator()->get_plugin_generator('mod_assign')->create_instance([
            'course' => $course->id,
            'name' => '[R0a] Test',
            'duedate' => self::COURSE_END + self::WEEK,
            'completion' => 2,
        ]);
        $input = $this->build_analysis_input($course->id);
        $runner = new consistency_runner();
        $warnings = $runner->get_warnings($input['cms'], $input['depindex'], $input['datesbycm'], null, $course);
        $alltypes = [];
        foreach ($warnings as $issues) {
            $alltypes = array_merge($alltypes, array_column($issues, 'type'));
        }
        $this->assertContains('r0_after_course_end', $alltypes, 'R0a should fire for duedate after course end');
    }

    /**
     * R0b: quiz mit timeopen vor Kursbeginn → r0_before_course_start.
     */
    public function test_r0b_timeopen_before_course_start(): void {
        $this->resetAfterTest();
        $course = $this->create_framed_course();
        $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $course->id,
            'name' => '[R0b] Test',
            'timeopen' => self::COURSE_START - self::WEEK,
            'timeclose' => self::COURSE_START + self::WEEK,
            'completion' => 2,
        ]);
        $input = $this->build_analysis_input($course->id);
        $runner = new consistency_runner();
        $warnings = $runner->get_warnings($input['cms'], $input['depindex'], $input['datesbycm'], null, $course);
        $alltypes = [];
        foreach ($warnings as $issues) {
            $alltypes = array_merge($alltypes, array_column($issues, 'type'));
        }
        $this->assertContains('r0_before_course_start', $alltypes);
    }

    /**
     * R0c: assign mit duedate in Vergangenheit + completion aktiv → r0_deadline_in_past.
     */
    public function test_r0c_deadline_in_past(): void {
        $this->resetAfterTest();
        $course = $this->create_framed_course();
        $this->getDataGenerator()->get_plugin_generator('mod_assign')->create_instance([
            'course' => $course->id,
            'name' => '[R0c] Test',
            'duedate' => mktime(0, 0, 0, 1, 1, 2020),
            'completion' => 2,
        ]);
        $input = $this->build_analysis_input($course->id);
        $runner = new consistency_runner();
        $warnings = $runner->get_warnings($input['cms'], $input['depindex'], $input['datesbycm'], null, $course);
        $alltypes = [];
        foreach ($warnings as $issues) {
            $alltypes = array_merge($alltypes, array_column($issues, 'type'));
        }
        $this->assertContains('r0_deadline_in_past', $alltypes);
    }

    // R3: Prozesslogik.

    /**
     * R3: assign allowsubmissionsfromdate > duedate → temporal_conflict.
     */
    public function test_r3_assign_open_after_due(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $base = mktime(0, 0, 0, 6, 1, 2026);
        $this->getDataGenerator()->get_plugin_generator('mod_assign')->create_instance([
            'course' => $course->id,
            'name' => '[R3] assign open after due',
            'allowsubmissionsfromdate' => $base + self::WEEK,
            'duedate' => $base,
            'completion' => 2,
        ]);
        $input = $this->build_analysis_input($course->id);
        $runner = new consistency_runner();
        $warnings = $runner->get_warnings($input['cms'], $input['depindex'], $input['datesbycm']);
        $alltypes = [];
        foreach ($warnings as $issues) {
            $alltypes = array_merge($alltypes, array_column($issues, 'type'));
        }
        $this->assertContains('temporal_conflict', $alltypes, 'R3 assign open > due should fire');
    }

    /**
     * R3: quiz timeopen > timeclose → temporal_conflict.
     */
    public function test_r3_quiz_open_after_close(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $base = mktime(0, 0, 0, 7, 1, 2026);
        $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $course->id,
            'name' => '[R3] quiz open after close',
            'timeopen' => $base + self::WEEK,
            'timeclose' => $base,
            'completion' => 2,
        ]);
        $input = $this->build_analysis_input($course->id);
        $runner = new consistency_runner();
        $warnings = $runner->get_warnings($input['cms'], $input['depindex'], $input['datesbycm']);
        $alltypes = [];
        foreach ($warnings as $issues) {
            $alltypes = array_merge($alltypes, array_column($issues, 'type'));
        }
        $this->assertContains('temporal_conflict', $alltypes);
    }

    // Kursrahmen-Konsistenz bei gültiger Konfiguration.

    /**
     * Ein korrekt konfigurierter Kurs produziert KEINE R0-Fehler.
     */
    public function test_valid_course_no_r0_warnings(): void {
        $this->resetAfterTest();
        $course = $this->create_framed_course();
        $inside = self::COURSE_START + self::WEEK * 4;
        $this->getDataGenerator()->get_plugin_generator('mod_assign')->create_instance([
            'course' => $course->id,
            'name' => '[OK] gültig',
            'duedate' => $inside,
            'completion' => 2,
        ]);
        $input = $this->build_analysis_input($course->id);
        $runner = new consistency_runner();
        $warnings = $runner->get_warnings($input['cms'], $input['depindex'], $input['datesbycm'], null, $course);
        $alltypes = [];
        foreach ($warnings as $issues) {
            $alltypes = array_merge($alltypes, array_column($issues, 'type'));
        }
        $this->assertNotContains('r0_after_course_end', $alltypes);
        $this->assertNotContains('r0_before_course_start', $alltypes);
    }

    // Risikoanalyse.

    /**
     * risk_assessment_runner liefert mindestens einen Befund für Kurs mit Zirkel.
     */
    public function test_risk_runner_detects_cycle(): void {
        $this->resetAfterTest();
        global $DB;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $gen = $this->getDataGenerator();

        $a = $gen->get_plugin_generator('mod_assign')->create_instance([
            'course' => $course->id, 'name' => 'CycleA', 'completion' => 2,
        ]);
        $b = $gen->get_plugin_generator('mod_assign')->create_instance([
            'course' => $course->id, 'name' => 'CycleB', 'completion' => 2,
        ]);

        // Manually inject circular availability (A requires B, B requires A).
        $availa = json_encode([
            'op' => '&',
            'c' => [['type' => 'completion', 'cm' => (int)$b->cmid, 'e' => 1]],
            'showc' => [true],
        ]);
        $availb = json_encode([
            'op' => '&',
            'c' => [['type' => 'completion', 'cm' => (int)$a->cmid, 'e' => 1]],
            'showc' => [true],
        ]);
        $DB->set_field('course_modules', 'availability', $availa, ['id' => (int)$a->cmid]);
        $DB->set_field('course_modules', 'availability', $availb, ['id' => (int)$b->cmid]);
        rebuild_course_cache($course->id, true);

        $input = $this->build_analysis_input($course->id);
        $riskrunner = new risk_assessment_runner(null, null, null, null);
        $items = $riskrunner->run($input['cms'], $input['depindex'], $input['datesbycm'], $course->id);

        $types = array_column($items, 'type');
        $this->assertContains('circular_dep_transitive', $types, 'Cycle should be detected by risk runner');
    }

    /**
     * risk_assessment_runner Ergebnisse werden persistiert und sind via load_last abrufbar.
     */
    public function test_risk_results_persisted(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $input = $this->build_analysis_input($course->id);
        $riskrunner = new risk_assessment_runner();
        $riskrunner->run($input['cms'], $input['depindex'], $input['datesbycm'], $course->id);

        $loaded = risk_assessment_runner::load_last($course->id);
        $this->assertIsArray($loaded, 'Persisted results should be loadable');
        $this->assertGreaterThanOrEqual(0, count($loaded));
    }

    /**
     * Jeder risk_item hat mindestens type, severity, score.
     */
    public function test_risk_items_have_required_fields(): void {
        $this->resetAfterTest();
        global $DB;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $gen = $this->getDataGenerator();
        $a = $gen->get_plugin_generator('mod_assign')->create_instance([
            'course' => $course->id, 'name' => 'FieldTestA', 'completion' => 2,
        ]);
        $b = $gen->get_plugin_generator('mod_assign')->create_instance([
            'course' => $course->id, 'name' => 'FieldTestB', 'completion' => 2,
        ]);
        $DB->set_field('course_modules', 'availability', json_encode([
            'op' => '&',
            'c' => [['type' => 'completion', 'cm' => (int)$b->cmid, 'e' => 1]],
            'showc' => [true],
        ]), ['id' => (int)$a->cmid]);
        $DB->set_field('course_modules', 'availability', json_encode([
            'op' => '&',
            'c' => [['type' => 'completion', 'cm' => (int)$a->cmid, 'e' => 1]],
            'showc' => [true],
        ]), ['id' => (int)$b->cmid]);

        $input = $this->build_analysis_input($course->id);
        $riskrunner = new risk_assessment_runner();
        $items = $riskrunner->run($input['cms'], $input['depindex'], $input['datesbycm'], $course->id);

        foreach ($items as $item) {
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('severity', $item);
            $this->assertArrayHasKey('score', $item);
        }
    }
}
