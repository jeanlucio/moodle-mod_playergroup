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
 * Tests for the events of the activity.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\event;

use advanced_testcase;

/**
 * Tests for the events the activity triggers.
 *
 * The log report, the activity report of the plugin and the site logs read their name, description and
 * link, so a broken one shows up as an empty or failing row long after the event was written.
 *
 * @covers \mod_playergroup\event\group_created
 * @covers \mod_playergroup\event\group_deleted
 * @covers \mod_playergroup\event\invite_accepted
 * @covers \mod_playergroup\event\member_joined
 * @covers \mod_playergroup\event\member_left
 * @covers \mod_playergroup\event\course_module_viewed
 */
final class events_test extends advanced_testcase {
    /**
     * Provides each group event with the name string it must show.
     *
     * @return array[] Event class and the language string of its name.
     */
    public static function group_events(): array {
        return [
            'group created' => [group_created::class, 'event_group_created'],
            'group deleted' => [group_deleted::class, 'event_group_deleted'],
            'invite accepted' => [invite_accepted::class, 'event_invite_accepted'],
            'member joined' => [member_joined::class, 'event_member_joined'],
            'member left' => [member_left::class, 'event_member_left'],
        ];
    }

    /**
     * Test the name, description and link of each event, and that it is dispatched.
     *
     * @dataProvider group_events
     * @param string $class Event class.
     * @param string $namestring Language string of the event name.
     */
    public function test_event_describes_itself(string $class, string $namestring): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($user);
        $context = \context_module::instance($cm->cmid);
        $sink = $this->redirectEvents();

        $event = $class::create(['context' => $context, 'objectid' => 77]);
        $event->trigger();

        $this->assertCount(1, $sink->get_events());
        $this->assertSame(get_string($namestring, 'mod_playergroup'), $class::get_name());
        $this->assertNotSame('', $class::get_name());

        $description = $event->get_description();
        $this->assertStringContainsString("'77'", $description);
        $this->assertStringContainsString("'{$cm->cmid}'", $description);
        if ($class !== group_deleted::class) {
            // The deletion of an empty group is automatic, so its description names no user.
            $this->assertStringContainsString("'{$user->id}'", $description);
        }

        $url = $event->get_url();
        // The site may live in a subfolder, so only the end of the path is fixed.
        $this->assertStringEndsWith('/mod/playergroup/view.php', $url->get_path());
        $this->assertEquals($cm->cmid, $url->get_param('id'));
        $this->assertSame($context->id, $event->contextid);
    }

    /**
     * Test the event fired when someone opens the activity.
     */
    public function test_course_module_viewed_is_read_only_and_points_at_the_activity(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $context = \context_module::instance($cm->cmid);
        $sink = $this->redirectEvents();

        $event = course_module_viewed::create(['context' => $context, 'objectid' => $cm->id]);
        $event->trigger();

        $this->assertCount(1, $sink->get_events());
        $this->assertSame('r', $event->crud);
        $this->assertSame('playergroup', $event->objecttable);
        $this->assertEquals($cm->id, $event->objectid);
    }
}
