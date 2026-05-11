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
 * Integration tests: Lernenden-Simulation.
 *
 * Tests for visibility_simulator and condition_evaluator against courses
 * mit Gruppen-, Abschluss- und Datum-Bedingungen.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl;

use local_coursectrl\local\simulation\visibility_simulator;
use local_coursectrl\local\simulation\learner_state;
use local_coursectrl\local\simulation\condition_evaluator;
use local_coursectrl\local\inventory\inventory_service;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\simulation\visibility_simulator::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\simulation\condition_evaluator::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\simulation\learner_state::class)]
/**
 * Tests for visibility_simulator and condition_evaluator.
 *
 * @covers \local_coursectrl\local\simulation\visibility_simulator
 * @covers \local_coursectrl\local\simulation\condition_evaluator
 * @covers \local_coursectrl\local\simulation\learner_state
 */
final class fixture_simulation_test extends \advanced_testcase {
    /** @var int Future timestamp (2026-06-15) */
    private const T_FUTURE = 1750032000;

    /** @var int Past timestamp (2020-01-01) */
    private const T_PAST = 1577836800;

    // Helpers.

    /**
     * Build cms array from course via inventory_service.
     *
     * @param int $courseid
     * @return array<int, \local_coursectrl\local\entity\cm_item>
     */
    private function get_cms(int $courseid): array {
        $svc = new inventory_service();
        return $svc->build_for_course($courseid)->cms;
    }

    /**
     * Create availability JSON requiring completion of a CM.
     *
     * @param int $cmid
     * @return string
     */
    private function avail_requires_completion(int $cmid): string {
        return json_encode([
            'op' => '&',
            'c' => [['type' => 'completion', 'cm' => $cmid, 'e' => 1]],
            'showc' => [true],
        ]);
    }

    /**
     * Create availability JSON requiring group membership.
     *
     * @param int $groupid
     * @return string
     */
    private function avail_requires_group(int $groupid): string {
        return json_encode([
            'op' => '&',
            'c' => [['type' => 'group', 'id' => $groupid]],
            'showc' => [true],
        ]);
    }

    /**
     * Create availability JSON requiring date >= ts.
     *
     * @param int $ts
     * @return string
     */
    private function avail_requires_date(int $ts): string {
        return json_encode([
            'op' => '&',
            'c' => [['type' => 'date', 'd' => '>=', 't' => $ts]],
            'showc' => [true],
        ]);
    }

    // Simulation: basic cases.

    /**
     * A CM without conditions is accessible to all learners.
     * @covers \local_coursectrl\local\simulation\visibility_simulator
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @covers \local_coursectrl\local\simulation\learner_state
     */
    public function test_cm_without_restrictions_accessible(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $assign = $this->getDataGenerator()->get_plugin_generator('mod_assign')
            ->create_instance(['course' => $course->id, 'completion' => 2]);

        $cms = $this->get_cms($course->id);
        $state = new learner_state(self::T_FUTURE, [], [], []);
        $sim = new visibility_simulator();
        $results = $sim->simulate($cms, $state);

        $cmid = (int) $assign->cmid;
        $this->assertArrayHasKey($cmid, $results);
        $this->assertTrue($results[(int)$assign->cmid]['accessible'], 'CM without restrictions should be accessible');
    }

    /**
     * A hidden CM is not accessible to learners.
     * @covers \local_coursectrl\local\simulation\visibility_simulator
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @covers \local_coursectrl\local\simulation\learner_state
     */
    public function test_hidden_cm_not_accessible(): void {
        $this->resetAfterTest();
        global $DB;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $assign = $this->getDataGenerator()->get_plugin_generator('mod_assign')
            ->create_instance(['course' => $course->id, 'completion' => 2, 'visible' => 0]);

        $cms = $this->get_cms($course->id);
        $state = new learner_state(self::T_FUTURE, [], [], []);
        $sim = new visibility_simulator();
        $results = $sim->simulate($cms, $state);

        $cmid = (int) $assign->cmid;
        $this->assertFalse($results[(int)$assign->cmid]['accessible'], 'Hidden CM should not be accessible');
        $this->assertSame(condition_evaluator::STATUS_FAIL, $results[(int)$assign->cmid]['status']);
    }

    // Simulation: Abschluss-Bedingungen.

    /**
     * CM with completion condition: prerequisite NOT met → not accessible.
     * @covers \local_coursectrl\local\simulation\visibility_simulator
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @covers \local_coursectrl\local\simulation\learner_state
     */
    public function test_completion_dep_not_met_blocks_access(): void {
        $this->resetAfterTest();
        ['courseid' => $courseid, 'prereqcmid' => $prereqcmid, 'dependentcmid' => $dependentcmid] =
            $this->create_course_with_completion_pair();
        $cms = $this->get_cms($courseid);
        // Learner has NOT completed the prereq.
        $state = new learner_state(self::T_FUTURE, [], [], []);
        $sim = new visibility_simulator();
        $results = $sim->simulate($cms, $state);

        $this->assertFalse(
            $results[$dependentcmid]['accessible'],
            'CM should be locked when completion prerequisite is not met'
        );
    }

    /**
     * CM with completion condition: prerequisite met → accessible.
     * @covers \local_coursectrl\local\simulation\visibility_simulator
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @covers \local_coursectrl\local\simulation\learner_state
     */
    public function test_completion_dep_met_grants_access(): void {
        $this->resetAfterTest();
        ['courseid' => $courseid, 'prereqcmid' => $prereqcmid, 'dependentcmid' => $dependentcmid] =
            $this->create_course_with_completion_pair();
        $cms = $this->get_cms($courseid);
        // Learner HAS completed the prereq (state=1).
        $state = new learner_state(self::T_FUTURE, [$prereqcmid => 1], [], []);
        $sim = new visibility_simulator();
        $results = $sim->simulate($cms, $state);

        $this->assertTrue(
            $results[$dependentcmid]['accessible'],
            'CM should be accessible when completion prerequisite is met'
        );
    }



    /**
     * Create a course with a prereq assign and a dependent assign
     * whose availability requires completion of the prereq.
     *
     * @return array{courseid:int, prereqcmid:int, dependentcmid:int}
     */
    private function create_course_with_completion_pair(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $gen = $this->getDataGenerator();
        $prereq = $gen->get_plugin_generator('mod_assign')
            ->create_instance(['course' => $course->id, 'completion' => 2]);
        $dependent = $gen->get_plugin_generator('mod_assign')
            ->create_instance(['course' => $course->id, 'completion' => 2]);
        $DB->set_field(
            'course_modules',
            'availability',
            $this->avail_requires_completion((int)$prereq->cmid),
            ['id' => (int)$dependent->cmid]
        );
        rebuild_course_cache($course->id, true);
        return [
            'courseid'      => (int)$course->id,
            'prereqcmid'    => (int)$prereq->cmid,
            'dependentcmid' => (int)$dependent->cmid,
        ];
    }

    /**
     * Create a course with one group and one assign whose availability
     * requires membership in that group.
     *
     * @return array{courseid:int, groupid:int, cmid:int}
     */
    private function create_course_with_group_assign(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $assign = $this->getDataGenerator()->get_plugin_generator('mod_assign')
            ->create_instance(['course' => $course->id, 'completion' => 2]);
        $DB->set_field(
            'course_modules',
            'availability',
            $this->avail_requires_group((int)$group->id),
            ['id' => (int)$assign->cmid]
        );
        rebuild_course_cache($course->id, true);
        return [
            'courseid' => (int)$course->id,
            'groupid'  => (int)$group->id,
            'cmid'     => (int)$assign->cmid,
        ];
    }

    // Simulation: Gruppen-Bedingungen.

    /**
     * CM with group condition: learner in the required group → accessible.
     * @covers \local_coursectrl\local\simulation\visibility_simulator
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @covers \local_coursectrl\local\simulation\learner_state
     */
    public function test_group_condition_met_grants_access(): void {
        $this->resetAfterTest();
        ['courseid' => $courseid, 'groupid' => $groupid, 'cmid' => $cmid] =
            $this->create_course_with_group_assign();
        $cms = $this->get_cms($courseid);
        $state = new learner_state(self::T_FUTURE, [], [$groupid], []);
        $sim = new visibility_simulator();
        $results = $sim->simulate($cms, $state);

        $this->assertTrue(
            $results[$cmid]['accessible'],
            'CM should be accessible when learner is in required group'
        );
    }

    /**
     * CM with group condition: learner in the wrong group → not accessible.
     * @covers \local_coursectrl\local\simulation\visibility_simulator
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @covers \local_coursectrl\local\simulation\learner_state
     */
    public function test_group_condition_not_met_blocks(): void {
        $this->resetAfterTest();
        ['courseid' => $courseid, 'cmid' => $cmid] =
            $this->create_course_with_group_assign();
        $cms = $this->get_cms($courseid);
        // Learner is in a different group.
        $state = new learner_state(self::T_FUTURE, [], [9999], []);
        $sim = new visibility_simulator();
        $results = $sim->simulate($cms, $state);

        $this->assertFalse(
            $results[$cmid]['accessible'],
            'CM should be locked for learner not in required group'
        );
    }

    // Simulation: Datum-Bedingungen.

    /**
     * CM mit Datum-Bedingung in der Zukunft: Datum noch nicht erreicht → gesperrt.
     * @covers \local_coursectrl\local\simulation\visibility_simulator
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @covers \local_coursectrl\local\simulation\learner_state
     */
    public function test_date_condition_future_blocks(): void {
        $this->resetAfterTest();
        global $DB;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $assign = $this->getDataGenerator()->get_plugin_generator('mod_assign')
            ->create_instance(['course' => $course->id, 'completion' => 2]);

        $DB->set_field(
            'course_modules',
            'availability',
            $this->avail_requires_date(self::T_FUTURE + 86400),
            ['id' => (int)$assign->cmid]
        );
        rebuild_course_cache($course->id, true);

        $cms = $this->get_cms($course->id);
        // Simulationszeitpunkt VOR dem Freigabedatum.
        $state = new learner_state(self::T_PAST, [], [], []);
        $sim = new visibility_simulator();
        $results = $sim->simulate($cms, $state);

        $this->assertFalse($results[(int)$assign->cmid]['accessible'], 'CM should be locked before release date');
    }

    /**
     * CM with a past date condition: date already passed → accessible.
     * @covers \local_coursectrl\local\simulation\visibility_simulator
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @covers \local_coursectrl\local\simulation\learner_state
     */
    public function test_date_condition_past_accessible(): void {
        $this->resetAfterTest();
        global $DB;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $assign = $this->getDataGenerator()->get_plugin_generator('mod_assign')
            ->create_instance(['course' => $course->id, 'completion' => 2]);

        $DB->set_field(
            'course_modules',
            'availability',
            $this->avail_requires_date(self::T_PAST),
            ['id' => (int)$assign->cmid]
        );

        rebuild_course_cache($course->id, true);

        $cms = $this->get_cms($course->id);
        $state = new learner_state(self::T_FUTURE, [], [], []);
        $sim = new visibility_simulator();
        $results = $sim->simulate($cms, $state);

        $this->assertTrue(
            $results[(int)$assign->cmid]['accessible'],
            'CM should be accessible after release date has passed'
        );
    }

    // Simulation: result shape.

    /**
     * Jedes Simulationsergebnis hat die Pflichtfelder.
     * @covers \local_coursectrl\local\simulation\visibility_simulator
     * @covers \local_coursectrl\local\simulation\condition_evaluator
     * @covers \local_coursectrl\local\simulation\learner_state
     */
    public function test_simulation_result_has_required_keys(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $this->getDataGenerator()->get_plugin_generator('mod_assign')
            ->create_instance(['course' => $course->id, 'completion' => 2]);

        $cms = $this->get_cms($course->id);
        $state = new learner_state(self::T_FUTURE, [], [], []);
        $sim = new visibility_simulator();
        $results = $sim->simulate($cms, $state);

        foreach ($results as $r) {
            $this->assertArrayHasKey('cmid', $r);
            $this->assertArrayHasKey('accessible', $r);
            $this->assertArrayHasKey('status', $r);
            $this->assertArrayHasKey('reasons', $r);
            $this->assertIsArray($r['reasons']);
        }
    }
}
