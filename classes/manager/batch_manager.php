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
 * Course-wide bulk execute manager for the Course Control Hub bulk pipeline.
 *
 * Phase 4 entry point that orchestrates the full execute path: capability
 * check, snapshot persistence, adapter execute_action calls, batch_item
 * persistence, eventing and (eventually) calendar event refresh. The
 * skeleton introduced in patch-023 holds the registry-based DI surface and
 * signature contract; the actual execute pipeline ships in patch-025.
 *
 * Calling execute() in patch-023 throws a coding_exception so accidental
 * production calls fail loudly rather than silently doing nothing.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\manager;

/**
 * Orchestrates course-wide bulk execute calls and rollback persistence.
 */
class batch_manager {
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
     * @return registry
     */
    public function get_registry(): registry {
        return $this->registry;
    }

    /**
     * Execute a course-wide bulk action.
     *
     * Will be implemented in patch-025. The signature is fixed in patch-023
     * to allow downstream code (external functions, UI) to declare types
     * against it without waiting for the body.
     *
     * @param int    $courseid target course id.
     * @param string $action   canonical action identifier, e.g. 'shift_dates'.
     * @param array  $payload  action-specific parameters.
     * @param int[]  $cmids    target course module ids; empty means "all".
     * @param int    $userid   acting user id for audit purposes.
     * @return int batch id of the persisted batch row.
     * @throws \coding_exception always, until patch-025 lands.
     */
    public function execute(
        int $courseid,
        string $action,
        array $payload,
        array $cmids,
        int $userid
    ): int {
        throw new \coding_exception(
            'batch_manager::execute() is not yet implemented; introduced as a skeleton in patch-023.'
        );
    }
}
