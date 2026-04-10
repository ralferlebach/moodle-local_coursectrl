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
 * Unit tests for the snapshot persistent.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\persistent;

/**
 * Tests creation, retrieval and round-trip of snapshot rows.
 *
 * @covers \local_coursectrl\local\persistent\snapshot
 */
final class snapshot_test extends \advanced_testcase
{
    /**
     * A persisted snapshot can be reloaded by id.
     */
    public function test_create_and_reload(): void
    {
        $this->resetAfterTest();
        $batchid = $this->make_parent_batch();
        $statejson = json_encode([
            'component' => 'mod_assign',
            'cmid' => 99,
            'instanceid' => 7,
            'fields' => ['duedate' => 1700000000],
            'version' => 1,
        ]);
        $snap = (new snapshot(0, (object) [
            'batchid' => $batchid,
            'entitytype' => 'cm',
            'entityid' => 99,
            'component' => 'mod_assign',
            'statejson' => $statejson,
        ]))->create();
        $reloaded = new snapshot($snap->get('id'));
        $this->assertSame($batchid, $reloaded->get('batchid'));
        $this->assertSame('cm', $reloaded->get('entitytype'));
        $this->assertSame(99, $reloaded->get('entityid'));
        $this->assertSame('mod_assign', $reloaded->get('component'));
        $this->assertSame($statejson, $reloaded->get('statejson'));
    }

    /**
     * The component column is nullable for non-cm entities such as
     * sections, labels or text fields.
     */
    public function test_component_can_be_null(): void
    {
        $this->resetAfterTest();
        $batchid = $this->make_parent_batch();
        $snap = (new snapshot(0, (object) [
            'batchid' => $batchid,
            'entitytype' => 'section',
            'entityid' => 3,
            'statejson' => '{}',
        ]))->create();
        $this->assertNull($snap->get('component'));
    }

    /**
     * Multiple snapshots for the same entity in the same batch are allowed.
     */
    public function test_multiple_snapshots_per_entity(): void
    {
        $this->resetAfterTest();
        $batchid = $this->make_parent_batch();
        for ($i = 0; $i < 3; ++$i) {
            (new snapshot(0, (object) [
                'batchid' => $batchid,
                'entitytype' => 'cm',
                'entityid' => 5,
                'component' => 'mod_quiz',
                'statejson' => '{"v":'.$i.'}',
            ]))->create();
        }
        $records = snapshot::get_records(['batchid' => $batchid, 'entityid' => 5]);
        $this->assertCount(3, $records);
    }

    /**
     * Helper that creates a parent batch and returns its id.
     */
    private function make_parent_batch(): int
    {
        $batch = (new batch(0, (object) [
            'courseid' => 1,
            'userid' => 1,
            'action' => 'shift_dates',
            'payloadjson' => '{}',
        ]))->create();

        return (int) $batch->get('id');
    }
}
