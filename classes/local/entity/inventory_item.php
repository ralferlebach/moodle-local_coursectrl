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
 * Abstract base for all normalised inventory entities in the Course Control Hub.
 *
 * Concrete subclasses (course_item, section_item, cm_item, text_item, ...)
 * are pure DTOs. They hold no references to Moodle's $DB and perform no
 * persistence of their own; the inventory_service builds them and the
 * managers consume them.
 *
 * @package    local_coursectrl
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\entity;

/**
 * Abstract inventory entity.
 *
 * Provides a uniform serialisation contract for every kind of course object
 * that the inventory, bulk, visualisation and simulation engines deal with.
 */
abstract class inventory_item implements \JsonSerializable {
    /**
     * Return the short type discriminator for this entity kind.
     *
     * Canonical values: 'course', 'section', 'cm', 'text'. Additional values
     * may be introduced by later phases (e.g. 'batch', 'risk') but must
     * remain stable across versions once used.
     *
     * @return string type discriminator.
     */
    abstract public function get_type(): string;

    /**
     * Return a plain associative array representation of this entity.
     *
     * The returned structure must round-trip through from_array() without
     * loss. It is used for JSON serialisation, event payloads and test
     * fixtures.
     *
     * @param array<string,mixed> $data Serialised entity produced by to_array().
     * @return array<string,mixed>
     */
    abstract public function to_array(): array;

    /**
     * Reconstruct an entity from its array representation.
     *
     * @param array<string,mixed> $data serialised entity.
     * @return static
     * @throws \coding_exception when a required key is missing.
     */
    abstract public static function from_array(array $data): static;

    /**
     * JsonSerializable hook. Delegates to to_array().
     *
     * @param array<string,mixed> $data Source array to validate.
     * @param string $key Required key name.
     * @param string $class Calling class name used in error context.
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array {
        return $this->to_array();
    }

    /**
     * Fetch a required key from an input array or throw.
     *
     * Helper for concrete from_array() implementations.
     *
     * @param array<string,mixed> $data  source array.
     * @param string              $key   required key.
     * @param string              $class calling class for error context.
     * @return mixed the value at $key.
     * @throws \coding_exception when $key is absent.
     */
    protected static function require_key(array $data, string $key, string $class) {
        if (!array_key_exists($key, $data)) {
            throw new \coding_exception("Missing required key '{$key}' for {$class}::from_array().");
        }
        return $data[$key];
    }
}
