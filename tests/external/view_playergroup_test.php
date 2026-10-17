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
 * Unit tests for the reject_invite external function.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\external;

use advanced_testcase;
use core_external\external_api;

/**
 * Tests for \mod_playergroup\external\view_playergroup.
 *
 * @covers \mod_playergroup\external\view_playergroup
 */
final class view_playergroup_test extends advanced_testcase {
    /**
     * Creates a course with an activity that completes when it is viewed.
     *
     * @return array [course, cm, student]
     */
    private function create_fixture(): array {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enablecompletion = true;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        return [$course, $cm, $student];
    }

    /**
     * Test that opening the activity in the app completes the "view" rule, as the web page does.
     */
    public function test_viewing_completes_the_view_rule(): void {
        [$course, $cm, $student] = $this->create_fixture();
        $this->setUser($student);
        $info = new \completion_info($course);
        $before = $info->get_data(get_fast_modinfo($course)->get_cm($cm->cmid), false, $student->id)->completionstate;
        $this->assertEquals(COMPLETION_INCOMPLETE, $before);

        $result = view_playergroup::execute($cm->cmid);

        $this->assertTrue($result['status']);
        $this->assertSame([], $result['warnings']);
        $state = $info->get_data(get_fast_modinfo($course)->get_cm($cm->cmid), false, $student->id)->completionstate;
        $this->assertEquals(COMPLETION_COMPLETE, $state);
    }

    /**
     * Test that the visit is logged.
     */
    public function test_viewing_triggers_the_viewed_event(): void {
        [, $cm, $student] = $this->create_fixture();
        $this->setUser($student);
        $sink = $this->redirectEvents();

        view_playergroup::execute($cm->cmid);

        $events = array_filter(
            $sink->get_events(),
            fn($event) => $event instanceof \mod_playergroup\event\course_module_viewed
        );
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertEquals($cm->id, $event->objectid);
        $this->assertEquals(\context_module::instance($cm->cmid)->id, $event->contextid);
    }

    /**
     * Test that someone outside the course is refused, and nothing is recorded.
     */
    public function test_a_user_outside_the_course_is_refused(): void {
        [, $cm] = $this->create_fixture();
        $this->setUser($this->getDataGenerator()->create_user());
        $sink = $this->redirectEvents();

        try {
            view_playergroup::execute($cm->cmid);
            $this->fail('The call should have been refused');
        } catch (\moodle_exception $e) {
            $this->assertCount(0, $sink->get_events());
        }
    }

    /**
     * Test that the capability to view the activity is required.
     */
    public function test_the_view_capability_is_required(): void {
        global $DB;
        [$course, $cm, $student] = $this->create_fixture();
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability('mod/playergroup:view', CAP_PROHIBIT, $roleid, \context_course::instance($course->id), true);
        rebuild_course_cache($course->id, true);
        $this->setUser($student);

        $this->expectException(\moodle_exception::class);
        view_playergroup::execute($cm->cmid);
    }

    /**
     * Test that the answer follows its declared structure.
     */
    public function test_the_result_follows_the_declared_structure(): void {
        [, $cm, $student] = $this->create_fixture();
        $this->setUser($student);

        $result = external_api::clean_returnvalue(view_playergroup::execute_returns(), view_playergroup::execute($cm->cmid));

        $this->assertTrue($result['status']);
        $this->assertArrayHasKey('warnings', $result);
    }
}
