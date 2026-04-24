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
 * Public API for querying group membership in a course.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\api;

/**
 * Read-only API used by external plugins (e.g. block_playerhud) to query
 * a student's group within a given course.
 */
class group_info {
    /**
     * Returns summary information about the first playergroup the user belongs
     * to in the given course, or null if the user has no group.
     *
     * The returned object contains:
     *  - int    groupid
     *  - string groupname
     *  - string badge
     *  - int    membercount
     *  - int    maxmembers
     *  - string leadername  (empty string when unknown)
     *
     * @param int $courseid The Moodle course ID.
     * @param int $userid   The user whose group should be fetched.
     * @return \stdClass|null Summary object, or null if the user has no group.
     */
    public static function get_player_group_in_course(int $courseid, int $userid): ?\stdClass {
        global $DB;

        $sql = "SELECT g.id        AS groupid,
                       g.name      AS groupname,
                       pm.badge    AS badge,
                       pg.maxmembers,
                       pm.creatorid,
                       (SELECT COUNT(gm2.id)
                          FROM {groups_members} gm2
                         WHERE gm2.groupid = g.id) AS membercount
                  FROM {groups_members} gm
                  JOIN {groups} g             ON g.id = gm.groupid
                  JOIN {playergroup_meta} pm  ON pm.groupid = g.id
                  JOIN {playergroup} pg       ON pg.id = pm.playergroupid
                 WHERE gm.userid = :userid
                   AND pg.course = :courseid
              ORDER BY g.name ASC";

        $row = $DB->get_record_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);

        if (!$row) {
            return null;
        }

        $leadername = '';
        if (!empty($row->creatorid)) {
            $creatorfields = 'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename';
            $creator = $DB->get_record('user', ['id' => $row->creatorid], $creatorfields);
            if ($creator) {
                $leadername = fullname($creator);
            }
        }

        $result = new \stdClass();
        $result->groupid     = (int) $row->groupid;
        $result->groupname   = (string) $row->groupname;
        $result->badge       = !empty($row->badge) ? (string) $row->badge : '🛡️';
        $result->membercount = (int) $row->membercount;
        $result->maxmembers  = (int) $row->maxmembers;
        $result->leadername  = $leadername;

        return $result;
    }
}
