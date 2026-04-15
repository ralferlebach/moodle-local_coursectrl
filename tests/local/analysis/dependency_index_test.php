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
 * Tests for the dependency_index.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;

/**
 * Unit tests for dependency_index.
 *
 * @covers \local_coursectrl\local\analysis\dependency_index
 */
final class dependency_index_test extends \basic_testcase {
    /**
     * Helper: build a cm_item with optional availability JSON.
     *
     * @param int         $id           Course module id.
     * @param string|null $availability Availability JSON.
     * @return cm_item
     */
    private function cm(int $id, ?string $availability = null): cm_item {
        return new cm_item($id, 1, 10, 'assign', $id, 'Activity ' . $id, true, $availability, 0);
    }

    /**
     * Helper: build availability JSON with a completion dep.
     *
     * @param int $depcmid The cmid this depends on.
     * @return string JSON string.
     */
    private function completiondep(int $depcmid): string {
        return json_encode([
            'op' => '&',
            'c' => [['type' => 'completion', 'cm' => $depcmid, 'e' => 1]],
        ]);
    }

    /**
     * Forward deps must list the prerequisite cmids.
     */
    public function test_forward_deps(): void {
        $cms = [
            10 => $this->cm(10),
            20 => $this->cm(20, $this->completiondep(10)),
        ];
        $index = new dependency_index($cms);

        $this->assertSame([10], $index->get_prerequisites(20));
        $this->assertEmpty($index->get_prerequisites(10));
    }

    /**
     * Reverse deps must list the dependent cmids.
     */
    public function test_reverse_deps(): void {
        $cms = [
            10 => $this->cm(10),
            20 => $this->cm(20, $this->completiondep(10)),
            30 => $this->cm(30, $this->completiondep(10)),
        ];
        $index = new dependency_index($cms);

        $dependents = $index->get_dependents(10);
        sort($dependents);
        $this->assertSame([20, 30], $dependents);
        $this->assertEmpty($index->get_dependents(20));
    }

    /**
     * has_dependents must return true for activities with dependents.
     */
    public function test_has_dependents(): void {
        $cms = [
            10 => $this->cm(10),
            20 => $this->cm(20, $this->completiondep(10)),
        ];
        $index = new dependency_index($cms);

        $this->assertTrue($index->has_dependents(10));
        $this->assertFalse($index->has_dependents(20));
    }

    /**
     * Date restrictions must be extracted from availability JSON.
     */
    public function test_date_restrictions(): void {
        $json = json_encode([
            'op' => '&',
            'c' => [['type' => 'date', 'd' => '>=', 't' => 1700000000]],
        ]);
        $cms = [
            10 => $this->cm(10, $json),
        ];
        $index = new dependency_index($cms);

        $dates = $index->get_date_restrictions(10);
        $this->assertCount(1, $dates);
        $this->assertSame(1700000000, $dates[0]['timestamp']);
    }

    /**
     * has_restrictions must be true for CMs with any restriction.
     */
    public function test_has_restrictions(): void {
        $cms = [
            10 => $this->cm(10),
            20 => $this->cm(20, $this->completiondep(10)),
        ];
        $index = new dependency_index($cms);

        $this->assertFalse($index->has_restrictions(10));
        $this->assertTrue($index->has_restrictions(20));
    }

    /**
     * Circular dependencies must be detected.
     */
    public function test_find_circular_deps(): void {
        $cms = [
            10 => $this->cm(10, $this->completiondep(20)),
            20 => $this->cm(20, $this->completiondep(10)),
        ];
        $index = new dependency_index($cms);

        $circular = $index->find_circular_deps();
        $this->assertCount(1, $circular);
        $this->assertSame(10, $circular[0]['a']);
        $this->assertSame(20, $circular[0]['b']);
    }

    /**
     * No circular deps must return empty array.
     */
    public function test_no_circular(): void {
        $cms = [
            10 => $this->cm(10),
            20 => $this->cm(20, $this->completiondep(10)),
            30 => $this->cm(30, $this->completiondep(20)),
        ];
        $index = new dependency_index($cms);

        $this->assertEmpty($index->find_circular_deps());
    }

    /**
     * Empty CMs must produce an empty index.
     */
    public function test_empty_cms(): void {
        $index = new dependency_index([]);

        $this->assertEmpty($index->get_all_forward());
        $this->assertEmpty($index->get_all_reverse());
        $this->assertEmpty($index->find_circular_deps());
    }

    /**
     * Chain A→B→C must have correct forward and reverse maps.
     */
    public function test_chain(): void {
        $cms = [
            10 => $this->cm(10),
            20 => $this->cm(20, $this->completiondep(10)),
            30 => $this->cm(30, $this->completiondep(20)),
        ];
        $index = new dependency_index($cms);

        // Forward.
        $this->assertSame([10], $index->get_prerequisites(20));
        $this->assertSame([20], $index->get_prerequisites(30));

        // Reverse.
        $this->assertSame([20], $index->get_dependents(10));
        $this->assertSame([30], $index->get_dependents(20));
    }
}
