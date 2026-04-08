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
 * Admin settings for local_coursectrl.
 *
 * @package    local_coursectrl
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Admin settings will be configured in Phase 2 (Inventory & Selection).
// Placeholder keeps the settings page registered without errors.
if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_coursectrl',
        get_string('pluginname', 'local_coursectrl')
    );

    if ($ADMIN->fulltree) {
        // No settings defined yet.
    }

    $ADMIN->add('localplugins', $settings);
}
