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
 * Plugininfo class for the coursectrlmod subplugin type.
 *
 * Moodle 4.x requires every custom subplugin type to register a plugininfo
 * class extending \core\plugininfo\base. Without this class Moodle emits a
 * debugging message on every request, which causes moodle-plugin-ci to abort
 * the install step with a non-zero exit code.
 *
 * The class lives in the local_coursectrl namespace because the subplugin type
 * is owned by local_coursectrl:
 *   Namespace:  local_coursectrl\plugininfo
 *   Class name: coursectrlmod
 *   File path:  local/coursectrl/classes/plugininfo/coursectrlmod.php
 *
 * @package    local_coursectrl
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\plugininfo;

/**
 * Plugininfo for the coursectrlmod subplugin type.
 *
 * No overrides are needed for MVP 1. Custom behaviour (e.g. is_uninstall_allowed,
 * load_settings) will be added in later phases when the adapter registry and
 * settings UI are built.
 */
class coursectrlmod extends \core\plugininfo\base {
    // Intentionally empty for Phase 1.
    // Moodle's base implementation covers all required lifecycle methods.
}
