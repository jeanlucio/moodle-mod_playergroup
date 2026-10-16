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
 * Unit tests for the course reset.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

use advanced_testcase;
use mod_playergroup\completion\custom_completion;
use mod_playergroup\external\create_group;
use mod_playergroup\external\join_group;
use mod_playergroup\external\send_invite;

/**
 * Tests for the course reset, run through the real reset_course_userdata() of the core.
 *
 * @covers \mod_playergroup\local\course_reset
 * @covers ::playergroup_reset_userdata
 * @covers \mod_playergroup\observer
 */
final class course_reset_test extends advanced_testcase {
    /** @var \stdClass Course with completion enabled. */
    private \stdClass $course;

    /**
     * Loads the libraries the core reset needs and creates the course.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $CFG->enablecompletion = true;
        require_once($CFG->libdir . '/moodlelib.php');
        require_once($CFG->dirroot . '/group/lib.php');
        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
    }

    /**
     * Runs the core reset with the given options.
     *
     * @param \stdClass $course The course to reset.
     * @param array $options Core reset options, such as reset_completion.
     * @return array[] The status rows the core reports.
     */
    private function run_reset(\stdClass $course, array $options): array {
        $data = (object) array_merge(['id' => $course->id], $options);
        $this->setAdminUser();
        return reset_course_userdata($data);
    }

    /**
     * Creates an activity in the course.
     *
     * @param \stdClass|null $course Course; defaults to the test course.
     * @return \stdClass Generator result.
     */
    private function create_activity(?\stdClass $course = null): \stdClass {
        return $this->getDataGenerator()->create_module('playergroup', [
            'course' => ($course ?? $this->course)->id,
            'minmembers' => 1,
            'grade' => 10,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionjoingroup' => 1,
        ]);
    }

    /**
     * Makes a new student found a group in the activity, which qualifies them (the minimum is 1).
     *
     * @param \stdClass $activity The activity.
     * @param \stdClass|null $course Course; defaults to the test course.
     * @param string $role Role to enrol the user with.
     * @return array [user, groupid]
     */
    private function student_with_group(\stdClass $activity, ?\stdClass $course = null, string $role = 'student'): array {
        $course = $course ?? $this->course;
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $role);
        $this->setUser($user);
        $groupid = create_group::execute($activity->cmid, 'Team ' . $user->id, '', '🛡', 0, '')['groupid'];
        return [$user, $groupid];
    }

    /**
     * Test that clearing the completion data also clears who qualified, so the rule cannot bring it back.
     *
     * Without this, the next completion evaluation reads the surviving qualification and reports the
     * student as done again although the teacher had just reset the completion.
     */
    public function test_resetting_completion_clears_the_qualifications_of_the_course_only(): void {
        global $DB;

        $activity = $this->create_activity();
        $othercourse = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $other = $this->create_activity($othercourse);
        [$student] = $this->student_with_group($activity);
        [$elsewhere] = $this->student_with_group($other, $othercourse);

        $status = $this->run_reset($this->course, ['reset_completion' => 1]);

        $this->assertFalse($DB->record_exists('playergroup_qualified', ['playergroupid' => $activity->id]));
        $this->assertTrue(
            $DB->record_exists('playergroup_qualified', ['playergroupid' => $other->id, 'userid' => $elsewhere->id]),
            'Another course is not touched.'
        );
        $cminfo = get_fast_modinfo($this->course->id, $student->id)->get_cm($activity->cmid);
        $this->assertSame(
            COMPLETION_INCOMPLETE,
            (new custom_completion($cminfo, (int) $student->id))->get_state('completionjoingroup')
        );
        $this->assertContains(get_string('resetqualified', 'mod_playergroup'), array_column($status, 'item'));
    }

    /**
     * Test that resetting the grades clears the qualifications, so a later regrade cannot resurrect them.
     */
    public function test_resetting_grades_clears_the_qualifications(): void {
        global $DB;

        $activity = $this->create_activity();
        $this->student_with_group($activity);

        $this->run_reset($this->course, ['reset_gradebook_grades' => 1]);

        $this->assertSame(0, $DB->count_records('playergroup_qualified', ['playergroupid' => $activity->id]));
        $record = $DB->get_record('playergroup', ['id' => $activity->id], '*', MUST_EXIST);
        $grades = $DB->get_records_sql(
            "SELECT gg.id
               FROM {grade_grades} gg
               JOIN {grade_items} gi ON gi.id = gg.itemid
              WHERE gi.itemmodule = 'playergroup' AND gi.iteminstance = :instance AND gg.rawgrade IS NOT NULL",
            ['instance' => $activity->id]
        );
        playergroup_update_grades($record);
        $this->assertCount(count($grades), $DB->get_records_sql(
            "SELECT gg.id
               FROM {grade_grades} gg
               JOIN {grade_items} gi ON gi.id = gg.itemid
              WHERE gi.itemmodule = 'playergroup' AND gi.iteminstance = :instance AND gg.rawgrade IS NOT NULL",
            ['instance' => $activity->id]
        ), 'A regrade sends nothing, since nobody is qualified any more.');
    }

    /**
     * Test that a reset that touches neither completion nor grades leaves the qualifications alone.
     */
    public function test_a_reset_without_those_options_keeps_the_qualifications(): void {
        global $DB;

        $activity = $this->create_activity();
        $this->student_with_group($activity);

        $this->run_reset($this->course, ['reset_comments' => 1]);

        $this->assertSame(1, $DB->count_records('playergroup_qualified', ['playergroupid' => $activity->id]));
    }

    /**
     * Test that removing the groups clears their metadata, through the group_deleted observer, the
     * pending invites, and nothing the students earned.
     */
    public function test_removing_the_groups_clears_metadata_and_invites_but_not_qualifications(): void {
        global $DB;

        $this->redirectMessages();
        $activity = $this->create_activity();
        [$leader, $groupid] = $this->student_with_group($activity);
        $invited = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($invited->id, $this->course->id, 'student');
        $this->setUser($leader);
        send_invite::execute($activity->cmid, $invited->id);
        $this->assertSame(1, $DB->count_records('playergroup_invites', ['playergroupid' => $activity->id]));

        $this->run_reset($this->course, ['reset_groups_remove' => 1]);

        $this->assertFalse($DB->record_exists('groups', ['id' => $groupid]));
        $this->assertFalse($DB->record_exists('playergroup_meta', ['groupid' => $groupid]));
        $this->assertSame(0, $DB->count_records('playergroup_invites', ['playergroupid' => $activity->id]));
        $this->assertSame(1, $DB->count_records('playergroup_qualified', ['playergroupid' => $activity->id]));
    }

    /**
     * Test that students the reset unenrols lose their qualification, while the ones who stay keep it.
     */
    public function test_unenrolled_students_lose_their_qualification(): void {
        global $DB;

        $activity = $this->create_activity();
        [$student] = $this->student_with_group($activity);
        // A teacher cannot create groups, so the qualification of one who stays is recorded directly.
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'teacher');
        $DB->insert_record('playergroup_qualified', (object) [
            'playergroupid' => $activity->id, 'userid' => $teacher->id, 'timequalified' => time(),
        ]);
        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);

        $this->run_reset($this->course, ['unenrol_users' => [$studentrole]]);

        $this->assertFalse($DB->record_exists('playergroup_qualified', ['userid' => $student->id]));
        $this->assertTrue($DB->record_exists('playergroup_qualified', ['userid' => $teacher->id]));
    }

    /**
     * Test that the core no longer reports this activity as not supporting the reset.
     */
    public function test_the_reset_is_reported_as_supported(): void {
        $this->create_activity();

        $status = $this->run_reset($this->course, ['reset_completion' => 1]);

        $errors = array_filter(array_column($status, 'error'));
        $this->assertNotContains(get_string('resetnotimplemented'), $errors);
    }

    /**
     * Test that deleting a group on the native Groups page forgets its metadata and invites.
     */
    public function test_deleting_a_group_forgets_its_metadata(): void {
        global $DB;

        $activity = $this->create_activity();
        [, $groupid] = $this->student_with_group($activity);

        groups_delete_group($groupid);

        $this->assertFalse($DB->record_exists('playergroup_meta', ['groupid' => $groupid]));
        $this->assertSame(1, $DB->count_records('playergroup_qualified', ['playergroupid' => $activity->id]));
    }
}
