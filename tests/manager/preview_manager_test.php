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
 * Behaviour tests for the productive preview_manager.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\manager;

use local_coursectrl\local\dto\preview_change;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\manager\preview_manager::class)]
/**
 * Verifies course-wide preview aggregation across the three productive
 * adapters (assign, quiz, feedback) introduced in patches 018-022. Uses
 * live registry discovery, not DI override, so any breakage of the
 * subplugin discovery chain surfaces here as well.
 *
 * @covers \local_coursectrl\manager\preview_manager
 */
final class preview_manager_test extends \advanced_testcase {
    /** @var int Reference timestamp used by all date-bearing fixtures. */
    private const BASE_TIME = 1700000000;

    /** @var int One-day delta in seconds. */
    private const ONE_DAY = 86400;

    /**
     * Helper that creates a course with one assign, one quiz and one
     * feedback instance, all with valid timeopen / due dates set.
     *
     * @return array{courseid: int, assign_cmid: int, quiz_cmid: int, feedback_cmid: int}
     */
    private function create_mixed_course(): array {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->get_plugin_generator('mod_assign')->create_instance([
            'course'   => $course->id,
            'name'     => 'A1',
            'duedate'  => self::BASE_TIME,
        ]);
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course'    => $course->id,
            'name'      => 'Q1',
            'timeopen'  => self::BASE_TIME,
            'timeclose' => self::BASE_TIME + self::ONE_DAY,
        ]);
        $feedback = $this->getDataGenerator()->get_plugin_generator('mod_feedback')->create_instance([
            'course'    => $course->id,
            'name'      => 'F1',
            'timeopen'  => self::BASE_TIME,
            'timeclose' => self::BASE_TIME + self::ONE_DAY,
        ]);
        return [
            'courseid'      => (int)$course->id,
            'assign_cmid'   => (int)$assign->cmid,
            'quiz_cmid'     => (int)$quiz->cmid,
            'feedback_cmid' => (int)$feedback->cmid,
        ];
    }

    /**
     * Single-adapter call: build with one assign cmid produces a single
     * preview_change with the four assign date fields.
     * @covers \local_coursectrl\manager\preview_manager
     */
    public function test_single_adapter_assign(): void {
        $this->resetAfterTest();
        $fixture = $this->create_mixed_course();
        $manager = new preview_manager();
        $result = $manager->build(
            $fixture['courseid'],
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [$fixture['assign_cmid']]
        );
        $this->assertCount(1, $result['changes']);
        $this->assertSame([], $result['skipped']);
        $this->assertSame([], $result['errors']);
        $change = $result['changes'][$fixture['assign_cmid']];
        $this->assertInstanceOf(preview_change::class, $change);
        $this->assertSame('mod_assign', $change->get_component());
        $this->assertSame('A1', $change->get_name());
        $this->assertArrayHasKey('duedate', $change->get_fields());
    }

    /**
     * Multi-adapter routing: build with one assign + one quiz + one
     * feedback cmid produces three correctly typed preview_changes.
     * @covers \local_coursectrl\manager\preview_manager
     */
    public function test_multi_adapter_routing(): void {
        $this->resetAfterTest();
        $fixture = $this->create_mixed_course();
        $manager = new preview_manager();
        $result = $manager->build(
            $fixture['courseid'],
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [$fixture['assign_cmid'], $fixture['quiz_cmid'], $fixture['feedback_cmid']]
        );
        $this->assertCount(3, $result['changes']);
        $this->assertSame([], $result['errors']);
        $this->assertSame(
            'mod_assign',
            $result['changes'][$fixture['assign_cmid']]->get_component()
        );
        $this->assertSame(
            'mod_quiz',
            $result['changes'][$fixture['quiz_cmid']]->get_component()
        );
        $this->assertSame(
            'mod_feedback',
            $result['changes'][$fixture['feedback_cmid']]->get_component()
        );
    }

    /**
     * Empty cmids defaults to "all CMs of all supported components" in
     * the course.
     * @covers \local_coursectrl\manager\preview_manager
     */
    public function test_empty_cmids_means_whole_course(): void {
        $this->resetAfterTest();
        $fixture = $this->create_mixed_course();
        $manager = new preview_manager();
        $result = $manager->build(
            $fixture['courseid'],
            'shift_dates',
            ['delta' => self::ONE_DAY]
        );
        $this->assertCount(3, $result['changes']);
        $this->assertArrayHasKey($fixture['assign_cmid'], $result['changes']);
        $this->assertArrayHasKey($fixture['quiz_cmid'], $result['changes']);
        $this->assertArrayHasKey($fixture['feedback_cmid'], $result['changes']);
    }

    /**
     * cmids that have no registered adapter are reported as skipped with
     * reason 'no_adapter', not as errors.
     * @covers \local_coursectrl\manager\preview_manager
     */
    public function test_cmid_without_adapter_is_skipped(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $label = $this->getDataGenerator()->create_module('label', [
            'course' => $course->id,
            'name'   => 'L1',
        ]);
        $manager = new preview_manager();
        $result = $manager->build(
            (int)$course->id,
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [(int)$label->cmid]
        );
        $this->assertSame([], $result['changes']);
        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame((int)$label->cmid, $result['skipped'][0]['cmid']);
        $this->assertSame('no_adapter', $result['skipped'][0]['reason']);
    }

    /**
     * cmids whose adapter does not advertise the requested action are
     * reported as skipped with reason 'unsupported_action'.
     * @covers \local_coursectrl\manager\preview_manager
     */
    public function test_cmid_with_unsupported_action_is_skipped(): void {
        $this->resetAfterTest();
        $fixture = $this->create_mixed_course();
        $manager = new preview_manager();
        $result = $manager->build(
            $fixture['courseid'],
            'set_visibility',
            ['visible' => 1],
            [$fixture['assign_cmid']]
        );
        $this->assertSame([], $result['changes']);
        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame($fixture['assign_cmid'], $result['skipped'][0]['cmid']);
        $this->assertSame('unsupported_action', $result['skipped'][0]['reason']);
    }

    /**
     * Validation errors from the adapter end up in 'errors', not in
     * 'changes', and the changes list is empty.
     * @covers \local_coursectrl\manager\preview_manager
     */
    public function test_validation_errors_are_reported(): void {
        $this->resetAfterTest();
        $fixture = $this->create_mixed_course();
        $manager = new preview_manager();
        $result = $manager->build(
            $fixture['courseid'],
            'shift_dates',
            ['delta' => 'tomorrow'],
            [$fixture['assign_cmid']]
        );
        $this->assertSame([], $result['changes']);
        $this->assertNotEmpty($result['errors']);
        $this->assertSame($fixture['assign_cmid'], $result['errors'][0]['cmid']);
        $this->assertSame('mod_assign', $result['errors'][0]['component']);
        $this->assertSame('invalid_delta', $result['errors'][0]['code']);
    }

    /**
     * The summary block reflects the correct counts for changes, skipped
     * and errors after a mixed run.
     * @covers \local_coursectrl\manager\preview_manager
     */
    public function test_summary_counts(): void {
        $this->resetAfterTest();
        $fixture = $this->create_mixed_course();
        $label = $this->getDataGenerator()->create_module('label', [
            'course' => $fixture['courseid'],
            'name'   => 'L1',
        ]);
        $manager = new preview_manager();
        $result = $manager->build(
            $fixture['courseid'],
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [
                $fixture['assign_cmid'],
                $fixture['quiz_cmid'],
                (int)$label->cmid,
            ]
        );
        $this->assertSame(3, $result['summary']['total']);
        $this->assertSame(2, $result['summary']['changes']);
        $this->assertSame(1, $result['summary']['skipped']);
        $this->assertSame(0, $result['summary']['errors']);
    }

    /**
     * Cross-component routing: cmids from different adapters in the same
     * call are dispatched to the correct adapter and produce per-component
     * preview_changes whose fields match each adapter's field map.
     * @covers \local_coursectrl\manager\preview_manager
     */
    public function test_cross_component_routing_uses_correct_field_maps(): void {
        $this->resetAfterTest();
        $fixture = $this->create_mixed_course();
        $manager = new preview_manager();
        $result = $manager->build(
            $fixture['courseid'],
            'shift_dates',
            ['delta' => self::ONE_DAY],
            [$fixture['assign_cmid'], $fixture['quiz_cmid']]
        );
        $assignchange = $result['changes'][$fixture['assign_cmid']];
        $quizchange = $result['changes'][$fixture['quiz_cmid']];
        $this->assertArrayHasKey('duedate', $assignchange->get_fields());
        $this->assertArrayNotHasKey('timeopen', $assignchange->get_fields());
        $this->assertArrayHasKey('timeopen', $quizchange->get_fields());
        $this->assertArrayNotHasKey('duedate', $quizchange->get_fields());
    }

    /**
     * The preview result echoes back action and payload for downstream
     * consumers (UI, batch_manager) that need to bind a preview to its
     * intended execute call.
     * @covers \local_coursectrl\manager\preview_manager
     */
    public function test_result_carries_action_and_payload(): void {
        $this->resetAfterTest();
        $fixture = $this->create_mixed_course();
        $manager = new preview_manager();
        $result = $manager->build(
            $fixture['courseid'],
            'shift_dates',
            ['delta' => 12345],
            [$fixture['assign_cmid']]
        );
        $this->assertSame('shift_dates', $result['action']);
        $this->assertSame(['delta' => 12345], $result['payload']);
    }

    // Target-based preview tests.
    /**
     * Target-based preview for a single adapter field (timeopen) must not
     * include other fields of the same CM (timeclose).
     *
     * @covers \local_coursectrl\manager\preview_manager
     */
    public function test_target_preview_single_adapter_field(): void {
        $this->resetAfterTest();
        $fixture = $this->create_mixed_course();

        $manager = new preview_manager();
        $payload = [
            'delta'   => self::ONE_DAY,
            'targets' => [
                [
                    'cmid'      => $fixture['quiz_cmid'],
                    'source'    => 'adapter',
                    'field'     => 'timeopen',
                    'timestamp' => self::BASE_TIME,
                ],
            ],
        ];
        $result = $manager->build($fixture['courseid'], 'shift_dates', $payload);

        $this->assertCount(1, $result['changes'], 'Exactly one CMID must be in the preview');
        $change = array_values($result['changes'])[0];
        $fields = $change->get_fields();
        $this->assertArrayHasKey('timeopen', $fields, 'timeopen must appear in preview');
        $this->assertArrayNotHasKey('timeclose', $fields, 'timeclose must NOT appear when not targeted');
    }

    /**
     * Target-based preview for completionexpected on an adapter-capable CM
     * must include completionexpected and not duedate.
     *
     * @covers \local_coursectrl\manager\preview_manager
     */
    public function test_target_preview_cm_level_on_adapter_cm(): void {
        $this->resetAfterTest();
        global $DB;
        $fixture = $this->create_mixed_course();

        // Set completionexpected on the assign CM.
        $DB->set_field('course_modules', 'completionexpected', self::BASE_TIME, ['id' => $fixture['assign_cmid']]);

        $manager = new preview_manager();
        $payload = [
            'delta'   => self::ONE_DAY,
            'targets' => [
                [
                    'cmid'      => $fixture['assign_cmid'],
                    'source'    => 'cm',
                    'field'     => 'completionexpected',
                    'timestamp' => self::BASE_TIME,
                ],
            ],
        ];
        $result = $manager->build($fixture['courseid'], 'shift_dates', $payload);

        $this->assertCount(1, $result['changes'], 'Exactly one preview change expected');
        $change = array_values($result['changes'])[0];
        $fields = $change->get_fields();
        $this->assertArrayHasKey('completionexpected', $fields, 'completionexpected must be in preview');
        $this->assertArrayNotHasKey('duedate', $fields, 'duedate must NOT appear when only cm-level target');
        // Old value must match what was set.
        $this->assertSame(self::BASE_TIME, $fields['completionexpected']['old']);
        $this->assertSame(self::BASE_TIME + self::ONE_DAY, $fields['completionexpected']['new']);
    }

    /**
     * When a CMID has both adapter and CM-level targets, both must appear
     * in the preview — not one silently overwriting the other.
     *
     * @covers \local_coursectrl\manager\preview_manager
     */
    public function test_target_preview_merged_adapter_and_cm_for_same_cmid(): void {
        $this->resetAfterTest();
        global $DB;
        $fixture = $this->create_mixed_course();
        $DB->set_field('course_modules', 'completionexpected', self::BASE_TIME, ['id' => $fixture['assign_cmid']]);

        $manager = new preview_manager();
        $payload = [
            'delta'   => self::ONE_DAY,
            'targets' => [
                [
                    'cmid' => $fixture['assign_cmid'],
                    'source' => 'adapter',
                    'field' => 'duedate',
                    'timestamp' => self::BASE_TIME,
                ],
                [
                    'cmid' => $fixture['assign_cmid'],
                    'source' => 'cm',
                    'field' => 'completionexpected',
                    'timestamp' => self::BASE_TIME,
                ],
            ],
        ];
        $result = $manager->build($fixture['courseid'], 'shift_dates', $payload);

        // Both fields must survive — no silent overwrite.
        $this->assertCount(1, $result['changes'], 'Single CMID must produce one merged preview change');
        $fields = array_values($result['changes'])[0]->get_fields();
        $this->assertArrayHasKey('duedate', $fields, 'duedate must be present after merge');
        $this->assertArrayHasKey('completionexpected', $fields, 'completionexpected must survive merge');
    }

    /**
     * Summary.total reflects the number of unique target CMIDs, not total targets.
     *
     * @covers \local_coursectrl\manager\preview_manager
     */
    public function test_target_preview_summary_total_equals_unique_cmids(): void {
        $this->resetAfterTest();
        global $DB;
        $fixture = $this->create_mixed_course();
        $DB->set_field('course_modules', 'completionexpected', self::BASE_TIME, ['id' => $fixture['assign_cmid']]);

        $manager = new preview_manager();
        $payload = [
            'delta'   => self::ONE_DAY,
            'targets' => [
                [
                    'cmid' => $fixture['assign_cmid'],
                    'source' => 'adapter',
                    'field' => 'duedate',
                    'timestamp' => self::BASE_TIME,
                ],
                [
                    'cmid' => $fixture['assign_cmid'],
                    'source' => 'cm',
                    'field' => 'completionexpected',
                    'timestamp' => self::BASE_TIME,
                ],
            ],
        ];
        $result = $manager->build($fixture['courseid'], 'shift_dates', $payload);

        // Two targets for the same CMID → total = 1 unique CMID.
        $this->assertSame(1, $result['summary']['total']);
        // Note: summary.changes counts CMIDs with a preview object, not fields.
        // This is a known semantic limitation — documented, not changed.
        $this->assertSame(1, $result['summary']['changes']);
    }

    /**
     * When followdeps is set in the payload, preview must include the dependent
     * activity's dates even though it was not in the original target list.
     *
     * This exercises the BFS expansion path in preview_bulk_action::execute()
     * by calling it directly with a real course graph.
     *
     * @covers \local_coursectrl\external\preview_bulk_action
     */
    public function test_followdeps_expands_dependent_activities_in_preview(): void {
        $this->resetAfterTest();
        global $DB;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $assign = $this->getDataGenerator()->get_plugin_generator('mod_assign')->create_instance([
            'course'  => $course->id,
            'name'    => 'ActivityA',
            'duedate' => self::BASE_TIME,
        ]);
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course'   => $course->id,
            'name'     => 'ActivityB',
            'timeopen' => self::BASE_TIME + self::ONE_DAY,
        ]);

        // Make quiz depend on assign completion.
        $avail = json_encode([
            'op'    => '&',
            'c'     => [['type' => 'completion', 'cm' => (int) $assign->cmid, 'e' => 1]],
            'showc' => [true],
        ]);
        $DB->set_field('course_modules', 'availability', $avail, ['id' => $quiz->cmid]);
        rebuild_course_cache((int) $course->id, true);

        // Preview with followdeps via the external function (which does BFS expansion).
        $this->setAdminUser();
        $payload = [
            'delta'      => self::ONE_DAY,
            'followdeps' => 1,
            'targets'    => [
                [
                    'cmid'      => (int) $assign->cmid,
                    'source'    => 'adapter',
                    'field'     => 'duedate',
                    'timestamp' => self::BASE_TIME,
                ],
            ],
        ];
        $result = \local_coursectrl\external\preview_bulk_action::execute(
            (int) $course->id,
            'shift_dates',
            json_encode($payload),
            [(int) $assign->cmid]
        );

        // Both activities must appear in the preview.
        $previewcmids = array_column($result['changes'], 'cmid');
        $this->assertContains(
            (int) $assign->cmid,
            $previewcmids,
            'ActivityA (the shifted activity) must appear in preview'
        );
        $this->assertContains(
            (int) $quiz->cmid,
            $previewcmids,
            'ActivityB (depends on A via completion) must appear in preview when followdeps is set'
        );
    }
}
