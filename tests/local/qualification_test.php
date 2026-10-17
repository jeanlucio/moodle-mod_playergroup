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
 * Unit tests for the minimum-group-size qualification.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

use advanced_testcase;
use mod_playergroup\completion\custom_completion;
use mod_playergroup\external\accept_invite;
use mod_playergroup\external\create_group;
use mod_playergroup\external\join_group;
use mod_playergroup\external\leave_group;
use mod_playergroup\external\send_invite;

/**
 * Tests for \mod_playergroup\local\qualification.
 *
 * The groups are built through the real web services, as students do, so the tests exercise the
 * places where the qualification is recorded and the grade and completion are given.
 *
 * @covers \mod_playergroup\local\qualification
 * @covers ::playergroup_update_grades
 * @covers ::playergroup_update_instance
 * @covers ::playergroup_delete_instance
 */
final class qualification_test extends advanced_testcase {
    /** @var \stdClass Course with completion enabled. */
    private \stdClass $course;

    /**
     * Enables completion and creates the course.
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        $CFG->enablecompletion = true;
        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
    }

    /**
     * Creates an activity with automatic completion and a grade of 10.
     *
     * @param int $minmembers Minimum group size.
     * @return \stdClass Generator result (id is the instance, cmid the course module).
     */
    private function create_activity(int $minmembers): \stdClass {
        return $this->getDataGenerator()->create_module('playergroup', [
            'course' => $this->course->id,
            'minmembers' => $minmembers,
            'maxmembers' => 5,
            'grade' => 10,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionjoingroup' => 1,
        ]);
    }

    /**
     * Creates an enrolled student.
     *
     * @return \stdClass The user.
     */
    private function create_student(): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, 'student');
        return $user;
    }

    /**
     * Makes the student create a group in the activity.
     *
     * @param \stdClass $activity The activity.
     * @param \stdClass $user The founder.
     * @return int Native group ID.
     */
    private function found_group(\stdClass $activity, \stdClass $user): int {
        $this->setUser($user);
        return create_group::execute($activity->cmid, 'Team', '', '⚔', 0, '')['groupid'];
    }

    /**
     * Makes the student join an open group.
     *
     * @param \stdClass $activity The activity.
     * @param \stdClass $user The student.
     * @param int $groupid The group to join.
     */
    private function join(\stdClass $activity, \stdClass $user, int $groupid): void {
        $this->setUser($user);
        join_group::execute($activity->cmid, $groupid, '');
    }

    /**
     * Returns the completion state of the rule for a student.
     *
     * @param \stdClass $activity The activity.
     * @param \stdClass $user The student.
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE.
     */
    private function completion_state(\stdClass $activity, \stdClass $user): int {
        $cminfo = get_fast_modinfo($this->course->id, $user->id)->get_cm($activity->cmid);
        return (new custom_completion($cminfo, (int) $user->id))->get_state('completionjoingroup');
    }

    /**
     * Returns the grade stored for a student, or null when none.
     *
     * @param \stdClass $activity The activity.
     * @param \stdClass $user The student.
     * @return float|null The raw grade.
     */
    private function rawgrade(\stdClass $activity, \stdClass $user): ?float {
        global $DB;

        $sql = "SELECT gg.rawgrade
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gi.itemmodule = 'playergroup' AND gi.iteminstance = :instance AND gg.userid = :userid";
        $value = $DB->get_field_sql($sql, ['instance' => $activity->id, 'userid' => $user->id]);
        return $value === false || $value === null ? null : (float) $value;
    }

    /**
     * Test that nobody qualifies until the group reaches the minimum, and then all members do at once.
     *
     * The first two members must not have to act again: when the third one joins, nothing else
     * recalculates automatic completion, so it has to be updated for each of them right then.
     */
    public function test_reaching_the_minimum_qualifies_every_member(): void {
        $activity = $this->create_activity(3);
        [$first, $second, $third] = [$this->create_student(), $this->create_student(), $this->create_student()];

        $groupid = $this->found_group($activity, $first);
        $this->assertFalse(qualification::is_qualified($activity->id, $first->id));
        $this->assertSame(COMPLETION_INCOMPLETE, $this->completion_state($activity, $first));
        $this->assertNull($this->rawgrade($activity, $first));

        $this->join($activity, $second, $groupid);
        $this->assertFalse(qualification::is_qualified($activity->id, $second->id));
        $this->assertNull($this->rawgrade($activity, $second));

        $this->join($activity, $third, $groupid);
        foreach ([$first, $second, $third] as $student) {
            $this->assertTrue(qualification::is_qualified($activity->id, $student->id));
            $this->assertSame(COMPLETION_COMPLETE, $this->completion_state($activity, $student));
            $this->assertEquals(10.0, $this->rawgrade($activity, $student));
        }

        // The completion is also what the activity page shows, not only what the rule evaluates.
        $completion = new \completion_info($this->course);
        $cminfo = get_fast_modinfo($this->course->id, $first->id)->get_cm($activity->cmid);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_data($cminfo, false, $first->id)->completionstate);
    }

    /**
     * Test that the grade and completion stay when a member leaves or the group shrinks below the minimum.
     */
    public function test_qualification_is_kept_after_leaving(): void {
        $activity = $this->create_activity(2);
        [$first, $second] = [$this->create_student(), $this->create_student()];
        $groupid = $this->found_group($activity, $first);
        $this->join($activity, $second, $groupid);

        $this->setUser($second);
        leave_group::execute($activity->cmid);

        $this->assertTrue(qualification::is_qualified($activity->id, $second->id));
        $this->assertSame(COMPLETION_COMPLETE, $this->completion_state($activity, $second));
        $this->assertEquals(10.0, $this->rawgrade($activity, $second));
        $this->assertSame(COMPLETION_COMPLETE, $this->completion_state($activity, $first));
    }

    /**
     * Test that someone joining a group that has shrunk below the minimum counts again once it is reached.
     */
    public function test_a_newcomer_qualifies_when_the_group_reaches_the_minimum_again(): void {
        $activity = $this->create_activity(3);
        [$a, $b, $c, $d] = [
            $this->create_student(), $this->create_student(), $this->create_student(), $this->create_student(),
        ];
        $groupid = $this->found_group($activity, $a);
        $this->join($activity, $b, $groupid);
        $this->join($activity, $c, $groupid);

        $this->setUser($c);
        leave_group::execute($activity->cmid);
        // The group is down to two members; the newcomer brings it back to three.
        $this->join($activity, $d, $groupid);

        $this->assertTrue(qualification::is_qualified($activity->id, $d->id));
        $this->assertEquals(10.0, $this->rawgrade($activity, $d));
    }

    /**
     * Test that a minimum of one qualifies the founder at once, as the activity always did.
     */
    public function test_minimum_of_one_qualifies_the_founder_immediately(): void {
        $activity = $this->create_activity(1);
        $founder = $this->create_student();
        $this->found_group($activity, $founder);

        $this->assertTrue(qualification::is_qualified($activity->id, $founder->id));
        $this->assertSame(COMPLETION_COMPLETE, $this->completion_state($activity, $founder));
        $this->assertEquals(10.0, $this->rawgrade($activity, $founder));
    }

    /**
     * Test that a legacy minimum of 0, which the form no longer accepts, is treated as 1.
     */
    public function test_a_legacy_minimum_below_one_counts_as_one(): void {
        $this->assertSame(1, qualification::required_members((object) ['minmembers' => 0]));
        $this->assertSame(1, qualification::required_members((object) ['minmembers' => -4]));
        $this->assertSame(4, qualification::required_members((object) ['minmembers' => 4]));
    }

    /**
     * Test that accepting an invite counts the group's size the same way joining does.
     */
    public function test_accepting_an_invite_can_complete_the_minimum(): void {
        global $DB;
        $this->redirectMessages();

        $activity = $this->create_activity(2);
        [$founder, $invited] = [$this->create_student(), $this->create_student()];
        $this->found_group($activity, $founder);
        $this->setUser($founder);
        send_invite::execute($activity->cmid, $invited->id);
        $inviteid = (int) $DB->get_field('playergroup_invites', 'id', ['receiverid' => $invited->id], MUST_EXIST);

        $this->setUser($invited);
        accept_invite::execute($inviteid);

        foreach ([$founder, $invited] as $student) {
            $this->assertTrue(qualification::is_qualified($activity->id, $student->id));
            $this->assertEquals(10.0, $this->rawgrade($activity, $student));
        }
    }

    /**
     * Test that the grade reports the moment the student qualified as the submission date.
     */
    public function test_datesubmitted_is_the_moment_of_qualification(): void {
        global $DB;

        $activity = $this->create_activity(2);
        [$first, $second] = [$this->create_student(), $this->create_student()];
        $groupid = $this->found_group($activity, $first);
        $this->join($activity, $second, $groupid);

        $qualified = (int) $DB->get_field(
            'playergroup_qualified',
            'timequalified',
            ['playergroupid' => $activity->id, 'userid' => $first->id],
            MUST_EXIST
        );
        $gradeitem = \grade_item::fetch(['itemmodule' => 'playergroup', 'iteminstance' => $activity->id, 'itemnumber' => 0]);
        $this->assertEquals($qualified, $gradeitem->get_grade($first->id, false)->get_datesubmitted());
    }

    /**
     * Test that lowering the minimum makes existing groups count, for each member right away.
     */
    public function test_lowering_the_minimum_qualifies_existing_groups(): void {
        global $DB;

        $activity = $this->create_activity(3);
        [$first, $second] = [$this->create_student(), $this->create_student()];
        $groupid = $this->found_group($activity, $first);
        $this->join($activity, $second, $groupid);
        $this->assertFalse(qualification::is_qualified($activity->id, $first->id));

        $record = $DB->get_record('playergroup', ['id' => $activity->id], '*', MUST_EXIST);
        $record->instance = $record->id;
        $record->coursemodule = $activity->cmid;
        $record->minmembers = 2;
        playergroup_update_instance($record);

        foreach ([$first, $second] as $student) {
            $this->assertTrue(qualification::is_qualified($activity->id, $student->id));
            $this->assertSame(COMPLETION_COMPLETE, $this->completion_state($activity, $student));
            $this->assertEquals(10.0, $this->rawgrade($activity, $student));
        }
    }

    /**
     * Test that raising the minimum takes nothing away from who already qualified.
     */
    public function test_raising_the_minimum_keeps_who_qualified(): void {
        global $DB;

        $activity = $this->create_activity(2);
        [$first, $second] = [$this->create_student(), $this->create_student()];
        $groupid = $this->found_group($activity, $first);
        $this->join($activity, $second, $groupid);

        $record = $DB->get_record('playergroup', ['id' => $activity->id], '*', MUST_EXIST);
        $record->instance = $record->id;
        $record->coursemodule = $activity->cmid;
        $record->minmembers = 5;
        playergroup_update_instance($record);

        $this->assertTrue(qualification::is_qualified($activity->id, $first->id));
        $this->assertSame(COMPLETION_COMPLETE, $this->completion_state($activity, $first));
        $this->assertEquals(10.0, $this->rawgrade($activity, $first));
    }

    /**
     * Test that who never reached a group of the minimum size is not graded by a bulk recalculation.
     */
    public function test_bulk_recalculation_grades_only_who_qualified(): void {
        $activity = $this->create_activity(2);
        [$alone, $first, $second] = [$this->create_student(), $this->create_student(), $this->create_student()];
        $this->found_group($activity, $alone);
        $groupid = $this->found_group($activity, $first);
        $this->join($activity, $second, $groupid);

        playergroup_update_grades($activity);

        $this->assertNull($this->rawgrade($activity, $alone));
        $this->assertEquals(10.0, $this->rawgrade($activity, $first));
    }

    /**
     * Test the number of members still missing, shown to the student on their own group.
     */
    public function test_missing_members(): void {
        $playergroup = (object) ['minmembers' => 3];

        $this->assertSame(2, qualification::missing_members($playergroup, 1, false));
        $this->assertSame(0, qualification::missing_members($playergroup, 3, false));
        $this->assertSame(0, qualification::missing_members($playergroup, 5, false));
        $this->assertSame(0, qualification::missing_members($playergroup, 1, true));
    }

    /**
     * Test that deleting the activity removes its qualification rows and no others.
     */
    public function test_deleting_the_activity_removes_its_rows_only(): void {
        global $DB;

        $activity = $this->create_activity(1);
        $other = $this->create_activity(1);
        $this->found_group($activity, $this->create_student());
        $this->found_group($other, $this->create_student());

        playergroup_delete_instance((int) $activity->id);

        $this->assertSame(0, $DB->count_records('playergroup_qualified', ['playergroupid' => $activity->id]));
        $this->assertSame(1, $DB->count_records('playergroup_qualified', ['playergroupid' => $other->id]));
    }

    /**
     * Test that turning the grade on after students qualified grades them, with the date they qualified.
     *
     * Creating the grade item sends no grades, so without an explicit resend only students who
     * qualify later would ever be graded.
     */
    public function test_turning_the_grade_on_grades_who_already_qualified(): void {
        global $DB;

        $activity = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $this->course->id,
            'minmembers' => 2,
            'grade' => 0,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionjoingroup' => 1,
        ]);
        [$first, $second, $late] = [$this->create_student(), $this->create_student(), $this->create_student()];
        $groupid = $this->found_group($activity, $first);
        $this->join($activity, $second, $groupid);
        $this->assertNull($this->rawgrade($activity, $first));

        $record = $DB->get_record('playergroup', ['id' => $activity->id], '*', MUST_EXIST);
        $record->instance = $record->id;
        $record->coursemodule = $activity->cmid;
        $record->grade = 10;
        playergroup_update_instance($record);

        $this->assertEquals(10.0, $this->rawgrade($activity, $first));
        $this->assertEquals(10.0, $this->rawgrade($activity, $second));
        $this->assertNull($this->rawgrade($activity, $late), 'Someone who never qualified is still not graded.');

        $qualified = (int) $DB->get_field(
            'playergroup_qualified',
            'timequalified',
            ['playergroupid' => $activity->id, 'userid' => $first->id],
            MUST_EXIST
        );
        $gradeitem = \grade_item::fetch(['itemmodule' => 'playergroup', 'iteminstance' => $activity->id, 'itemnumber' => 0]);
        $this->assertEquals($qualified, $gradeitem->get_grade($first->id, false)->get_datesubmitted());
    }

    /**
     * Test that changing the maximum grade updates the grades already given.
     */
    public function test_changing_the_grade_value_updates_the_grades_already_given(): void {
        global $DB;

        $activity = $this->create_activity(2);
        [$first, $second] = [$this->create_student(), $this->create_student()];
        $groupid = $this->found_group($activity, $first);
        $this->join($activity, $second, $groupid);
        $this->assertEquals(10.0, $this->rawgrade($activity, $first));

        // One of them has left by then: the grade is theirs to keep, and it follows the new value too.
        $this->setUser($second);
        leave_group::execute($activity->cmid);

        $record = $DB->get_record('playergroup', ['id' => $activity->id], '*', MUST_EXIST);
        $record->instance = $record->id;
        $record->coursemodule = $activity->cmid;
        $record->grade = 20;
        playergroup_update_instance($record);

        $this->assertEquals(20.0, $this->rawgrade($activity, $first));
        $this->assertEquals(20.0, $this->rawgrade($activity, $second));
    }

    /**
     * Test that saving the settings without touching the grade does not resend it.
     */
    public function test_an_unchanged_grade_is_not_resent(): void {
        global $DB;

        $activity = $this->create_activity(1);
        $student = $this->create_student();
        $this->found_group($activity, $student);

        // A teacher's manual override must survive a settings save that does not touch the grade.
        $gradeitem = \grade_item::fetch(['itemmodule' => 'playergroup', 'iteminstance' => $activity->id, 'itemnumber' => 0]);
        $gradeitem->update_final_grade($student->id, 3.0, 'test', 'manual override');

        $record = $DB->get_record('playergroup', ['id' => $activity->id], '*', MUST_EXIST);
        $record->instance = $record->id;
        $record->coursemodule = $activity->cmid;
        $record->name = 'Renamed';
        playergroup_update_instance($record);

        $this->assertEquals(3.0, (float) $gradeitem->get_grade($student->id, false)->finalgrade);
    }
}
