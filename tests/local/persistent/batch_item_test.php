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
 * Unit tests for the batch_item persistent.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\persistent;

/**
 * Tests creation, FK linkage to batch, and nullable JSON columns.
 *
 * @covers \local_coursectrl\local\persistent\batch_item
 */
final class batch_item_test extends \advanced_testcase {
    /**
     * Helper that creates a parent batch and returns its id.
     *
     * @return int
     */
    private function make_parent_batch(): int {
        $data = (object)[
            'courseid'    => 5,
            'userid'      => 5,
            'action'      => 'shift_dates',
            'payloadjson' => '{}',
        ];
        $batch = (new batch(0, $data))->create();
        return (int)$batch->get('id');
    }

    /**
     * A persisted batch_item can be reloaded by id.
     */
    public function test_create_and_reload(): void {
        $this->resetAfterTest();
        $batchid = $this->make_parent_batch();
        $data = (object)[
            'batchid'     => $batchid,
            'entitytype'  => 'cm',
            'entityid'    => 123,
            'component'   => 'mod_assign',
            'previewjson' => '{"old":1,"new":2}',
            'resultjson'  => '{"status":"ok"}',
        ];
        $item = (new batch_item(0, $data))->create();
        $reloaded = new batch_item($item->get('id'));
        $this->assertSame($batchid, $reloaded->get('batchid'));
        $this->assertSame('cm', $reloaded->get('entitytype'));
        $this->assertSame(123, $reloaded->get('entityid'));
        $this->assertSame('mod_assign', $reloaded->get('component'));
        $this->assertSame('{"old":1,"new":2}', $reloaded->get('previewjson'));
        $this->assertSame('{"status":"ok"}', $reloaded->get('resultjson'));
    }

    /**
     * component, previewjson and resultjson are nullable in the schema and
     * can be omitted on creation.
     */
    public function test_nullable_columns(): void {
        $this->resetAfterTest();
        $batchid = $this->make_parent_batch();
        $data = (object)[
            'batchid'    => $batchid,
            'entitytype' => 'section',
            'entityid'   => 1,
        ];
        $item = (new batch_item(0, $data))->create();
        $reloaded = new batch_item($item->get('id'));
        $this->assertNull($reloaded->get('component'));
        $this->assertNull($reloaded->get('previewjson'));
        $this->assertNull($reloaded->get('resultjson'));
        $this->assertSame(batch_item::STATUS_PENDING, $reloaded->get('status'));
    }

    /**
     * The four legal item statuses are accepted.
     */
    public function test_legal_statuses(): void {
        $this->resetAfterTest();
        $batchid = $this->make_parent_batch();
        $statuses = [
            batch_item::STATUS_PENDING,
            batch_item::STATUS_SKIPPED,
            batch_item::STATUS_SUCCESS,
            batch_item::STATUS_ERROR,
        ];
        foreach ($statuses as $status) {
            $data = (object)[
                'batchid'    => $batchid,
                'entitytype' => 'cm',
                'entityid'   => 1,
                'status'     => $status,
            ];
            $item = (new batch_item(0, $data))->create();
            $this->assertSame($status, $item->get('status'));
        }
    }
}
