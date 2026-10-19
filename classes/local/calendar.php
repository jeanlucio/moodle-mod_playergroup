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
 * Calendar events for the opening and closing dates of the activity.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

/**
 * Keeps the calendar events of the window of the activity in step with its dates.
 *
 * The window is enforced by the server, but the calendar, the personal calendar and the timeline of the students
 * only know about it through these events. The design is the one of the core activities with a window of dates
 * (choice): an event for each date that is set; when both are, the opening is an ordinary event and the closing
 * the one with an action, and when only one is, that one carries the action.
 */
final class calendar {
    /** @var string Event type of the opening of the window. */
    public const EVENT_OPEN = 'open';

    /** @var string Event type of the closing of the window. */
    public const EVENT_CLOSE = 'close';

    /**
     * Creates, updates or removes the events of the opening and the closing to match the dates of the activity.
     *
     * Safe to call again: the events are found by activity and type, so nothing is made twice, and a repeated
     * event left by something that went wrong is removed.
     *
     * @param \stdClass $playergroup The whole activity record, with its intro.
     * @param int $cmid The course module of the activity.
     * @param bool $visible Whether the course module is visible.
     * @return void
     */
    public static function set_events(\stdClass $playergroup, int $cmid, bool $visible = true): void {
        global $CFG;

        require_once($CFG->dirroot . '/calendar/lib.php');

        $timeopen = (int) $playergroup->timeopen;
        $timeclose = (int) $playergroup->timeclose;

        self::sync_event(
            $playergroup,
            $cmid,
            $visible,
            self::EVENT_OPEN,
            $timeopen,
            $timeclose > 0 ? CALENDAR_EVENT_TYPE_STANDARD : CALENDAR_EVENT_TYPE_ACTION,
            get_string('calendaropen', 'mod_playergroup', $playergroup->name)
        );
        self::sync_event(
            $playergroup,
            $cmid,
            $visible,
            self::EVENT_CLOSE,
            $timeclose,
            CALENDAR_EVENT_TYPE_ACTION,
            get_string('calendarclose', 'mod_playergroup', $playergroup->name)
        );
    }

    /**
     * Brings the events of every activity of a course, or of the whole site, in step with their dates.
     *
     * Used after something changed the dates without going through the form: the reset of the course, which
     * shifts the column and leaves the events behind, and the upgrade that introduced the events.
     *
     * @param int $courseid The course, or 0 for the whole site.
     * @return void
     */
    public static function refresh_events(int $courseid = 0): void {
        global $DB;

        $params = ['module' => 'playergroup'];
        $coursefilter = '';
        if ($courseid > 0) {
            $coursefilter = 'AND pg.course = :courseid';
            $params['courseid'] = $courseid;
        }

        // One query for the activities and their course modules; a module being deleted has no event to keep.
        $sql = "SELECT pg.*, cm.id AS cmid, cm.visible AS cmvisible
                  FROM {playergroup} pg
                  JOIN {course_modules} cm ON cm.instance = pg.id AND cm.deletioninprogress = 0
                  JOIN {modules} m ON m.id = cm.module AND m.name = :module
                 WHERE 1 = 1 $coursefilter";
        $activities = $DB->get_recordset_sql($sql, $params);
        foreach ($activities as $activity) {
            self::set_events($activity, (int) $activity->cmid, !empty($activity->cmvisible));
        }
        $activities->close();
    }

    /**
     * Offers the timeline the action for an event of the window, for a student.
     *
     * The action opens the activity, to form or join a group. It is shown but not usable before the window opens,
     * and goes away once the window closed or the student is already in a group of the activity.
     *
     * @param \cm_info $cm The activity as the student sees it.
     * @param \core_calendar\action_factory $factory The factory of actions.
     * @param int $userid The student.
     * @return \core_calendar\local\event\entities\action_interface|null The action, or null for none.
     */
    public static function window_action(
        \cm_info $cm,
        \core_calendar\action_factory $factory,
        int $userid
    ): ?\core_calendar\local\event\entities\action_interface {
        global $DB;

        $now = time();
        $timeopen = (int) ($cm->customdata['timeopen'] ?? 0);
        $timeclose = (int) ($cm->customdata['timeclose'] ?? 0);
        if ($timeclose > 0 && $timeclose < $now) {
            return null;
        }

        $sql = "SELECT gm.groupid
                  FROM {groups_members} gm
                  JOIN {playergroup_meta} pm ON pm.groupid = gm.groupid
                 WHERE pm.playergroupid = :playergroupid AND gm.userid = :userid";
        if ($DB->record_exists_sql($sql, ['playergroupid' => $cm->instance, 'userid' => $userid])) {
            return null;
        }

        return $factory->create_instance(
            get_string('view'),
            new \moodle_url('/mod/playergroup/view.php', ['id' => $cm->id]),
            1,
            $timeopen <= $now
        );
    }

    /**
     * Makes the event of one date match it: creates it, moves it, or removes it when the date is gone.
     *
     * @param \stdClass $playergroup The whole activity record.
     * @param int $cmid The course module of the activity.
     * @param bool $visible Whether the course module is visible.
     * @param string $eventtype EVENT_OPEN or EVENT_CLOSE.
     * @param int $time The date, or 0 for none.
     * @param int $calendartype CALENDAR_EVENT_TYPE_STANDARD or CALENDAR_EVENT_TYPE_ACTION.
     * @param string $name The name of the event.
     * @return void
     */
    private static function sync_event(
        \stdClass $playergroup,
        int $cmid,
        bool $visible,
        string $eventtype,
        int $time,
        int $calendartype,
        string $name
    ): void {
        global $DB;

        $existing = $DB->get_records('event', [
            'modulename' => 'playergroup',
            'instance'   => $playergroup->id,
            'eventtype'  => $eventtype,
        ], 'id ASC', 'id');

        $kept = array_shift($existing);
        foreach ($existing as $repeated) {
            \calendar_event::load($repeated->id)->delete();
        }

        if ($time <= 0) {
            if ($kept) {
                \calendar_event::load($kept->id)->delete();
            }
            return;
        }

        $event = new \stdClass();
        $event->type         = $calendartype;
        $event->eventtype    = $eventtype;
        $event->name         = $name;
        $event->description  = format_module_intro('playergroup', $playergroup, $cmid, false);
        $event->format       = FORMAT_HTML;
        $event->timestart    = $time;
        $event->timesort     = $time;
        $event->timeduration = 0;
        $event->visible      = $visible ? 1 : 0;

        if ($kept) {
            \calendar_event::load($kept->id)->update($event, false);
            return;
        }

        $event->courseid   = $playergroup->course;
        $event->groupid    = 0;
        $event->userid     = 0;
        $event->modulename = 'playergroup';
        $event->instance   = $playergroup->id;
        \calendar_event::create($event, false);
    }
}
