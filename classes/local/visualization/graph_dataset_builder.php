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
 * Graph dataset builder for the Course Control Hub.
 *
 * Produces a serialisable node/edge dataset for the dependency-graph view.
 * The PHP layer handles structural computation (topological layer assignment,
 * circular-cycle detection); the AMD layer handles pixel layout and drawing.
 *
 * Layer algorithm (Kahn-style relaxation):
 *   - Nodes with no intra-course prerequisites → layer 0.
 *   - A node moves to layer max(prereq_layers)+1 once all its known
 *     prerequisites have been placed.
 *   - Nodes that remain unplaced after all iterations are in a circular
 *     cycle; they are assigned to the next available layer and flagged
 *     circular=true.
 *   - Dangling dependencies (prerequisite cmid not in snapshot) are skipped
 *     when computing layers so they do not block progression.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\visualization;

use local_coursectrl\local\analysis\dependency_index;
use local_coursectrl\local\entity\cm_item;

/**
 * Builds a layered graph dataset from CMs and a dependency index.
 */
class graph_dataset_builder {
    /**
     * Build the full graph dataset.
     *
     * @param cm_item[]        $cms      Course modules keyed by cmid.
     * @param dependency_index $depindex Prebuilt dependency index.
     * @param array            $warnings Per-CM warning lists from consistency_runner
     *                                   (keyed by cmid; used to flag nodes).
     * @return array Graph dataset with keys: nodes, edges, nodecount, edgecount,
     *               layercount, hasonlynodes, hasdata.
     */
    public function build(
        array $cms,
        dependency_index $depindex,
        array $warnings = []
    ): array {
        if (empty($cms)) {
            return $this->empty_result();
        }

        $forward = $depindex->get_all_forward();
        $circular = $depindex->find_circular_deps();
        $circularset = $this->build_circular_set($circular);
        $layers = $this->assign_layers($cms, $forward);
        $layerpositions = $this->assign_layer_positions($layers);
        $layercount = empty($layers) ? 0 : max(array_values($layers)) + 1;

        $nodes = [];
        foreach ($cms as $cm) {
            $nodes[] = [
                'id' => $cm->id,
                'label' => $cm->name,
                'modname' => $cm->modname,
                'component' => $cm->get_component(),
                'visible' => $cm->visible,
                'circular' => isset($circularset[$cm->id]),
                'haswarnings' => !empty($warnings[$cm->id]),
                'layer' => $layers[$cm->id] ?? 0,
                'layerpos' => $layerpositions[$cm->id] ?? 0,
                'url' => (new \moodle_url(
                    '/mod/' . $cm->modname . '/view.php',
                    ['id' => $cm->id]
                ))->out(false),
                'editurl' => (new \moodle_url(
                    '/course/modedit.php',
                    ['update' => $cm->id, 'return' => 1]
                ))->out(false),
            ];
        }

        $knownids = array_fill_keys(array_keys($cms), true);
        $circularedgeset = [];
        foreach ($circular as $pair) {
            $key = min($pair['a'], $pair['b']) . '-' . max($pair['a'], $pair['b']);
            $circularedgeset[$key] = true;
        }
        $edges = [];
        foreach ($forward as $cmid => $prereqs) {
            foreach ($prereqs as $depcmid) {
                if (!isset($knownids[$depcmid])) {
                    continue;
                }
                $key = min($cmid, $depcmid) . '-' . max($cmid, $depcmid);
                $edges[] = [
                    'from' => $cmid,
                    'to' => $depcmid,
                    'circular' => isset($circularedgeset[$key]),
                ];
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'nodecount' => count($nodes),
            'edgecount' => count($edges),
            'layercount' => $layercount,
            'hasonlynodes' => count($edges) === 0,
            'hasdata' => true,
        ];
    }

    /**
     * Build the full graph dataset using an external forward-dependency map.
     *
     * Identical to build() but accepts a pre-filtered forward map (e.g. from
     * dependency_index::get_all_forward_for_group()) instead of fetching all
     * forward dependencies from the index. The dependency_index is still used
     * for circular-cycle detection over the full graph.
     *
     * @param cm_item[]        $cms      Course modules keyed by cmid.
     * @param dependency_index $depindex Prebuilt dependency index (for cycle detection).
     * @param array            $forward  Filtered forward map: cmid → prerequisite cmids.
     * @param array            $warnings Per-CM warning lists from consistency_runner.
     * @return array Graph dataset.
     */
    public function build_with_forward(
        array $cms,
        dependency_index $depindex,
        array $forward,
        array $warnings = []
    ): array {
        if (empty($cms)) {
            return $this->empty_result();
        }

        $circular = $depindex->find_circular_deps();
        $circularset = $this->build_circular_set($circular);
        $layers = $this->assign_layers($cms, $forward);
        $layerpositions = $this->assign_layer_positions($layers);
        $layercount = empty($layers) ? 0 : max(array_values($layers)) + 1;

        $nodes = [];
        foreach ($cms as $cm) {
            $nodes[] = [
                'id' => $cm->id,
                'label' => $cm->name,
                'modname' => $cm->modname,
                'component' => $cm->get_component(),
                'visible' => $cm->visible,
                'circular' => isset($circularset[$cm->id]),
                'haswarnings' => !empty($warnings[$cm->id]),
                'layer' => $layers[$cm->id] ?? 0,
                'layerpos' => $layerpositions[$cm->id] ?? 0,
                'url' => (new \moodle_url(
                    '/mod/' . $cm->modname . '/view.php',
                    ['id' => $cm->id]
                ))->out(false),
                'editurl' => (new \moodle_url(
                    '/course/modedit.php',
                    ['update' => $cm->id, 'return' => 1]
                ))->out(false),
            ];
        }

        $knownids = array_fill_keys(array_keys($cms), true);
        $circularedgeset = [];
        foreach ($circular as $pair) {
            $key = min($pair['a'], $pair['b']) . '-' . max($pair['a'], $pair['b']);
            $circularedgeset[$key] = true;
        }
        $edges = [];
        foreach ($forward as $cmid => $prereqs) {
            foreach ($prereqs as $depcmid) {
                if (!isset($knownids[$depcmid])) {
                    continue;
                }
                $key = min($cmid, $depcmid) . '-' . max($cmid, $depcmid);
                $edges[] = [
                    'from' => $cmid,
                    'to' => $depcmid,
                    'circular' => isset($circularedgeset[$key]),
                ];
            }
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'nodecount' => count($nodes),
            'edgecount' => count($edges),
            'layercount' => $layercount,
            'hasonlynodes' => count($edges) === 0,
            'hasdata' => true,
        ];
    }

    /**
     * Assign topological layers to all CMs (public for unit testing).
     *
     * @param cm_item[] $cms     CMs keyed by cmid.
     * @param array     $forward Forward map: cmid → list of prerequisite cmids.
     * @return array<int, int> cmid → layer index (0-based).
     */
    public function assign_layers(array $cms, array $forward): array {
        $knownids = array_fill_keys(array_keys($cms), true);
        $layers = [];
        $unprocessed = array_keys($cms);
        $maxiters = count($cms) + 1;
        for ($i = 0; $i < $maxiters && !empty($unprocessed); $i++) {
            $stillpending = [];
            $madeprogress = false;
            foreach ($unprocessed as $cmid) {
                $prereqs = array_values(
                    array_filter($forward[$cmid] ?? [], fn($d) => isset($knownids[$d]))
                );
                $allresolved = true;
                foreach ($prereqs as $dep) {
                    if (!isset($layers[$dep])) {
                        $allresolved = false;
                        break;
                    }
                }
                if ($allresolved) {
                    $maxpre = -1;
                    foreach ($prereqs as $dep) {
                        $maxpre = max($maxpre, $layers[$dep]);
                    }
                    $layers[$cmid] = $maxpre + 1;
                    $madeprogress = true;
                } else {
                    $stillpending[] = $cmid;
                }
            }
            $unprocessed = $stillpending;
            if (!$madeprogress) {
                $circularlayer = empty($layers) ? 0 : max(array_values($layers)) + 1;
                foreach ($unprocessed as $cmid) {
                    $layers[$cmid] = $circularlayer;
                }
                break;
            }
        }
        return $layers;
    }

    /**
     * Assign position-within-layer indices sorted by cmid for determinism.
     *
     * @param array<int, int> $layers cmid → layer index.
     * @return array<int, int> cmid → 0-based position within its layer.
     */
    private function assign_layer_positions(array $layers): array {
        $bylayer = [];
        foreach ($layers as $cmid => $layer) {
            $bylayer[$layer][] = $cmid;
        }
        $positions = [];
        foreach ($bylayer as $layercmids) {
            sort($layercmids);
            foreach ($layercmids as $pos => $cmid) {
                $positions[$cmid] = $pos;
            }
        }
        return $positions;
    }

    /**
     * Build a set of cmids involved in circular dependency pairs.
     *
     * @param array $circular Pairs [{a, b}] from dependency_index::find_circular_deps().
     * @return array<int, true>
     */
    private function build_circular_set(array $circular): array {
        $set = [];
        foreach ($circular as $pair) {
            $set[$pair['a']] = true;
            $set[$pair['b']] = true;
        }
        return $set;
    }

    /**
     * Return an empty result structure for courses with no CMs.
     *
     * @return array
     */
    private function empty_result(): array {
        return [
            'nodes' => [],
            'edges' => [],
            'nodecount' => 0,
            'edgecount' => 0,
            'layercount' => 0,
            'hasonlynodes' => true,
            'hasdata' => false,
        ];
    }
}
