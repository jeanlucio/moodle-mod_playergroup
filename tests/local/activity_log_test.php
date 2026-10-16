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
 * Unit tests for the activity log reader.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

use advanced_testcase;
use context_module;

/**
 * Tests for \mod_playergroup\local\activity_log.
 *
 * @covers \mod_playergroup\local\activity_log
 */
final class activity_log_test extends advanced_testcase {
    /**
     * Enables the standard log store with synchronous writes, so triggered events are readable at once.
     */
    private function enable_standard_logstore(): void {
        $this->preventResetByRollback(); // Logging waits until the transaction is committed.
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        set_config('logguests', 1, 'logstore_standard');
        get_log_manager(true);
    }

    /**
     * Test that no enabled store is reported as such, not as an empty log.
     *
     * With nothing to read from the log cannot be shown, and a teacher must not mistake that for
     * "nobody did anything".
     */
    public function test_get_rows_returns_null_without_a_log_store(): void {
        $this->resetAfterTest();
        set_config('enabled_stores', '', 'tool_log');
        get_log_manager(true);

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);

        $this->assertFalse(activity_log::has_reader());
        $this->assertNull(activity_log::get_rows(context_module::instance($cm->cmid)->id));
    }

    /**
     * Test that an enabled store with no events gives an empty list, which is not null.
     */
    public function test_get_rows_returns_empty_list_when_nothing_was_logged(): void {
        $this->resetAfterTest();
        $this->enable_standard_logstore();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);

        $this->assertTrue(activity_log::has_reader());
        $this->assertSame([], activity_log::get_rows(context_module::instance($cm->cmid)->id));
    }

    /**
     * Test the rows: only this activity's events, newest first, with the localized action and name.
     */
    public function test_get_rows_lists_only_this_activitys_events_newest_first(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable_standard_logstore();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $othercm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $context = context_module::instance($cm->cmid);

        $student = $this->getDataGenerator()->create_user(['firstname' => 'Ana', 'lastname' => 'Silva']);
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        \mod_playergroup\event\group_created::create(['context' => $context, 'objectid' => $group->id])->trigger();
        \mod_playergroup\event\member_joined::create(['context' => $context, 'objectid' => $group->id])->trigger();
        \mod_playergroup\event\group_created::create([
            'context' => context_module::instance($othercm->cmid),
            'objectid' => $group->id,
        ])->trigger();

        // Make the order unambiguous: the join happened after the creation.
        $DB->set_field('logstore_standard_log', 'timecreated', 1000, ['eventname' => '\\mod_playergroup\\event\\group_created']);
        $DB->set_field('logstore_standard_log', 'timecreated', 2000, ['eventname' => '\\mod_playergroup\\event\\member_joined']);

        $rows = activity_log::get_rows($context->id);

        $this->assertCount(2, $rows);
        $this->assertSame(get_string('event_member_joined', 'mod_playergroup'), $rows[0]['action']);
        $this->assertSame(get_string('event_group_created', 'mod_playergroup'), $rows[1]['action']);
        $this->assertSame(fullname($student), $rows[0]['username']);
    }

    /**
     * Test that the limit caps the number of rows.
     */
    public function test_get_rows_honours_the_limit(): void {
        $this->resetAfterTest();
        $this->enable_standard_logstore();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $context = context_module::instance($cm->cmid);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        foreach (range(1, 3) as $unused) {
            \mod_playergroup\event\member_joined::create(['context' => $context, 'objectid' => $group->id])->trigger();
        }

        $this->assertCount(3, activity_log::get_rows($context->id));
        $this->assertCount(2, activity_log::get_rows($context->id, 2));
    }

    /**
     * Test that an entry of a user who no longer exists is shown with a placeholder name.
     */
    public function test_get_rows_uses_a_placeholder_for_unknown_users(): void {
        global $DB;
        $this->resetAfterTest();
        $this->enable_standard_logstore();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $context = context_module::instance($cm->cmid);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        \mod_playergroup\event\member_left::create(['context' => $context, 'objectid' => $group->id])->trigger();

        $DB->set_field('logstore_standard_log', 'userid', 999999, ['eventname' => '\\mod_playergroup\\event\\member_left']);

        $this->assertSame('?', activity_log::get_rows($context->id)[0]['username']);
    }
}
