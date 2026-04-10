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
 * Data transfer object representing the result of one execute_action call.
 *
 * Wraps the per-cmid item shape returned by activity_adapter::execute_action()
 * in a typed, immutable structure. The batch_manager (patch-025) serialises
 * execution_result instances into batch_item.resultjson.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\dto;

/**
 * Immutable DTO for one per-cmid execute outcome.
 */
final class execution_result {
    /** @var string Adapter result status: ok | noop | failed. */
    public const STATUS_OK = 'ok';

    /** @var string Adapter result status: nothing changed, no DB write. */
    public const STATUS_NOOP = 'noop';

    /** @var string Adapter result status: write attempted but failed. */
    public const STATUS_FAILED = 'failed';

    /** @var int Course module id this result refers to. */
    private int $cmid;

    /** @var string One of the STATUS_* constants. */
    private string $status;

    /** @var array Snapshot captured before the mutation, as returned by the adapter. */
    private array $snapshot;

    /** @var string[] Names of fields that actually changed. */
    private array $changed;

    /** @var string|null Human-readable failure message, if any. */
    private ?string $message;

    /**
     * Constructor.
     *
     * @param int      $cmid     course module id.
     * @param string   $status   one of STATUS_OK, STATUS_NOOP, STATUS_FAILED.
     * @param array    $snapshot snapshot captured before mutation.
     * @param string[] $changed  list of field names that actually changed.
     * @param string|null $message optional failure message.
     */
    public function __construct(
        int $cmid,
        string $status,
        array $snapshot = [],
        array $changed = [],
        ?string $message = null
    ) {
        $this->cmid = $cmid;
        $this->status = $status;
        $this->snapshot = $snapshot;
        $this->changed = array_values($changed);
        $this->message = $message;
    }

    /**
     * Returns the cmid.
     *
     * @return int
     */
    public function get_cmid(): int {
        return $this->cmid;
    }

    /**
     * Returns the status string.
     *
     * @return string
     */
    public function get_status(): string {
        return $this->status;
    }

    /**
     * Returns the captured snapshot array.
     *
     * @return array
     */
    public function get_snapshot(): array {
        return $this->snapshot;
    }

    /**
     * Returns the list of fields that actually changed.
     *
     * @return string[]
     */
    public function get_changed(): array {
        return $this->changed;
    }

    /**
     * Returns the failure message, if any.
     *
     * @return string|null
     */
    public function get_message(): ?string {
        return $this->message;
    }

    /**
     * Render this DTO as a plain array suitable for JSON encoding into the
     * batch_item.resultjson column.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'cmid'     => $this->cmid,
            'status'   => $this->status,
            'snapshot' => $this->snapshot,
            'changed'  => $this->changed,
            'message'  => $this->message,
        ];
    }
}
