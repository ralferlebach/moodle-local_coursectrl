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
 * Integration tests: Termin-Erkennung und -Verschiebung (mit und ohne Text).
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\tests;

use local_coursectrl\local\analysis\date_collector;
use local_coursectrl\local\analysis\dependency_index;
use local_coursectrl\local\inventory\inventory_service;
use local_coursectrl\local\text\text_datetime_rewriter;
use local_coursectrl\local\text\text_datetime_extractor;
use local_coursectrl\local\text\text_datetime_parser;
use local_coursectrl\manager\batch_manager;
use local_coursectrl\manager\preview_manager;

/**
 * Tests for date detection (date_collector) and date shifting (batch_manager/preview_manager).
 *
 * Uses the Moodle data generator — no MBZ restore needed.
 *
 * @covers \local_coursectrl\local\analysis\date_collector
 * @covers \local_coursectrl\manager\preview_manager
 * @covers \local_coursectrl\manager\batch_manager
 * @covers \local_coursectrl\local\text\text_datetime_rewriter
 */
final class fixture_date_shift_test extends \advanced_testcase {
    /** @var int 2026-06-01 00:00 UTC */
    private const T_BASE = 1748736000;

    /** @var int 7 days in seconds */
    private const WEEK = 604800;

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Create course with assign + quiz having structured dates.
     *
     * @return array{courseid:int, assigncmid:int, quizcmid:int, assigniid:int, quiziid:int}
     */
    private function create_dated_course(): array {
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $gen = $this->getDataGenerator();

        $assign = $gen->get_plugin_generator('mod_assign')->create_instance([
            'course' => $course->id,
            'name' => 'Testaufgabe',
            'allowsubmissionsfromdate' => self::T_BASE,
            'duedate' => self::T_BASE + self::WEEK,
            'cutoffdate' => self::T_BASE + self::WEEK * 2,
            'completion' => 2,
        ]);
        $quiz = $gen->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $course->id,
            'name' => 'Testquiz',
            'timeopen' => self::T_BASE,
            'timeclose' => self::T_BASE + self::WEEK,
            'completion' => 2,
        ]);
        return [
            'courseid' => (int) $course->id,
            'assigncmid' => (int) $assign->cmid,
            'quizcmid' => (int) $quiz->cmid,
            'assigniid' => (int) $assign->id,
            'quiziid' => (int) $quiz->id,
        ];
    }

    // ── Termin-Erkennung (date_collector) ─────────────────────────────────────

    /**
     * date_collector findet die assign-Datumsfelder.
     */
    public function test_date_collector_finds_assign_dates(): void {
        $this->resetAfterTest();
        $data = $this->create_dated_course();
        $svc = new inventory_service();
        $snapshot = $svc->build_for_course($data['courseid']);
        $collector = new date_collector();
        $bygroup = $collector->collect_grouped_by_cm($snapshot->cms);

        $this->assertArrayHasKey($data['assigncmid'], $bygroup, 'assign CM should have date entries');
        $assigndates = $bygroup[$data['assigncmid']];
        $fields = array_column($assigndates, 'field');
        $this->assertContains('duedate', $fields);
        $this->assertContains('cutoffdate', $fields);
    }

    /**
     * date_collector findet quiz timeopen/timeclose.
     */
    public function test_date_collector_finds_quiz_dates(): void {
        $this->resetAfterTest();
        $data = $this->create_dated_course();
        $svc = new inventory_service();
        $snapshot = $svc->build_for_course($data['courseid']);
        $collector = new date_collector();
        $bygroup = $collector->collect_grouped_by_cm($snapshot->cms);

        $this->assertArrayHasKey($data['quizcmid'], $bygroup);
        $fields = array_column($bygroup[$data['quizcmid']], 'field');
        $this->assertContains('timeopen', $fields);
        $this->assertContains('timeclose', $fields);
    }

    /**
     * Datum-Einträge haben korrekte Timestamps.
     */
    public function test_date_collector_timestamp_values_correct(): void {
        $this->resetAfterTest();
        $data = $this->create_dated_course();
        $svc = new inventory_service();
        $snapshot = $svc->build_for_course($data['courseid']);
        $collector = new date_collector();
        $bygroup = $collector->collect_grouped_by_cm($snapshot->cms);

        $duedates = array_filter($bygroup[$data['assigncmid']], fn($e) => $e['field'] === 'duedate');
        $entry = reset($duedates);
        $this->assertSame(self::T_BASE + self::WEEK, (int) $entry['timestamp']);
    }

    // ── Vorschau (preview_manager) ────────────────────────────────────────────

    /**
     * preview_manager::build gibt Preview-Objekte mit old/new-Werten zurück.
     */
    public function test_preview_shift_dates_returns_changes(): void {
        $this->resetAfterTest();
        $data = $this->create_dated_course();
        $preview = new preview_manager();
        $changes = $preview->build(
            $data['courseid'],
            'shift_dates',
            ['delta' => self::WEEK],
            [$data['assigncmid'], $data['quizcmid']]
        );
        $this->assertNotEmpty($changes, 'Preview should return change objects');
        $hasassign = false;
        foreach ($changes as $c) {
            if ((int)($c['cmid'] ?? 0) === $data['assigncmid']) {
                $hasassign = true;
            }
        }
        $this->assertTrue($hasassign, 'assign should appear in preview');
    }

    /**
     * Preview zeigt old_value und new_value für duedate.
     */
    public function test_preview_shows_old_and_new_value(): void {
        $this->resetAfterTest();
        $data = $this->create_dated_course();
        $preview = new preview_manager();
        $changes = $preview->build(
            $data['courseid'],
            'shift_dates',
            ['delta' => self::WEEK],
            [$data['assigncmid']]
        );
        $duedatechanges = array_filter($changes, fn($c) => ($c['field'] ?? '') === 'duedate');
        $this->assertNotEmpty($duedatechanges, 'duedate should appear in preview');
        $c = reset($duedatechanges);
        $this->assertArrayHasKey('old_value', $c);
        $this->assertArrayHasKey('new_value', $c);
        $this->assertSame(self::T_BASE + self::WEEK, (int)$c['old_value']);
        $this->assertSame(self::T_BASE + self::WEEK * 2, (int)$c['new_value']);
    }

    // ── Termin-Verschiebung ohne Text (batch_manager) ─────────────────────────

    /**
     * batch_manager::execute verschiebt assign-Daten um 7 Tage.
     */
    public function test_batch_shift_dates_changes_duedate(): void {
        $this->resetAfterTest();
        global $DB;
        $data = $this->create_dated_course();
        $admin = get_admin();

        $batchmgr = new batch_manager();
        $batchid = $batchmgr->execute(
            $data['courseid'],
            'shift_dates',
            ['delta' => self::WEEK],
            [$data['assigncmid']],
            $admin->id
        );

        $this->assertGreaterThan(0, $batchid);
        $newdue = (int) $DB->get_field('assign', 'duedate', ['id' => $data['assigniid']]);
        $this->assertSame(self::T_BASE + self::WEEK * 2, $newdue, 'duedate should be shifted by one week');
    }

    /**
     * batch_manager verschiebt alle Felder einer Aktivität proportional.
     */
    public function test_batch_shift_all_fields_proportional(): void {
        $this->resetAfterTest();
        global $DB;
        $data = $this->create_dated_course();
        $admin = get_admin();

        $batchmgr = new batch_manager();
        $batchmgr->execute(
            $data['courseid'],
            'shift_dates',
            ['delta' => self::WEEK],
            [$data['quizcmid']],
            $admin->id
        );

        $quiz = $DB->get_record('quiz', ['id' => $data['quiziid']]);
        $this->assertSame(self::T_BASE + self::WEEK, (int)$quiz->timeopen, 'timeopen should shift by 1 week');
        $this->assertSame(self::T_BASE + self::WEEK * 2, (int)$quiz->timeclose, 'timeclose should shift by 1 week');
    }

    /**
     * batch_manager erstellt einen Snapshot (für Rollback).
     */
    public function test_batch_creates_snapshot(): void {
        $this->resetAfterTest();
        global $DB;
        $data = $this->create_dated_course();
        $admin = get_admin();

        $batchmgr = new batch_manager();
        $batchid = $batchmgr->execute(
            $data['courseid'],
            'shift_dates',
            ['delta' => self::WEEK],
            [$data['assigncmid']],
            $admin->id
        );

        $snapshots = $DB->get_records('local_coursectrl_snapshot', ['batchid' => $batchid]);
        $this->assertNotEmpty($snapshots, 'Snapshot should be created for rollback');
    }

    // ── Termin-Verschiebung MIT Text (rewriter) ───────────────────────────────

    /**
     * text_datetime_rewriter verschiebt ein ISO-Datum im Text.
     */
    public function test_rewriter_shifts_iso_date_in_text(): void {
        $this->resetAfterTest();
        $text = 'Abgabe bis 2026-05-19. Nachfrist bis 2026-05-21.';
        $extractor = new text_datetime_extractor();
        $parser = new text_datetime_parser();
        $hits = $extractor->extract($text);

        $hitrecords = [];
        foreach ($hits as $hit) {
            $normalized = $parser->normalise($hit);
            if ($normalized === null) {
                continue;
            }
            $hitrecords[] = [
                'matchedtext' => $hit['match'],
                'normalizedvalue' => $normalized,
                'contextjson' => json_encode([
                    'offset' => $hit['offset'],
                    'length' => strlen($hit['match']),
                ]),
            ];
        }

        $rewriter = new text_datetime_rewriter();
        $result = $rewriter->rewrite($text, $hitrecords, self::WEEK); // +7 Tage

        $this->assertArrayHasKey('text', $result);
        $this->assertStringContainsString('2026-05-26', $result['text'], 'Date should be shifted by 7 days');
        $this->assertNotEmpty($result['applied'], 'At least one replacement should be applied');
    }

    /**
     * text_datetime_rewriter überspringt Treffer ohne normalisierten Wert.
     */
    public function test_rewriter_skips_unnormalizable_hits(): void {
        $this->resetAfterTest();
        $rewriter = new text_datetime_rewriter();
        $hitrecords = [
            [
                'matchedtext' => 'Irgendwann',
                'normalizedvalue' => '',
                'contextjson' => json_encode(['offset' => 0, 'length' => 10]),
            ],
        ];
        $result = $rewriter->rewrite('Irgendwann muss es passieren.', $hitrecords, self::WEEK);
        $this->assertNotEmpty($result['skipped'], 'Entry without normalized value should be skipped');
    }
}
