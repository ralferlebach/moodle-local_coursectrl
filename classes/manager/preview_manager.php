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
 * Course-wide preview manager for the Course Control Hub bulk pipeline.
 *
 * Phase 4 entry point that aggregates per-cmid adapter previews into a
 * course-wide preview result. The skeleton introduced in patch-023 holds
 * the registry-based DI surface and signature contract; the actual
 * aggregation logic ships in patch-024.
 *
 * Calling build() in patch-023 throws a coding_exception so accidental
 * production calls fail loudly rather than silently producing empty
 * previews.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\manager;

/**
 * Aggregates adapter previews for a course-wide bulk action.
 */
class preview_manager {
    /** @var registry Adapter registry used to look up activity adapters. */
    private registry $registry;

    /**
     * Constructor.
     *
     * @param registry|null $registry optional registry instance, mainly for
     *                                tests. When null, a fresh registry with
     *                                live discovery is created.
     */
    public function __construct(?registry $registry = null) {
        $this->registry = $registry ?? new registry();
    }

    /**
     * Returns the registry instance backing this manager.
     *
     * Exposed primarily for tests; production code should not need to
     * reach into the registry directly.
     *
     * @return registry
     */
    public function get_registry(): registry {
        return $this->registry;
    }

    /**
     * Build a course-wide preview for a bulk action.
     *
     * Will be implemented in patch-024. The signature is fixed in patch-023
     * to allow downstream code (external functions, UI) to declare types
     * against it without waiting for the body.
     *
     * @param int    $courseid target course id.
     * @param string $action   canonical action identifier, e.g. 'shift_dates'.
     * @param array  $payload  action-specific parameters.
     * @param int[]  $cmids    target course module ids; empty means "all".
     * @return array of preview_change DTOs keyed by cmid.
     * @throws \coding_exception always, until patch-024 lands.
     */
    public function build(int $courseid, string $action, array $payload, array $cmids = []): array {
        throw new \coding_exception(
            'preview_manager::build() is not yet implemented; introduced as a skeleton in patch-023.'
        );
    }
}
