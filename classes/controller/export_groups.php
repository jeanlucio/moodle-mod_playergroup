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

namespace mod_playergroup\controller;

/**
 * Controller for exporting the teacher groups-and-members composition report.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_groups {
    /**
     * Executes the export process using Moodle core dataformat.
     *
     * @param int $playergroupid The activity instance ID whose groups are exported.
     * @param string $format The export format (csv, excel, etc).
     * @param string $courseshortname The course shortname for the filename.
     * @return void
     */
    public function execute(int $playergroupid, string $format, string $courseshortname): void {
        global $DB;

        // 1. Load the groups belonging to this activity instance.
        $groupsql = "SELECT g.id, g.name, pm.privacy, pm.creatorid
                       FROM {playergroup_meta} pm
                       JOIN {groups} g ON g.id = pm.groupid
                      WHERE pm.playergroupid = :playergroupid
                   ORDER BY g.name ASC";
        $groups = $DB->get_records_sql($groupsql, ['playergroupid' => $playergroupid]);

        // 2. Bulk-load all members of those groups to avoid a per-group query.
        $groupids = array_map(fn($g): int => (int) $g->id, $groups);
        $membersbygroup = [];
        if (!empty($groupids)) {
            [$groupidsinsql, $groupidsinparams] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'grp');
            $membersql = "SELECT gm.id, gm.groupid, u.id AS userid, u.firstname, u.lastname, u.firstnamephonetic,
                                 u.lastnamephonetic, u.middlename, u.alternatename
                            FROM {groups_members} gm
                            JOIN {user} u ON u.id = gm.userid
                           WHERE gm.groupid $groupidsinsql
                        ORDER BY gm.groupid ASC, gm.timeadded ASC";
            foreach ($DB->get_records_sql($membersql, $groupidsinparams) as $member) {
                $membersbygroup[(int) $member->groupid][] = $member;
            }
        }

        // 3. Stream one row per member via a generator to avoid loading it all into memory.
        $exportdata = (function () use ($groups, $membersbygroup): \Generator {
            foreach ($groups as $g) {
                $groupid = (int) $g->id;
                $privacy = (int) ($g->privacy ?? 0);

                if ($privacy === 1) {
                    $privacylabel = get_string('groupprotected', 'mod_playergroup');
                } else if ($privacy === 2) {
                    $privacylabel = get_string('groupclosed', 'mod_playergroup');
                } else {
                    $privacylabel = get_string('groupopen', 'mod_playergroup');
                }

                foreach ($membersbygroup[$groupid] ?? [] as $member) {
                    if ((int) $member->userid === (int) $g->creatorid) {
                        $role = get_string('leader', 'mod_playergroup');
                    } else {
                        $role = get_string('member', 'mod_playergroup');
                    }

                    yield [
                        format_string($g->name),
                        $privacylabel,
                        $role,
                        fullname($member),
                    ];
                }
            }
        })();

        // 4. Define localized headers.
        $columns = [
            get_string('report_group', 'mod_playergroup'),
            get_string('privacy', 'mod_playergroup'),
            get_string('report_role', 'mod_playergroup'),
            get_string('report_name', 'mod_playergroup'),
        ];

        $filename = 'playergroup_groups_' . format_string($courseshortname) . '_' . date('Ymd');

        // 5. Trigger download using the Moodle native API.
        \core\dataformat::download_data($filename, $format, $columns, $exportdata);
        die();
    }
}
