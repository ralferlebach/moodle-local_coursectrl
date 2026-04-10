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
 * Base test fixture extending the production abstract_activity_adapter.
 *
 * Since patch-017 the no-op default implementations live in the production
 * namespace under classes/local/contract/abstract_activity_adapter.php. This
 * fixture is now a thin non-namespaced shim that exists only because the
 * concrete fakes (fake_adapter_assign, fake_adapter_quiz, ...) are PSR-1
 * non-namespaced classes loaded via require_once and need a common parent
 * they can extend without triggering the registry's component-name discovery.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Non-namespaced fixture base. Inherits all 12 no-op defaults from the
 * production abstract base class. Concrete fake adapters extend this class
 * and need only override component() (and any methods relevant to a given
 * test scenario).
 */
abstract class local_coursectrl_fake_adapter_base extends \local_coursectrl\local\contract\abstract_activity_adapter {
}
