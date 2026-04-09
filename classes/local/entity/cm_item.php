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
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\entity;

/**
 * Immutable DTO carrying the course-module-level fields the hub cares about.
 */
final class cm_item extends inventory_item {

    /**
     * Constructor.
     *
     * @param int         $id           Moodle course_modules.id (cmid).
     * @param int         $courseid     Parent course id.
     * @param int         $sectionid    Parent course_sections.id.
     * @param string      $modname      Module short name, e.g. 'assign', 'quiz'.
     * @param int         $instance     Row id inside the module-specific table.
     * @param string      $name         Activity name as shown to users.
     * @param bool        $visible      Current visibility flag.
     * @param string|null $availability JSON availability tree, or null.
     * @param int         $completion   COMPLETION_TRACKING_* constant.
     */
    public function __construct(
        public readonly int $id,
        public readonly int $courseid,
        public readonly int $sectionid,
        public readonly string $modname,
        public readonly int $instance,
        public readonly string $name,
        public readonly bool $visible,
        public readonly ?string $availability,
        public readonly int $completion,
    ) {
    }

    public function get_type(): string {
        return 'cm';
    }

    /**
     * Return the frankenstyle component name, e.g. 'mod_assign'.
     *
     * Convenience accessor used by the registry and the bulk engine.
     *
     * @return string
     */
    public function get_component(): string {
        return 'mod_' . $this->modname;
    }

    public function to_array(): array {
        return [
            'type'         => $this->get_type(),
            'id'           => $this->id,
            'courseid'     => $this->courseid,
            'sectionid'    => $this->sectionid,
            'modname'      => $this->modname,
            'instance'     => $this->instance,
            'name'         => $this->name,
            'visible'      => $this->visible,
            'availability' => $this->availability,
            'completion'   => $this->completion,
        ];
    }

    public static function from_array(array $data): static {
        $cls = static::class;
        return new self(
            id:           (int)    self::require_key($data, 'id', $cls),
            courseid:     (int)    self::require_key($data, 'courseid', $cls),
            sectionid:    (int)    self::require_key($data, 'sectionid', $cls),
            modname:      (string) self::require_key($data, 'modname', $cls),
            instance:     (int)    self::require_key($data, 'instance', $cls),
            name:         (string) self::require_key($data, 'name', $cls),
            visible:      (bool)   ($data['visible'] ?? true),
            availability: isset($data['availability']) ? (string)$data['availability'] : null,
            completion:   (int)    ($data['completion'] ?? 0),
        );
    }
}
