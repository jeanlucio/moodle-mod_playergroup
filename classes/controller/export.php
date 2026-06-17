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
 * Controller for exporting the teacher activity log report.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export {
    /**
     * Executes the export process using Moodle core dataformat.
     *
     * @param int $contextid The activity module context ID.
     * @param string $format The export format (csv, excel, etc).
     * @param string $courseshortname The course shortname for the filename.
     * @return void
     */
    public function execute(int $contextid, string $format, string $courseshortname): void {
        global $DB;

        // 1. Map the logged events to their localized action labels.
        $eventsmap = [
            '\\mod_playergroup\\event\\group_created'   => get_string('event_group_created', 'mod_playergroup'),
            '\\mod_playergroup\\event\\member_joined'   => get_string('event_member_joined', 'mod_playergroup'),
            '\\mod_playergroup\\event\\member_left'     => get_string('event_member_left', 'mod_playergroup'),
            '\\mod_playergroup\\event\\invite_accepted' => get_string('event_invite_accepted', 'mod_playergroup'),
        ];

        [$inevents, $ineventsparams] = $DB->get_in_or_equal(array_keys($eventsmap), SQL_PARAMS_NAMED, 'evt');

        // JOIN user fields directly so the export can stream row-by-row without a separate bulk fetch.
        $logsql = "SELECT l.id, l.timecreated, l.userid, l.eventname,
                          u.firstname, u.lastname, u.firstnamephonetic,
                          u.lastnamephonetic, u.middlename, u.alternatename
                     FROM {logstore_standard_log} l
                LEFT JOIN {user} u ON u.id = l.userid
                    WHERE l.contextid = :contextid
                      AND l.eventname $inevents
                 ORDER BY l.timecreated DESC";

        $rs = $DB->get_recordset_sql(
            $logsql,
            array_merge(['contextid' => $contextid], $ineventsparams)
        );

        // 2. Stream row-by-row via a generator to avoid loading the full result into memory.
        $exportdata = (function () use ($rs, $eventsmap): \Generator {
            foreach ($rs as $entry) {
                yield [
                    userdate($entry->timecreated),
                    empty($entry->firstname) && empty($entry->lastname) ? '?' : fullname($entry),
                    $eventsmap[$entry->eventname] ?? $entry->eventname,
                ];
            }
            $rs->close();
        })();

        // 4. Define localized headers.
        $columns = [
            get_string('report_date', 'mod_playergroup'),
            get_string('report_user', 'mod_playergroup'),
            get_string('report_action', 'mod_playergroup'),
        ];

        $filename = 'playergroup_activitylog_' . format_string($courseshortname) . '_' . date('Ymd');

        // 5. Trigger download using the Moodle native API.
        \core\dataformat::download_data($filename, $format, $columns, $exportdata);
        die();
    }
}
