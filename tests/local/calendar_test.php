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
 * Unit tests for the calendar events of the opening and closing dates.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

use advanced_testcase;

/**
 * Tests for \mod_playergroup\local\calendar.
 *
 * The window is enforced by the server, but only an event makes it known to who has not opened the activity:
 * the calendar and the timeline of the students read these events.
 *
 * @covers \mod_playergroup\local\calendar
 * @covers ::playergroup_refresh_events
 * @covers ::playergroup_add_instance
 * @covers ::playergroup_update_instance
 */
final class calendar_test extends advanced_testcase {
    /** @var int A fixed opening time, far from now so nothing depends on the clock. */
    private const OPEN = 1900000000;

    /** @var int A fixed closing time, after the opening. */
    private const CLOSE = 1900500000;

    /**
     * Loads the library of the plugin, which the tests call.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/playergroup/lib.php');
    }

    /**
     * Returns the calendar events of the window of an activity, keyed by event type.
     *
     * @param int $instanceid The activity.
     * @return \stdClass[] Event records keyed by event type.
     */
    private function events(int $instanceid): array {
        global $DB;

        $records = $DB->get_records_select(
            'event',
            "modulename = 'playergroup' AND instance = :instance AND eventtype IN ('open', 'close')",
            ['instance' => $instanceid],
            'id ASC'
        );

        $bytype = [];
        foreach ($records as $record) {
            $this->assertArrayNotHasKey($record->eventtype, $bytype, 'One event per type is expected.');
            $bytype[$record->eventtype] = $record;
        }
        return $bytype;
    }

    /**
     * Creates an activity.
     *
     * @param int $timeopen Opening time, or 0.
     * @param int $timeclose Closing time, or 0.
     * @param array $extra Other fields of the activity.
     * @return \stdClass The generator result: id is the instance, cmid the course module.
     */
    private function create_activity(int $timeopen, int $timeclose, array $extra = []): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        return $this->getDataGenerator()->create_module('playergroup', array_merge([
            'course'    => $course->id,
            'timeopen'  => $timeopen,
            'timeclose' => $timeclose,
        ], $extra));
    }

    /**
     * Test that an activity with both dates gets an ordinary opening event and a closing event with an action.
     */
    public function test_both_dates_make_a_plain_opening_and_an_action_closing(): void {
        $cm = $this->create_activity(self::OPEN, self::CLOSE);

        $events = $this->events((int) $cm->id);

        $this->assertSame(['open', 'close'], array_keys($events));
        $this->assertEquals(self::OPEN, (int) $events['open']->timestart);
        $this->assertEquals(self::CLOSE, (int) $events['close']->timestart);
        $this->assertEquals(CALENDAR_EVENT_TYPE_STANDARD, (int) $events['open']->type);
        $this->assertEquals(CALENDAR_EVENT_TYPE_ACTION, (int) $events['close']->type);
        $this->assertEquals(0, (int) $events['open']->userid);
        $this->assertEquals($cm->course, (int) $events['open']->courseid);
    }

    /**
     * Test that the only date there is carries the action.
     */
    public function test_a_single_date_makes_an_event_with_an_action(): void {
        $onlyopen = $this->create_activity(self::OPEN, 0);
        $onlyclose = $this->create_activity(0, self::CLOSE);

        $open = $this->events((int) $onlyopen->id);
        $this->assertSame(['open'], array_keys($open));
        $this->assertEquals(CALENDAR_EVENT_TYPE_ACTION, (int) $open['open']->type);

        $close = $this->events((int) $onlyclose->id);
        $this->assertSame(['close'], array_keys($close));
        $this->assertEquals(CALENDAR_EVENT_TYPE_ACTION, (int) $close['close']->type);
    }

    /**
     * Test that an activity without a window has no event.
     */
    public function test_no_dates_make_no_events(): void {
        $cm = $this->create_activity(0, 0);

        $this->assertSame([], $this->events((int) $cm->id));
    }

    /**
     * Test that the events carry the name of the activity, its description and the module they belong to.
     */
    public function test_the_events_are_named_after_the_activity(): void {
        $cm = $this->create_activity(self::OPEN, self::CLOSE, ['name' => 'Teams of May', 'intro' => 'Form your team']);

        $events = $this->events((int) $cm->id);

        $this->assertSame(get_string('calendaropen', 'mod_playergroup', 'Teams of May'), $events['open']->name);
        $this->assertSame(get_string('calendarclose', 'mod_playergroup', 'Teams of May'), $events['close']->name);
        $this->assertStringContainsString('Form your team', $events['open']->description);
    }

    /**
     * Test that the event of a hidden activity is hidden too.
     */
    public function test_the_events_of_a_hidden_activity_are_hidden(): void {
        $cm = $this->create_activity(self::OPEN, self::CLOSE, ['visible' => 0]);

        foreach ($this->events((int) $cm->id) as $event) {
            $this->assertEquals(0, (int) $event->visible);
        }
    }

    /**
     * Test that editing a date moves its event instead of making another one.
     */
    public function test_changing_a_date_updates_its_event(): void {
        $cm = $this->create_activity(self::OPEN, self::CLOSE);
        $before = $this->events((int) $cm->id);

        playergroup_update_instance((object) [
            'instance' => $cm->id, 'coursemodule' => $cm->cmid, 'course' => $cm->course, 'name' => $cm->name,
            'minmembers' => 2, 'maxmembers' => 5, 'groupingmode' => 'none',
            'timeopen' => self::OPEN + DAYSECS, 'timeclose' => self::CLOSE,
        ]);
        $after = $this->events((int) $cm->id);

        $this->assertEquals(self::OPEN + DAYSECS, (int) $after['open']->timestart);
        $this->assertEquals(self::OPEN + DAYSECS, (int) $after['open']->timesort);
        $this->assertSame($before['open']->id, $after['open']->id);
        $this->assertSame($before['close']->id, $after['close']->id);
    }

    /**
     * Test that clearing one date removes only its event, and that the other one then takes the action.
     */
    public function test_clearing_one_date_removes_only_its_event(): void {
        $cm = $this->create_activity(self::OPEN, self::CLOSE);
        $update = (object) [
            'instance' => $cm->id, 'coursemodule' => $cm->cmid, 'course' => $cm->course, 'name' => $cm->name,
            'minmembers' => 2, 'maxmembers' => 5, 'groupingmode' => 'none',
            'timeopen' => self::OPEN, 'timeclose' => 0,
        ];

        playergroup_update_instance($update);
        $events = $this->events((int) $cm->id);
        $this->assertSame(['open'], array_keys($events));
        $this->assertEquals(CALENDAR_EVENT_TYPE_ACTION, (int) $events['open']->type);

        $update->timeopen = 0;
        $update->timeclose = self::CLOSE;
        playergroup_update_instance($update);
        $this->assertSame(['close'], array_keys($this->events((int) $cm->id)));

        $update->timeclose = 0;
        playergroup_update_instance($update);
        $this->assertSame([], $this->events((int) $cm->id));
    }

    /**
     * Test that a date added later gets its event.
     */
    public function test_adding_a_date_later_creates_its_event(): void {
        $cm = $this->create_activity(0, 0);

        playergroup_update_instance((object) [
            'instance' => $cm->id, 'coursemodule' => $cm->cmid, 'course' => $cm->course, 'name' => $cm->name,
            'minmembers' => 2, 'maxmembers' => 5, 'groupingmode' => 'none',
            'timeopen' => self::OPEN, 'timeclose' => self::CLOSE,
        ]);

        $this->assertSame(['open', 'close'], array_keys($this->events((int) $cm->id)));
    }

    /**
     * Test that the refresh recreates what is missing and never makes a second event, whichever way it is asked.
     */
    public function test_refreshing_rebuilds_the_events_without_repeating_them(): void {
        global $DB;

        $first = $this->create_activity(self::OPEN, self::CLOSE);
        $second = $this->create_activity(self::OPEN, self::CLOSE);
        $deleteall = fn() => $DB->delete_records_select(
            'event',
            "modulename = 'playergroup' AND eventtype IN ('open', 'close')"
        );

        // By record.
        $deleteall();
        $this->assertTrue(playergroup_refresh_events(0, $DB->get_record('playergroup', ['id' => $first->id])));
        $this->assertSame(['open', 'close'], array_keys($this->events((int) $first->id)));
        $this->assertSame([], $this->events((int) $second->id));

        // By id.
        $deleteall();
        playergroup_refresh_events(0, (int) $first->id);
        $this->assertSame(['open', 'close'], array_keys($this->events((int) $first->id)));
        $this->assertSame([], $this->events((int) $second->id));

        // By course.
        $deleteall();
        playergroup_refresh_events((int) $second->course);
        $this->assertSame([], $this->events((int) $first->id));
        $this->assertSame(['open', 'close'], array_keys($this->events((int) $second->id)));

        // For the whole site, twice: the second pass must find the events it made.
        $deleteall();
        playergroup_refresh_events();
        playergroup_refresh_events();
        $this->assertSame(['open', 'close'], array_keys($this->events((int) $first->id)));
        $this->assertSame(['open', 'close'], array_keys($this->events((int) $second->id)));
    }

    /**
     * Test that a repeated event, left by something that went wrong, is cleaned up by the next refresh.
     */
    public function test_refreshing_removes_a_repeated_event(): void {
        global $DB;

        $cm = $this->create_activity(self::OPEN, self::CLOSE);
        $copy = $DB->get_record('event', ['modulename' => 'playergroup', 'instance' => $cm->id, 'eventtype' => 'open']);
        unset($copy->id);
        $DB->insert_record('event', $copy);
        $this->assertSame(2, $DB->count_records('event', ['modulename' => 'playergroup', 'eventtype' => 'open']));

        playergroup_refresh_events(0, (int) $cm->id);

        $this->assertSame(1, $DB->count_records('event', ['modulename' => 'playergroup', 'eventtype' => 'open']));
        $this->assertSame(['open', 'close'], array_keys($this->events((int) $cm->id)));
    }

    /**
     * Test that the refresh leaves the event of the expected completion date alone.
     */
    public function test_refreshing_keeps_the_expected_completion_event(): void {
        global $CFG, $DB;

        $CFG->enablecompletion = true;
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id, 'timeopen' => self::OPEN, 'timeclose' => self::CLOSE,
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => 1,
            'completionexpected' => self::CLOSE,
        ]);

        playergroup_refresh_events((int) $course->id);

        $this->assertSame(1, $DB->count_records('event', [
            'modulename' => 'playergroup',
            'instance'   => $cm->id,
            'eventtype'  => \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED,
        ]));
        $this->assertSame(['open', 'close'], array_keys($this->events((int) $cm->id)));
    }

    /**
     * Test that deleting the activity leaves no event behind.
     */
    public function test_deleting_the_activity_removes_its_events(): void {
        global $DB;

        $cm = $this->create_activity(self::OPEN, self::CLOSE);
        $this->assertCount(2, $this->events((int) $cm->id));

        course_delete_module($cm->cmid);

        $this->assertSame(0, $DB->count_records('event', ['modulename' => 'playergroup', 'instance' => $cm->id]));
    }
}
