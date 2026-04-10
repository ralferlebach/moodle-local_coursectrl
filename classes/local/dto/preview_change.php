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
 * Data transfer object representing a single previewed change.
 *
 * One preview_change instance carries the per-cmid result of an adapter's
 * preview_action() call, normalised into a shape the bulk preview UI and
 * the batch_item persistent layer can consume directly. Instances are
 * immutable: properties are set in the constructor and exposed via getters.
 *
 * The preview_manager (patch-024) collects preview_change instances across
 * all adapters in a course and feeds them into the preview Mustache
 * template; the batch_manager (patch-025) serialises the chosen subset
 * into batch_item.previewjson.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\dto;

/**
 * Immutable DTO for one previewed bulk-action change.
 */
final class preview_change {
    /** @var int Course module id this change targets. */
    private int $cmid;

    /** @var string Frankenstyle component name, e.g. 'mod_assign'. */
    private string $component;

    /** @var string Display name of the affected instance. */
    private string $name;

    /** @var array Per-field old/new/shifted descriptors as returned by the adapter. */
    private array $fields;

    /**
     * Constructor.
     *
     * @param int    $cmid      course module id.
     * @param string $component frankenstyle component name.
     * @param string $name      display name of the instance.
     * @param array  $fields    per-field preview descriptors.
     */
    public function __construct(int $cmid, string $component, string $name, array $fields) {
        $this->cmid = $cmid;
        $this->component = $component;
        $this->name = $name;
        $this->fields = $fields;
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
     * Returns the frankenstyle component name.
     *
     * @return string
     */
    public function get_component(): string {
        return $this->component;
    }

    /**
     * Returns the display name of the affected instance.
     *
     * @return string
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Returns the per-field preview descriptors.
     *
     * @return array
     */
    public function get_fields(): array {
        return $this->fields;
    }

    /**
     * Returns true if at least one field would actually change.
     *
     * @return bool
     */
    public function has_changes(): bool {
        foreach ($this->fields as $descriptor) {
            if (!empty($descriptor['shifted'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Render this DTO as a plain array suitable for JSON encoding into the
     * batch_item.previewjson column.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'cmid'      => $this->cmid,
            'component' => $this->component,
            'name'      => $this->name,
            'fields'    => $this->fields,
        ];
    }
}
