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
 * Normalised course module entity for the Course Control Hub inventory.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\entity;

/**
 * Immutable DTO carrying the course-module-level fields the hub cares about.
 */
final class cm_item extends inventory_item {
    /** @var int Moodle course_modules.id (cmid). */
    public readonly int $id;

    /** @var int Parent course id. */
    public readonly int $courseid;

    /** @var int Parent course_sections.id. */
    public readonly int $sectionid;

    /** @var string Module short name, e.g. 'assign', 'quiz'. */
    public readonly string $modname;

    /** @var int Row id inside the module-specific table. */
    public readonly int $instance;

    /** @var string Activity name as shown to users. */
    public readonly string $name;

    /** @var bool Current visibility flag. */
    public readonly bool $visible;

    /** @var string|null JSON availability tree, or null. */
    public readonly ?string $availability;

    /** @var int COMPLETION_TRACKING_* constant. */
    public readonly int $completion;

    /** @var int Expected completion timestamp (timeline reminder), 0 = unset. */
    public readonly int $completionexpected;

    /**
     * Constructor.
     *
     * @param int         $id                 Moodle course_modules.id (cmid).
     * @param int         $courseid           Parent course id.
     * @param int         $sectionid          Parent course_sections.id.
     * @param string      $modname            Module short name, e.g. 'assign', 'quiz'.
     * @param int         $instance           Row id inside the module-specific table.
     * @param string      $name               Activity name as shown to users.
     * @param bool        $visible            Current visibility flag.
     * @param string|null $availability       JSON availability tree, or null.
     * @param int         $completion         COMPLETION_TRACKING_* constant.
     * @param int         $completionexpected Expected completion timestamp, 0 = unset.
     */
    public function __construct(
        int $id,
        int $courseid,
        int $sectionid,
        string $modname,
        int $instance,
        string $name,
        bool $visible,
        ?string $availability,
        int $completion,
        int $completionexpected = 0
    ) {
        $this->id = $id;
        $this->courseid = $courseid;
        $this->sectionid = $sectionid;
        $this->modname = $modname;
        $this->instance = $instance;
        $this->name = $name;
        $this->visible = $visible;
        $this->availability = $availability;
        $this->completion = $completion;
        $this->completionexpected = $completionexpected;
    }

    /**
     * Return the type discriminator.
     *
     * @return string always 'cm'.
     */
    public function get_type(): string {
        return 'cm';
    }

    /**
     * Return the frankenstyle component name, e.g. 'mod_assign'.
     *
     * @return string
     */
    public function get_component(): string {
        return 'mod_' . $this->modname;
    }

    /**
     * Return a plain array representation suitable for serialisation.
     *
     * @param array<string,mixed> $data Serialised entity produced by to_array().
     * @return array<string,mixed>
     */
    public function to_array(): array {
        return [
            'type' => $this->get_type(),
            'id' => $this->id,
            'courseid' => $this->courseid,
            'sectionid' => $this->sectionid,
            'modname' => $this->modname,
            'instance' => $this->instance,
            'name' => $this->name,
            'visible' => $this->visible,
            'availability' => $this->availability,
            'completion' => $this->completion,
            'completionexpected' => $this->completionexpected,
        ];
    }

    /**
     * Reconstruct a cm_item from its array representation.
     *
     * @param array<string,mixed> $data serialised entity.
     * @return static
     * @throws \coding_exception when a required key is missing.
     */
    public static function from_array(array $data): static {
        $cls = static::class;
        return new self(
            (int) self::require_key($data, 'id', $cls),
            (int) self::require_key($data, 'courseid', $cls),
            (int) self::require_key($data, 'sectionid', $cls),
            (string) self::require_key($data, 'modname', $cls),
            (int) self::require_key($data, 'instance', $cls),
            (string) self::require_key($data, 'name', $cls),
            (bool) ($data['visible'] ?? true),
            isset($data['availability']) ? (string) $data['availability'] : null,
            (int) ($data['completion'] ?? 0),
            (int) ($data['completionexpected'] ?? 0)
        );
    }
}
