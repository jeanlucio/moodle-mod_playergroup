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
 * Reads the activity log of a PlayerGroup activity through the core log readers.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

/**
 * Builds the rows of the teacher activity log, for the report tab and the export alike.
 *
 * Events are read from whichever log store the site has enabled, not from a hardcoded table: an
 * administrator may turn the standard log off or keep only an external one, and a query against
 * logstore_standard_log would then return nothing and look like "no activity".
 */
final class activity_log {
    /**
     * Returns the events shown in the log, keyed by event class name, with their localized label.
     *
     * @return string[] Label per event name (with the leading backslash the log stores).
     */
    public static function get_events_map(): array {
        return [
            '\\mod_playergroup\\event\\group_created'   => get_string('event_group_created', 'mod_playergroup'),
            '\\mod_playergroup\\event\\member_joined'   => get_string('event_member_joined', 'mod_playergroup'),
            '\\mod_playergroup\\event\\member_left'     => get_string('event_member_left', 'mod_playergroup'),
            '\\mod_playergroup\\event\\invite_accepted' => get_string('event_invite_accepted', 'mod_playergroup'),
        ];
    }

    /**
     * Tells whether at least one enabled log store can be queried.
     *
     * @return bool True when the log can be read.
     */
    public static function has_reader(): bool {
        return !empty(get_log_manager()->get_readers('\\core\\log\\sql_reader'));
    }

    /**
     * Returns the log rows of one activity, newest first.
     *
     * The rows are small (a handful of events per student), so they are read into memory in one pass.
     * That lets the user names be fetched in a single query instead of one per row.
     *
     * @param int $contextid The activity module context ID.
     * @param int $limit Maximum number of rows; 0 means all.
     * @return array[]|null Rows of ['date' => string, 'username' => string, 'action' => string], or
     *                      null when no log store that can be queried is enabled.
     */
    public static function get_rows(int $contextid, int $limit = 0): ?array {
        global $DB;

        $readers = get_log_manager()->get_readers('\\core\\log\\sql_reader');
        if (empty($readers)) {
            return null;
        }
        $reader = reset($readers);

        $eventsmap = self::get_events_map();
        [$inevents, $params] = $DB->get_in_or_equal(array_keys($eventsmap), SQL_PARAMS_NAMED, 'evt');
        $params['contextid'] = $contextid;

        $entries = [];
        $events = $reader->get_events_select_iterator(
            "contextid = :contextid AND eventname $inevents",
            $params,
            'timecreated DESC',
            0,
            $limit
        );
        foreach ($events as $event) {
            $entries[] = [
                'timecreated' => (int) $event->timecreated,
                'userid' => (int) $event->userid,
                'eventname' => $event->eventname,
            ];
        }
        $events->close();

        $users = [];
        $userids = array_unique(array_column($entries, 'userid'));
        if (!empty($userids)) {
            $userfields = 'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename';
            $users = $DB->get_records_list('user', 'id', $userids, '', $userfields);
        }

        $rows = [];
        foreach ($entries as $entry) {
            $user = $users[$entry['userid']] ?? null;
            $rows[] = [
                'date' => userdate($entry['timecreated']),
                'username' => $user ? fullname($user) : '?',
                'action' => $eventsmap[$entry['eventname']] ?? $entry['eventname'],
            ];
        }

        return $rows;
    }
}
