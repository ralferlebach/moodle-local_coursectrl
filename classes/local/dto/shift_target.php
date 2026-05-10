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
 * Data transfer object representing a single shift target.
 *
 * A shift_target identifies one specific date field of one course module
 * that should be moved by a shift operation. It carries the minimum
 * information needed to route the shift to the correct handler:
 *
 *   - cmid      - which course module
 *   - source    - how to reach the stored value (adapter / cm / availability)
 *   - field     - the raw technical field key (e.g. 'duedate')
 *   - timestamp - the current value, used for slot grouping and following-filters
 *
 * Using an explicit target list instead of a flat cmid list + optional
 * field restriction ensures that preview and execute always operate on
 * exactly the same set of (cmid, field) pairs, and that CM-level fields
 * (completionexpected, availability dates) are visible in the preview
 * rather than silently appearing only in the execute result.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\dto;

/**
 * Immutable DTO for one (cmid, source, field, timestamp) shift target.
 */
final class shift_target {
    /** @var string Source: the field is managed by an activity adapter. */
    public const SOURCE_ADAPTER = 'adapter';

    /** @var string Source: the field lives in {course_modules} (completionexpected). */
    public const SOURCE_CM = 'cm';

    /** @var string Source: the field is a date condition inside the availability JSON. */
    public const SOURCE_AVAILABILITY = 'availability';

    /** @var int Course module id this target refers to. */
    private int $cmid;

    /** @var string Source type - one of the SOURCE_* constants. */
    private string $source;

    /** @var string Raw technical field key, e.g. 'duedate', 'completionexpected'. */
    private string $field;

    /** @var int Current Unix timestamp of the date value. */
    private int $timestamp;

    /**
     * Constructor.
     *
     * @param int    $cmid      Course module id.
     * @param string $source    Source type (SOURCE_ADAPTER, SOURCE_CM, SOURCE_AVAILABILITY).
     * @param string $field     Raw technical field key.
     * @param int    $timestamp Current Unix timestamp of the date value.
     */
    public function __construct(
        int $cmid,
        string $source,
        string $field,
        int $timestamp
    ) {
        $this->cmid = $cmid;
        $this->source = $source;
        $this->field = $field;
        $this->timestamp = $timestamp;
    }

    /**
     * Derive the source type from a raw field name alone.
     *
     * Rules:
     *   - 'completionexpected'    → SOURCE_CM
     *   - 'availability_*' prefix → SOURCE_AVAILABILITY
     *   - everything else         → SOURCE_ADAPTER
     *
     * @param string $field Raw technical field key.
     * @return string One of the SOURCE_* constants.
     */
    public static function resolve_source(string $field): string {
        if ($field === 'completionexpected') {
            return self::SOURCE_CM;
        }
        if (strpos($field, 'availability_') === 0) {
            return self::SOURCE_AVAILABILITY;
        }
        return self::SOURCE_ADAPTER;
    }

    /**
     * Create a shift_target from an associative array.
     *
     * If the 'source' key is absent or empty the source is inferred from
     * the field name via resolve_source(). This allows callers that only
     * know (cmid, field, timestamp) to omit source and still get a valid
     * instance.
     *
     * @param array $data Associative array with keys cmid, field, timestamp
     *                    and optionally source.
     * @return self
     */
    public static function from_array(array $data): self {
        $cmid = (int) ($data['cmid'] ?? 0);
        $field = (string) ($data['field'] ?? '');
        $source = isset($data['source']) && $data['source'] !== ''
            ? (string) $data['source']
            : self::resolve_source($field);
        $timestamp = (int) ($data['timestamp'] ?? 0);
        return new self($cmid, $source, $field, $timestamp);
    }

    /**
     * Parse a JSON string into an array of shift_target instances.
     *
     * Entries that are missing the mandatory 'cmid' or 'field' keys are
     * silently skipped. Returns an empty array when the JSON is malformed
     * or does not decode to an array.
     *
     * @param string $json JSON-encoded array of target descriptors.
     * @return self[]
     */
    public static function from_json_array(string $json): array {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $targets = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $cmid = (int) ($item['cmid'] ?? 0);
            $field = (string) ($item['field'] ?? '');
            if ($cmid <= 0 || $field === '') {
                continue;
            }
            $targets[] = self::from_array($item);
        }
        return $targets;
    }

    /**
     * Returns the course module id.
     *
     * @return int
     */
    public function get_cmid(): int {
        return $this->cmid;
    }

    /**
     * Returns the source type.
     *
     * @return string
     */
    public function get_source(): string {
        return $this->source;
    }

    /**
     * Returns the raw technical field key.
     *
     * @return string
     */
    public function get_field(): string {
        return $this->field;
    }

    /**
     * Returns the current Unix timestamp of the date value.
     *
     * @return int
     */
    public function get_timestamp(): int {
        return $this->timestamp;
    }

    /**
     * Serialise to a plain array suitable for JSON encoding.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'cmid'      => $this->cmid,
            'source'    => $this->source,
            'field'     => $this->field,
            'timestamp' => $this->timestamp,
        ];
    }
}
