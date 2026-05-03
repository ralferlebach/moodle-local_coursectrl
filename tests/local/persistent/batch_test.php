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
 * Unit tests for the batch persistent.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\persistent;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\persistent\batch::class)]
/**
 * Tests creation, retrieval and status transitions of batch records.
 *
 * @covers \local_coursectrl\local\persistent\batch
 */
final class batch_test extends \advanced_testcase {
    /**
     * A persisted batch can be reloaded by id and round-trips its fields.
     * @covers \local_coursectrl\local\persistent\batch
     */
    public function test_create_and_reload(): void {
        $this->resetAfterTest();
        $data = (object)[
            'courseid'    => 42,
            'userid'      => 7,
            'action'      => 'shift_dates',
            'payloadjson' => '{"delta":86400}',
        ];
        $record = (new batch(0, $data))->create();
        $this->assertGreaterThan(0, $record->get('id'));
        $reloaded = new batch($record->get('id'));
        $this->assertSame(42, $reloaded->get('courseid'));
        $this->assertSame(7, $reloaded->get('userid'));
        $this->assertSame('shift_dates', $reloaded->get('action'));
        $this->assertSame('{"delta":86400}', $reloaded->get('payloadjson'));
        $this->assertSame(batch::STATUS_PENDING, $reloaded->get('status'));
    }

    /**
     * The default status of a freshly created batch is 'pending'.
     * @covers \local_coursectrl\local\persistent\batch
     */
    public function test_default_status_is_pending(): void {
        $this->resetAfterTest();
        $data = (object)[
            'courseid'    => 1,
            'userid'      => 1,
            'action'      => 'shift_dates',
            'payloadjson' => '{}',
        ];
        $record = (new batch(0, $data))->create();
        $this->assertSame(batch::STATUS_PENDING, $record->get('status'));
    }

    /**
     * Status transitions through all five legal values.
     * @covers \local_coursectrl\local\persistent\batch
     */
    public function test_status_transitions(): void {
        $this->resetAfterTest();
        $data = (object)[
            'courseid'    => 1,
            'userid'      => 1,
            'action'      => 'shift_dates',
            'payloadjson' => '{}',
        ];
        $record = (new batch(0, $data))->create();
        $statuses = [
            batch::STATUS_PREVIEWED,
            batch::STATUS_EXECUTED,
            batch::STATUS_ROLLED_BACK,
            batch::STATUS_FAILED,
        ];
        foreach ($statuses as $status) {
            $record->set('status', $status);
            $record->update();
            $reloaded = new batch($record->get('id'));
            $this->assertSame($status, $reloaded->get('status'));
        }
    }

    /**
     * Setting an unknown status value must be rejected by the persistent's
     * choices validator.
     * @covers \local_coursectrl\local\persistent\batch
     */
    public function test_unknown_status_is_rejected(): void {
        $this->resetAfterTest();
        $data = (object)[
            'courseid'    => 1,
            'userid'      => 1,
            'action'      => 'shift_dates',
            'payloadjson' => '{}',
        ];
        $record = (new batch(0, $data))->create();
        $record->set('status', 'bogus');
        $this->expectException(\core\invalid_persistent_exception::class);
        $record->update();
    }
}
