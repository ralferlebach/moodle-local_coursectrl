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
 * Data transfer object representing the result of a validation pass.
 *
 * Wraps the array returned by activity_adapter::validate_action() in a
 * typed, immutable shape. preview_manager and batch_manager use it to
 * decide whether to proceed with a preview build or an execute call.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\dto;

/**
 * Immutable DTO for an action validation outcome.
 */
final class validation_result {
    /** @var bool Whether the validation passed. */
    private bool $valid;

    /** @var array List of structured error descriptors. */
    private array $errors;

    /** @var int[] Course module ids confirmed valid by the adapter. */
    private array $cmids;

    /**
     * Constructor.
     *
     * @param bool  $valid  whether the validation passed.
     * @param array $errors list of structured error descriptors.
     * @param int[] $cmids  cmids confirmed valid by the adapter.
     */
    public function __construct(bool $valid, array $errors = [], array $cmids = []) {
        $this->valid = $valid;
        $this->errors = $errors;
        $this->cmids = array_values(array_map('intval', $cmids));
    }

    /**
     * Construct a validation_result from the array shape returned by
     * activity_adapter::validate_action().
     *
     * @param array $raw raw adapter validation result.
     * @return self
     */
    public static function from_adapter_array(array $raw): self {
        return new self(
            (bool)($raw['valid'] ?? false),
            $raw['errors'] ?? [],
            $raw['cmids'] ?? []
        );
    }

    /**
     * Whether the validation passed.
     *
     * @return bool
     */
    public function is_valid(): bool {
        return $this->valid;
    }

    /**
     * Returns the list of structured error descriptors.
     *
     * @return array
     */
    public function get_errors(): array {
        return $this->errors;
    }

    /**
     * Returns the validated course module ids.
     *
     * @return int[]
     */
    public function get_cmids(): array {
        return $this->cmids;
    }
}
