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
 * Tests for graph_dataset_builder.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\visualization;

use local_coursectrl\local\analysis\dependency_index;
use local_coursectrl\local\entity\cm_item;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\local\visualization\graph_dataset_builder::class)]
/**
 * Unit tests for graph_dataset_builder.
 *
 * @covers \local_coursectrl\local\visualization\graph_dataset_builder
 */
final class graph_dataset_builder_test extends \advanced_testcase {
    /**
     * Build a cm_item with optional availability JSON.
     *
     * @param int    $cmid       CM id.
     * @param string $avail      Availability JSON or empty string.
     * @param bool   $visible    Visibility flag.
     * @return cm_item
     */
    private function make_cm(int $cmid, string $avail = '', bool $visible = true): cm_item {
        return new cm_item(
            $cmid,
            1,
            10,
            'assign',
            $cmid,
            'Activity ' . $cmid,
            $visible,
            $avail !== '' ? $avail : null,
            2
        );
    }

    /**
     * Availability JSON requiring completion of the given cmid.
     *
     * @param int $depcmid Prerequisite cmid.
     * @return string
     */
    private function avail_requires(int $depcmid): string {
        return json_encode([
            'op' => '&',
            'c' => [['type' => 'completion', 'cm' => $depcmid, 'e' => 1]],
            'show' => false,
        ]);
    }

    /**
     * Empty CMs produce an empty hasdata=false result.
     * @covers \local_coursectrl\local\visualization\graph_dataset_builder
     */
    public function test_empty_cms_returns_empty_result(): void {
        $this->resetAfterTest();
        $builder = new graph_dataset_builder();
        $depindex = new dependency_index([]);
        $result = $builder->build([], $depindex);
        $this->assertFalse($result['hasdata']);
        $this->assertEmpty($result['nodes']);
        $this->assertEmpty($result['edges']);
        $this->assertSame(0, $result['nodecount']);
    }

    /**
     * CMs without dependencies all get layer 0.
     * @covers \local_coursectrl\local\visualization\graph_dataset_builder
     */
    public function test_independent_cms_all_layer_zero(): void {
        $this->resetAfterTest();
        $cms = [1 => $this->make_cm(1), 2 => $this->make_cm(2), 3 => $this->make_cm(3)];
        $depindex = new dependency_index($cms);
        $builder = new graph_dataset_builder();
        $result = $builder->build($cms, $depindex);
        $this->assertTrue($result['hasdata']);
        $this->assertSame(3, $result['nodecount']);
        $this->assertSame(0, $result['edgecount']);
        $this->assertTrue($result['hasonlynodes']);
        foreach ($result['nodes'] as $node) {
            $this->assertSame(0, $node['layer'], "Node {$node['id']} should be layer 0");
        }
    }

    /**
     * A linear chain A→B→C produces layers 0,1,2.
     * @covers \local_coursectrl\local\visualization\graph_dataset_builder
     */
    public function test_linear_chain_produces_ascending_layers(): void {
        $this->resetAfterTest();
        $cms = [
            1 => $this->make_cm(1),
            2 => $this->make_cm(2, $this->avail_requires(1)),
            3 => $this->make_cm(3, $this->avail_requires(2)),
        ];
        $depindex = new dependency_index($cms);
        $builder = new graph_dataset_builder();
        $result = $builder->build($cms, $depindex);
        $byid = array_column($result['nodes'], null, 'id');
        $this->assertSame(0, $byid[1]['layer']);
        $this->assertSame(1, $byid[2]['layer']);
        $this->assertSame(2, $byid[3]['layer']);
        $this->assertSame(2, $result['edgecount']);
        $this->assertFalse($result['hasonlynodes']);
    }

    /**
     * Circular nodes are flagged and still get a layer assigned.
     * @covers \local_coursectrl\local\visualization\graph_dataset_builder
     */
    public function test_circular_nodes_flagged(): void {
        $this->resetAfterTest();
        $cms = [
            10 => $this->make_cm(10, $this->avail_requires(11)),
            11 => $this->make_cm(11, $this->avail_requires(10)),
        ];
        $depindex = new dependency_index($cms);
        $builder = new graph_dataset_builder();
        $result = $builder->build($cms, $depindex);
        $this->assertSame(2, $result['nodecount']);
        foreach ($result['nodes'] as $node) {
            $this->assertTrue($node['circular'], "Node {$node['id']} should be circular");
        }
    }

    /**
     * Dangling dependencies (dep not in snapshot) produce no edge.
     * @covers \local_coursectrl\local\visualization\graph_dataset_builder
     */
    public function test_dangling_dep_produces_no_edge(): void {
        $this->resetAfterTest();
        $cms = [
            20 => $this->make_cm(20, $this->avail_requires(999)),
        ];
        $depindex = new dependency_index($cms);
        $builder = new graph_dataset_builder();
        $result = $builder->build($cms, $depindex);
        $this->assertSame(1, $result['nodecount']);
        $this->assertSame(0, $result['edgecount']);
    }

    /**
     * Nodes with warnings get haswarnings=true.
     * @covers \local_coursectrl\local\visualization\graph_dataset_builder
     */
    public function test_warning_flag_propagated_to_node(): void {
        $this->resetAfterTest();
        $cms = [30 => $this->make_cm(30)];
        $depindex = new dependency_index($cms);
        $warnings = [30 => [['type' => 'temporal_conflict']]];
        $builder = new graph_dataset_builder();
        $result = $builder->build($cms, $depindex, $warnings);
        $this->assertTrue($result['nodes'][0]['haswarnings']);
    }

    /**
     * assign_layers: diamond graph (1→3, 2→3, 1→4, 2→4) places 3 and 4 at layer 1.
     * @covers \local_coursectrl\local\visualization\graph_dataset_builder
     */
    public function test_assign_layers_diamond(): void {
        $this->resetAfterTest();
        $cms = [
            1 => $this->make_cm(1),
            2 => $this->make_cm(2),
            3 => $this->make_cm(3, json_encode(['op' => '&', 'c' => [
                ['type' => 'completion', 'cm' => 1, 'e' => 1],
                ['type' => 'completion', 'cm' => 2, 'e' => 1],
            ], 'show' => false])),
        ];
        $forward = [3 => [1, 2]];
        $builder = new graph_dataset_builder();
        $layers = $builder->assign_layers($cms, $forward);
        $this->assertSame(0, $layers[1]);
        $this->assertSame(0, $layers[2]);
        $this->assertSame(1, $layers[3]);
    }

    /**
     * Nodes within the same layer get distinct layerpos values (0-based).
     * @covers \local_coursectrl\local\visualization\graph_dataset_builder
     */
    public function test_layer_positions_distinct_within_layer(): void {
        $this->resetAfterTest();
        $cms = [1 => $this->make_cm(1), 2 => $this->make_cm(2), 3 => $this->make_cm(3)];
        $depindex = new dependency_index($cms);
        $builder = new graph_dataset_builder();
        $result = $builder->build($cms, $depindex);
        $positions = array_column($result['nodes'], 'layerpos');
        sort($positions);
        $this->assertSame([0, 1, 2], $positions);
    }

    // Grade-based dependency edge tests.

    /**
     * Build grade availability JSON requiring a grade item.
     *
     * @param int   $gradeid Grade item id.
     * @param float $min     Minimum grade percentage.
     * @return string
     */
    private function avail_grade(int $gradeid, float $min = 50.0): string {
        return json_encode([
            'op' => '&',
            'c' => [['type' => 'grade', 'id' => $gradeid, 'min' => $min]],
        ]);
    }

    /**
     * Grade condition produces an edge when gradeitemmap resolves it.
     * @covers \local_coursectrl\local\visualization\graph_dataset_builder
     */
    public function test_grade_dep_produces_edge(): void {
        $this->resetAfterTest();
        $cms = [
            1 => $this->make_cm(1),
            2 => $this->make_cm(2, $this->avail_grade(99)),
        ];
        // Grade item 99 belongs to cmid 1.
        $depindex = new dependency_index($cms, [99 => 1]);
        $builder = new graph_dataset_builder();
        $result = $builder->build($cms, $depindex);

        $this->assertSame(1, $result['edgecount']);
        $edge = $result['edges'][0];
        $this->assertSame(2, $edge['from']);
        $this->assertSame(1, $edge['to']);
        $this->assertFalse($edge['circular']);
    }

    /**
     * When a cmid pair already has a completion edge, the grade edge is not duplicated.
     * @covers \local_coursectrl\local\visualization\graph_dataset_builder
     */
    public function test_grade_dep_not_duplicated_when_completion_edge_exists(): void {
        $this->resetAfterTest();
        // CM 2 depends on CM 1 via both completion AND grade.
        $avail = json_encode([
            'op' => '&',
            'c' => [
                ['type' => 'completion', 'cm' => 1, 'e' => 1],
                ['type' => 'grade', 'id' => 99, 'min' => 50],
            ],
        ]);
        $cms = [
            1 => $this->make_cm(1),
            2 => $this->make_cm(2, $avail),
        ];
        $depindex = new dependency_index($cms, [99 => 1]);
        $builder = new graph_dataset_builder();
        $result = $builder->build($cms, $depindex);

        // Only one edge, not two.
        $this->assertSame(1, $result['edgecount']);
    }

    /**
     * Grade dep with no gradeitemmap produces no edge.
     * @covers \local_coursectrl\local\visualization\graph_dataset_builder
     */
    public function test_grade_dep_without_map_produces_no_edge(): void {
        $this->resetAfterTest();
        $cms = [
            1 => $this->make_cm(1),
            2 => $this->make_cm(2, $this->avail_grade(99)),
        ];
        $depindex = new dependency_index($cms); // No map.
        $builder = new graph_dataset_builder();
        $result = $builder->build($cms, $depindex);

        $this->assertSame(0, $result['edgecount']);
    }
}
