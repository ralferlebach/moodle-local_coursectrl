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
 * Parser for Moodle availability condition JSON trees.
 *
 * Extracts structured information from the availability JSON stored on
 * course_modules and course_sections. The parser is stateless and works
 * on a single JSON string at a time.
 *
 * Extracted condition types:
 *   - completion: cmid of the controlling activity + required state
 *   - date: direction (>=, <) and timestamp
 *   - grade: grade item id + min/max thresholds
 *   - group / grouping: group or grouping id
 *   - profile: user profile field conditions
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

/**
 * Stateless parser for Moodle availability JSON.
 */
class availability_parser {
    /**
     * Parse an availability JSON string into a structured result.
     *
     * @param string|null $json Raw availability JSON, or null.
     * @return array{
     *     completiondeps: array<int, int>,
     *     dateconditions: array,
     *     gradeconditions: array,
     *     groupconditions: array,
     *     otherconditions: array,
     *     operator: string,
     *     hasrestrictions: bool
     * }
     */
    public function parse(?string $json): array {
        $result = [
            'completiondeps' => [],
            'dateconditions' => [],
            'gradeconditions' => [],
            'groupconditions' => [],
            'otherconditions' => [],
            'operator' => '&',
            'hasrestrictions' => false,
        ];

        if ($json === null || $json === '') {
            return $result;
        }

        $tree = json_decode($json, true);
        if (!is_array($tree)) {
            return $result;
        }

        $result['operator'] = $tree['op'] ?? '&';
        $this->walk_tree($tree, $result);
        $result['hasrestrictions'] = !empty($result['completiondeps'])
            || !empty($result['dateconditions'])
            || !empty($result['gradeconditions'])
            || !empty($result['groupconditions'])
            || !empty($result['otherconditions']);

        return $result;
    }

    /**
     * Extract the cmids of all completion-based dependencies.
     *
     * Convenience method returning only the cmids this entity depends on.
     *
     * @param string|null $json Raw availability JSON.
     * @return int[] List of controlling cmids.
     */
    public function get_completion_deps(?string $json): array {
        $parsed = $this->parse($json);
        return array_keys($parsed['completiondeps']);
    }

    /**
     * Extract date-based restrictions as an array of conditions.
     *
     * @param string|null $json Raw availability JSON.
     * @return array[] Each entry: ['direction' => '>=' or '<', 'timestamp' => int].
     */
    public function get_date_conditions(?string $json): array {
        $parsed = $this->parse($json);
        return $parsed['dateconditions'];
    }

    /**
     * Recursively walk the availability tree and collect conditions.
     *
     * @param array $node   Current node in the tree.
     * @param array $result Accumulated result (modified in place).
     */
    private function walk_tree(array $node, array &$result): void {
        $conditions = $node['c'] ?? [];
        foreach ($conditions as $condition) {
            $type = $condition['type'] ?? '';
            switch ($type) {
                case 'completion':
                    $cmid = (int) ($condition['cm'] ?? 0);
                    $expectedstate = (int) ($condition['e'] ?? 1);
                    if ($cmid > 0) {
                        $result['completiondeps'][$cmid] = $expectedstate;
                    }
                    break;

                case 'date':
                    $result['dateconditions'][] = [
                        'direction' => (string) ($condition['d'] ?? '>='),
                        'timestamp' => (int) ($condition['t'] ?? 0),
                    ];
                    break;

                case 'grade':
                    $result['gradeconditions'][] = [
                        'itemid' => (int) ($condition['id'] ?? 0),
                        'min' => isset($condition['min']) ? (float) $condition['min'] : null,
                        'max' => isset($condition['max']) ? (float) $condition['max'] : null,
                    ];
                    break;

                case 'group':
                    $result['groupconditions'][] = [
                        'type' => 'group',
                        'id' => (int) ($condition['id'] ?? 0),
                    ];
                    break;

                case 'grouping':
                    $result['groupconditions'][] = [
                        'type' => 'grouping',
                        'id' => (int) ($condition['id'] ?? 0),
                    ];
                    break;

                default:
                    if ($type !== '') {
                        $result['otherconditions'][] = $condition;
                    }
                    break;
            }

            // Recurse into nested condition sets.
            if (isset($condition['c']) && is_array($condition['c'])) {
                $this->walk_tree($condition, $result);
            }
        }
    }
}
