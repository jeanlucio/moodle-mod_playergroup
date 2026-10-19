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

    if ($oldversion < 2026101600) {
        $table = new xmldb_table('playergroup_qualified');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('playergroupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timequalified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('playergroupid_fk', XMLDB_KEY_FOREIGN, ['playergroupid'], 'playergroup', ['id']);
            $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key('playergroupid_userid', XMLDB_KEY_UNIQUE, ['playergroupid', 'userid']);
            $dbman->create_table($table);
        }

        // Before the minimum size counted, a student earned the grade and the completion by being in
        // any group, so everyone who is in one now, or who already holds a grade, has earned it and
        // must keep it. Reading the two sources and writing in batches avoids a query per student.
        // Skipping rows that already exist lets an interrupted upgrade run again safely.
        $earned = [];

        $sql = "SELECT gm.userid, pm.playergroupid, MIN(gm.timeadded) AS firsttime
                  FROM {groups_members} gm
                  JOIN {playergroup_meta} pm ON pm.groupid = gm.groupid
                  JOIN {playergroup} pg ON pg.id = pm.playergroupid
              GROUP BY gm.userid, pm.playergroupid";
        $members = $DB->get_recordset_sql($sql);
        foreach ($members as $member) {
            $earned[(int) $member->playergroupid][(int) $member->userid] = (int) $member->firsttime;
        }
        $members->close();

        // A recorded grade keeps its own date, which is what the gradebook already reports as submitted.
        // Grades from older versions may have no creation time, so the last modification stands in.
        $sql = "SELECT gg.userid, gi.iteminstance AS playergroupid,
                       MIN(CASE WHEN gg.timecreated > 0 THEN gg.timecreated ELSE gg.timemodified END) AS granted
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                  JOIN {playergroup} pg ON pg.id = gi.iteminstance
                 WHERE gi.itemtype = 'mod'
                       AND gi.itemmodule = 'playergroup'
                       AND gi.itemnumber = 0
                       AND (gg.rawgrade IS NOT NULL OR gg.finalgrade IS NOT NULL)
              GROUP BY gg.userid, gi.iteminstance";
        $grades = $DB->get_recordset_sql($sql);
        foreach ($grades as $grade) {
            $granted = (int) $grade->granted;
            $previous = $earned[(int) $grade->playergroupid][(int) $grade->userid] ?? 0;
            // Never leave the date empty: a real date is better than the start of 1970 in the gradebook.
            $earned[(int) $grade->playergroupid][(int) $grade->userid] = $granted > 0 ? $granted : ($previous ?: time());
        }
        $grades->close();

        $existing = [];
        $rows = $DB->get_recordset('playergroup_qualified', null, '', 'id, playergroupid, userid');
        foreach ($rows as $row) {
            $existing[(int) $row->playergroupid][(int) $row->userid] = true;
        }
        $rows->close();

        $batch = [];
        foreach ($earned as $playergroupid => $users) {
            foreach ($users as $userid => $time) {
                if (isset($existing[$playergroupid][$userid])) {
                    continue;
                }
                $batch[] = (object) [
                    'playergroupid' => $playergroupid,
                    'userid' => $userid,
                    'timequalified' => $time,
                ];
                if (count($batch) >= 1000) {
                    $DB->insert_records('playergroup_qualified', $batch);
                    $batch = [];
                }
            }
        }
        if (!empty($batch)) {
            $DB->insert_records('playergroup_qualified', $batch);
        }

        upgrade_mod_savepoint(true, 2026101600, 'playergroup');
    }

    if ($oldversion < 2026101900) {
        // The opening and closing dates now have calendar events. Activities saved before this version have the
        // dates and no event, and the events are only made when an activity is saved. The refresh finds the
        // event of each activity by type before it makes one, so running the step again changes nothing.
        \mod_playergroup\local\calendar::refresh_events();

        upgrade_mod_savepoint(true, 2026101900, 'playergroup');
    }

    return true;
}
