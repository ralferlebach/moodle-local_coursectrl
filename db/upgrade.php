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
 * Upgrade steps for local_coursectrl.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Perform the upgrade steps.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool True on success.
 */
function xmldb_local_coursectrl_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026041903) {
        // Add local_coursectrl_risk table introduced in 0.1.58.
        $table = new xmldb_table('local_coursectrl_risk');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('risktype', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
            $table->add_field('severity', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
            $table->add_field('entitytype', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL);
            $table->add_field('entityid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('detailsjson', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $table->add_index('idx_risk_course', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $table->add_index('idx_risk_type', XMLDB_INDEX_NOTUNIQUE, ['risktype']);
            $table->add_index('idx_risk_severity', XMLDB_INDEX_NOTUNIQUE, ['severity']);
            $table->add_index('idx_risk_entity', XMLDB_INDEX_NOTUNIQUE, ['entitytype', 'entityid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026041903, 'local', 'coursectrl');
    }

    if ($oldversion < 2026041904) {
        // Version bump for 0.1.59 (no schema change).
        upgrade_plugin_savepoint(true, 2026041904, 'local', 'coursectrl');
    }

    return true;
}
