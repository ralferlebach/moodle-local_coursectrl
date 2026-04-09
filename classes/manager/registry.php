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
 * Activity adapter registry for the Course Control Hub.
 *
 * Discovers all installed coursectrlmod_* subplugins, verifies they provide
 * a class implementing activity_adapter, and exposes lookup methods used by
 * the inventory, preview, bulk, simulation and risk managers.
 *
 * @package    local_coursectrl
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\manager;

use local_coursectrl\local\contract\activity_adapter;

/**
 * Central registry for activity_adapter instances.
 *
 * Not a singleton by design: manager classes instantiate a registry per
 * request scope. Discovery is lazy and cached inside the instance.
 */
class registry {

    /** @var activity_adapter[] Keyed by frankenstyle component name (e.g. 'mod_assign'). */
    private array $adapters = [];

    /** @var bool Whether adapter discovery has already been executed. */
    private bool $loaded = false;

    /** @var string[]|null Optional override for discovery, used by tests. */
    private ?array $classnameoverride;

    /**
     * Constructor.
     *
     * @param string[]|null $classnameoverride Optional list of fully qualified
     *                                          adapter class names. When null
     *                                          (default) adapters are
     *                                          auto-discovered via
     *                                          core_plugin_manager. When an
     *                                          array is passed, auto-discovery
     *                                          is bypassed. Used by tests.
     */
    public function __construct(?array $classnameoverride = null) {
        $this->classnameoverride = $classnameoverride;
    }

    /**
     * Ensure the adapter list has been loaded.
     */
    private function ensure_loaded(): void {
        if ($this->loaded) {
            return;
        }
        $classes = $this->classnameoverride ?? $this->discover_classnames();
        foreach ($classes as $class) {
            $this->try_register($class);
        }
        $this->loaded = true;
    }

    /**
     * Discover candidate adapter class names from installed subplugins.
     *
     * Each coursectrlmod_{name} subplugin is expected to ship a class
     * coursectrlmod_{name}\adapter under classes/adapter.php.
     *
     * @return string[] list of fully qualified class names.
     */
    protected function discover_classnames(): array {
        $result  = [];
        $plugins = \core_plugin_manager::instance()->get_plugins_of_type('coursectrlmod');
        if (!is_array($plugins)) {
            return $result;
        }
        foreach ($plugins as $name => $unused) {
            $result[] = "coursectrlmod_{$name}\\adapter";
        }
        return $result;
    }

    /**
     * Attempt to register a single adapter class.
     *
     * Silently skips (with a developer debugging message) classes that are
     * missing, do not implement the contract, or report themselves as
     * unavailable at runtime.
     *
     * @param string $class fully qualified adapter class name.
     */
    private function try_register(string $class): void {
        if (!class_exists($class)) {
            debugging("Course Control Hub: adapter class {$class} not found, skipping.", \DEBUG_DEVELOPER);
            return;
        }
        if (!is_subclass_of($class, activity_adapter::class, true)) {
            debugging(
                "Course Control Hub: class {$class} does not implement activity_adapter, skipping.",
                \DEBUG_DEVELOPER
            );
            return;
        }
        try {
            /** @var activity_adapter $instance */
            $instance = new $class();
        } catch (\Throwable $e) {
            debugging(
                "Course Control Hub: adapter {$class} failed to instantiate: " . $e->getMessage(),
                \DEBUG_DEVELOPER
            );
            return;
        }
        if (!$instance->is_available()) {
            return;
        }
        $component = $instance::component();
        if ($component === '') {
            debugging(
                "Course Control Hub: adapter {$class} returned empty component name, skipping.",
                \DEBUG_DEVELOPER
            );
            return;
        }
        $this->adapters[$component] = $instance;
    }

    /**
     * Return all successfully registered adapters.
     *
     * @return activity_adapter[] keyed by component name.
     */
    public function get_all(): array {
        $this->ensure_loaded();
        return $this->adapters;
    }

    /**
     * Return the registered adapter for a given component, if any.
     *
     * @param string $component Frankenstyle component name, e.g. 'mod_assign'.
     * @return activity_adapter|null
     */
    public function get_for_component(string $component): ?activity_adapter {
        $this->ensure_loaded();
        return $this->adapters[$component] ?? null;
    }

    /**
     * Return the adapter responsible for a given course module, if any.
     *
     * @param int $cmid course module id.
     * @return activity_adapter|null
     */
    public function get_for_cmid(int $cmid): ?activity_adapter {
        try {
            $cm = get_coursemodule_from_id('', $cmid, 0, false, \MUST_EXIST);
        } catch (\Throwable $e) {
            return null;
        }
        return $this->get_for_component('mod_' . $cm->modname);
    }

    /**
     * Whether an adapter is registered for the given component.
     *
     * @param string $component Frankenstyle component name.
     * @return bool
     */
    public function has(string $component): bool {
        return $this->get_for_component($component) !== null;
    }

    /**
     * Number of registered adapters.
     *
     * @return int
     */
    public function count(): int {
        $this->ensure_loaded();
        return count($this->adapters);
    }
}
