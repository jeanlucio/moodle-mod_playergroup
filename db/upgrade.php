<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Database upgrade steps for mod_playergroup.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin from one version to the next.
 *
 * @param int $oldversion The old plugin version.
 * @return bool True on success.
 */
function xmldb_playergroup_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026050201) {
        $table = new xmldb_table('playergroup');
        $field = new xmldb_field(
            'deleteemptygroups',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'deletegroups'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026050201, 'playergroup');
    }

    if ($oldversion < 2026061701) {
        $table = new xmldb_table('playergroup_meta');
        $field = new xmldb_field(
            'password',
            XMLDB_TYPE_CHAR,
            '255',
            null,
            null,
            null,
            '',
            'privacy'
        );
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }
        upgrade_mod_savepoint(true, 2026061701, 'playergroup');
    }

    return true;
}
