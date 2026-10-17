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
 * Unit tests for the get_activity_data external function.
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
 * Tests for \mod_playergroup\external\get_activity_data.
 *
 * @covers \mod_playergroup\external\get_activity_data
 */
final class get_activity_data_test extends advanced_testcase {
    /**
     * Test that each returned group carries its member list, with the creator flagged as
     * leader, and that the response validates against execute_returns() — this is what the
     * mobile app actually consumes, so a PARAM type mismatch here would break it silently.
     */
    public function test_execute_includes_group_members(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);

        // Explicit, distinct names: the generator's own random pool is small enough that two
        // auto-named users could otherwise collide on fullname(), which the assertions below
        // key on.
        $creator = $this->getDataGenerator()->create_user(['firstname' => 'Creator', 'lastname' => 'One']);
        $this->getDataGenerator()->enrol_user($creator->id, $course->id, 'student');
        $this->setUser($creator);
        $created = create_group::execute($cm->cmid, 'Test Group', '', '🛡', 0, '');

        $joiner = $this->getDataGenerator()->create_user(['firstname' => 'Joiner', 'lastname' => 'Two']);
        $this->getDataGenerator()->enrol_user($joiner->id, $course->id, 'student');
        $this->setUser($joiner);
        join_group::execute($cm->cmid, $created['groupid'], '');

        $result = get_activity_data::execute($cm->cmid);
        $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), $result);

        $this->assertCount(1, $result['groups']);
        $members = $result['groups'][0]['members'];
        $this->assertCount(2, $members);

        $isleaderbyname = [];
        foreach ($members as $member) {
            $isleaderbyname[$member['fullname']] = $member['isleader'];
        }
        $this->assertTrue($isleaderbyname[fullname($creator)]);
        $this->assertFalse($isleaderbyname[fullname($joiner)]);
        $this->assertStringContainsString('Test Group', $result['groups'][0]['membersheading']);
    }

    /**
     * Test that the member list is ordered leader-first, then alphabetically - not by join
     * order, which would otherwise put the earliest joiner first regardless of who actually
     * leads the group today.
     */
    public function test_execute_orders_members_leader_first_then_alphabetically(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);

        $creator = $this->getDataGenerator()->create_user(['firstname' => 'Creator', 'lastname' => 'One']);
        $this->getDataGenerator()->enrol_user($creator->id, $course->id, 'student');
        $this->setUser($creator);
        $created = create_group::execute($cm->cmid, 'Ordered Group', '', '🛡', 0, '');

        // Joins in this order (Zulema before Alice), so join order and alphabetical order
        // disagree - only the fix sorts the non-leaders alphabetically instead of by join time.
        $zulema = $this->getDataGenerator()->create_user(['firstname' => 'Zulema', 'lastname' => 'Nunes']);
        $this->getDataGenerator()->enrol_user($zulema->id, $course->id, 'student');
        $this->setUser($zulema);
        join_group::execute($cm->cmid, $created['groupid'], '');

        $alice = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'Vale']);
        $this->getDataGenerator()->enrol_user($alice->id, $course->id, 'student');
        $this->setUser($alice);
        join_group::execute($cm->cmid, $created['groupid'], '');

        $result = get_activity_data::execute($cm->cmid);
        $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), $result);

        $names = array_column($result['groups'][0]['members'], 'fullname');
        $this->assertSame([fullname($creator), fullname($alice), fullname($zulema)], $names);
    }

    /**
     * Builds an activity with one group of two members and a student who has no group.
     *
     * @return array [course, cmid, creator, joiner, viewer]
     */
    private function build_group_with_outsider(): array {
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);

        $creator = $this->getDataGenerator()->create_user(['firstname' => 'Creator', 'lastname' => 'One']);
        $joiner = $this->getDataGenerator()->create_user(['firstname' => 'Joiner', 'lastname' => 'Two']);
        $viewer = $this->getDataGenerator()->create_user(['firstname' => 'Viewer', 'lastname' => 'Three']);
        foreach ([$creator, $joiner, $viewer] as $user) {
            $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        }

        $this->setUser($creator);
        $created = create_group::execute($cm->cmid, 'Test Group', '', '🛡', 0, '');
        $this->setUser($joiner);
        join_group::execute($cm->cmid, $created['groupid'], '');

        return [$course, $cm->cmid, $creator, $joiner, $viewer];
    }

    /**
     * Removes the right to see the course participant list from the student role in the course.
     *
     * @param \stdClass $course Course where the override applies.
     */
    private function hide_participants_from_students(\stdClass $course): void {
        global $DB;

        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability(
            'moodle/course:viewparticipants',
            CAP_PREVENT,
            $studentrole,
            \context_course::instance($course->id)->id,
            true
        );
    }

    /**
     * Test that names of other groups' members and leaders are withheld from a user who cannot
     * see the course participants, the same decision the invite picker already applies.
     *
     * The member count stays visible; only the names are withheld, and they must not appear
     * anywhere in the response.
     */
    public function test_execute_hides_names_without_viewparticipants(): void {
        $this->resetAfterTest();

        [$course, $cmid, $creator, $joiner, $viewer] = $this->build_group_with_outsider();
        $this->hide_participants_from_students($course);

        $this->setUser($viewer);
        $result = get_activity_data::execute($cmid);
        $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), $result);

        $group = $result['groups'][0];
        $this->assertFalse($group['canviewmembers']);
        $this->assertSame([], $group['members']);
        $this->assertSame('', $group['leaderbadge']);
        $this->assertSame(2, $group['membercount']);

        $encoded = json_encode($result);
        $this->assertStringNotContainsString($creator->lastname, $encoded);
        $this->assertStringNotContainsString($joiner->firstname, $encoded);
    }

    /**
     * Test that a member always sees the names in their own group, even without the capability.
     */
    public function test_execute_shows_names_in_own_group_without_viewparticipants(): void {
        $this->resetAfterTest();

        [$course, $cmid, $creator, $joiner] = $this->build_group_with_outsider();
        $this->hide_participants_from_students($course);

        $this->setUser($joiner);
        $result = get_activity_data::execute($cmid);
        $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), $result);

        $group = $result['groups'][0];
        $this->assertTrue($group['canviewmembers']);
        $this->assertEqualsCanonicalizing(
            [fullname($creator), fullname($joiner)],
            array_column($group['members'], 'fullname')
        );
        $this->assertNotSame('', $group['leaderbadge']);
    }

    /**
     * Test that a user with the capability keeps seeing every group's members.
     */
    public function test_execute_shows_names_with_viewparticipants(): void {
        $this->resetAfterTest();

        [, $cmid, , , $viewer] = $this->build_group_with_outsider();

        $this->setUser($viewer);
        $result = get_activity_data::execute($cmid);
        $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), $result);

        $this->assertTrue($result['groups'][0]['canviewmembers']);
        $this->assertCount(2, $result['groups'][0]['members']);
    }

    /**
     * Test that the app is told why the student's own group does not count yet, and only for that group.
     */
    public function test_execute_reports_missing_members_for_the_own_group_only(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'minmembers' => 3,
        ]);
        [$mine, $theirs] = [$this->getDataGenerator()->create_user(), $this->getDataGenerator()->create_user()];
        foreach ([$mine, $theirs] as $user) {
            $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        }
        $this->setUser($theirs);
        $theirgroup = create_group::execute($cm->cmid, 'Theirs', '', '🛡', 0, '')['groupid'];
        $this->setUser($mine);
        $mygroup = create_group::execute($cm->cmid, 'Mine', '', '🛡', 0, '')['groupid'];

        $result = get_activity_data::execute($cm->cmid);
        $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), $result);

        $messages = array_column($result['groups'], 'missingmessage', 'groupid');
        $this->assertSame(get_string('groupneedsmembers', 'mod_playergroup', 2), $messages[$mygroup]);
        $this->assertSame('', $messages[$theirgroup]);
    }

    /**
     * Test that names with an ampersand reach the app as typed, with no HTML escaping of their own.
     *
     * The app prints these fields as text, so an "&amp;" in them would be shown literally.
     */
    public function test_execute_returns_names_as_plain_text(): void {
        $this->resetAfterTest();

        foreach ([1, 0] as $striptags) {
            set_config('formatstringstriptags', $striptags);
            $course = $this->getDataGenerator()->create_course();
            $cm = $this->getDataGenerator()->create_module('playergroup', [
                'course' => $course->id,
                'name' => 'Q&A activity',
            ]);
            $student = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
            $this->setUser($student);
            create_group::execute($cm->cmid, 'Ana & Pedro', '', '🛡', 0, '');

            $result = get_activity_data::execute($cm->cmid);
            $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), $result);

            $this->assertSame('Q&A activity', $result['name'], "striptags=$striptags");
            $this->assertSame('Ana & Pedro', $result['groups'][0]['name'], "striptags=$striptags");
            $this->assertStringContainsString('Ana & Pedro', $result['groups'][0]['membersheading']);
            $this->assertStringNotContainsString('&amp;', json_encode($result));
        }
    }

    /**
     * Takes a capability of the activity away from the student role in the course.
     *
     * @param \stdClass $course Course where the override applies.
     * @param string $capability Capability to prohibit.
     */
    private function prohibit_for_students(\stdClass $course, string $capability): void {
        global $DB;

        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability($capability, CAP_PROHIBIT, $studentrole, \context_course::instance($course->id)->id, true);
    }

    /**
     * Test that the app is told whether the user may create a group, which the server requires.
     *
     * Offering "Create group" to someone the server will refuse loses what they typed and ends in
     * a permission error.
     */
    public function test_execute_tells_whether_the_user_can_create_a_group(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $result = get_activity_data::execute($cm->cmid);
        $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), $result);
        $this->assertArrayHasKey('cancreate', $result);
        $this->assertTrue($result['cancreate']);

        $this->prohibit_for_students($course, 'mod/playergroup:creategroup');

        $result = get_activity_data::execute($cm->cmid);
        $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), $result);
        $this->assertArrayHasKey('cancreate', $result);
        $this->assertFalse($result['cancreate']);
    }

    /**
     * Test that a non-editing teacher, who can view the activity but may not create groups, is not offered to.
     */
    public function test_execute_does_not_offer_creating_a_group_to_a_non_editing_teacher(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $this->setUser($teacher);

        $result = get_activity_data::execute($cm->cmid);
        $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), $result);

        $this->assertArrayHasKey('cancreate', $result);
        $this->assertFalse($result['cancreate']);
    }

    /**
     * Test that a leader who later loses the right to create groups is not offered to edit the group.
     *
     * The editing service asks for the same capability, so the button would only lead to an error.
     */
    public function test_execute_does_not_offer_editing_to_a_leader_without_creategroup(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $leader = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($leader);
        create_group::execute($cm->cmid, 'Team', '', '🛡', 0, '');

        $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), get_activity_data::execute($cm->cmid));
        $this->assertTrue($result['groups'][0]['canedit'], 'Before the change the leader can edit.');

        $this->prohibit_for_students($course, 'mod/playergroup:creategroup');

        $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), get_activity_data::execute($cm->cmid));
        $this->assertFalse($result['groups'][0]['canedit']);
    }

    /**
     * Test that inviting is not offered, and the people picker is not filled, without manageinvites.
     */
    public function test_execute_does_not_offer_invites_without_manageinvites(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $leader = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($leader);
        create_group::execute($cm->cmid, 'Team', '', '🛡', 0, '');

        $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), get_activity_data::execute($cm->cmid));
        $this->assertTrue($result['groups'][0]['caninvite'], 'Before the change the leader can invite.');
        $this->assertNotEmpty($result['inviteableusers']);

        $this->prohibit_for_students($course, 'mod/playergroup:manageinvites');

        $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), get_activity_data::execute($cm->cmid));
        $this->assertFalse($result['groups'][0]['caninvite']);
        $this->assertEmpty($result['inviteableusers']);
    }

    /**
     * Returns the cleaned answer of the service, the way the app receives it.
     *
     * @param int $cmid Course module ID.
     * @return array The answer.
     */
    private function answer(int $cmid): array {
        return external_api::clean_returnvalue(get_activity_data::execute_returns(), get_activity_data::execute($cmid));
    }

    /**
     * Test the message the app shows while the activity is not open yet, and that the actions follow.
     */
    public function test_execute_tells_when_the_activity_is_not_open_yet(): void {
        $this->resetAfterTest();

        $opens = time() + DAYSECS;
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id, 'timeopen' => $opens]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $result = $this->answer($cm->cmid);

        $this->assertFalse($result['activityopen']);
        $this->assertSame(get_string('activityopensfrom', 'mod_playergroup', userdate($opens)), $result['availabilitymessage']);
        $this->assertSame($opens, $result['timeopen']);
    }

    /**
     * Test the message once the activity has closed.
     */
    public function test_execute_tells_when_the_activity_has_closed(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'timeclose' => time() - HOURSECS,
        ]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $result = $this->answer($cm->cmid);

        $this->assertFalse($result['activityopen']);
        $this->assertSame(get_string('activityclosedmsg', 'mod_playergroup'), $result['availabilitymessage']);
    }

    /**
     * Test that an open activity has no availability message.
     */
    public function test_execute_has_no_message_while_the_activity_is_open(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $result = $this->answer($cm->cmid);

        $this->assertTrue($result['activityopen']);
        $this->assertSame('', $result['availabilitymessage']);
    }

    /**
     * Test the invites a student without a group sees in the app: who sent them, to which group.
     */
    public function test_execute_lists_the_invites_received_by_a_student_without_a_group(): void {
        global $DB;
        $this->resetAfterTest();
        $this->redirectMessages();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $other = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $leader = $this->getDataGenerator()->create_user(['firstname' => 'Leader', 'lastname' => 'Silva']);
        $invited = $this->getDataGenerator()->create_user(['firstname' => 'Invited', 'lastname' => 'Souza']);
        foreach ([$leader, $invited] as $user) {
            $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        }
        $this->setUser($leader);
        create_group::execute($cm->cmid, 'P&D team', '', '⚔', 0, '');
        send_invite::execute($cm->cmid, $invited->id);
        $this->setUser($invited);

        $result = $this->answer($cm->cmid);

        $this->assertCount(1, $result['receivedinvites']);
        $invite = $result['receivedinvites'][0];
        $this->assertSame('P&D team', $invite['groupname']);
        $this->assertSame('⚔', $invite['badge']);
        $this->assertSame(get_string('invitedby', 'mod_playergroup', fullname($leader)), $invite['sentby']);
        $this->assertGreaterThan(0, $invite['inviteid']);

        $this->assertSame([], $this->answer($other->cmid)['receivedinvites'], 'An invite belongs to one activity.');

        // Once the invite is answered it is no longer listed.
        $DB->set_field('playergroup_invites', 'status', 2, ['id' => $invite['inviteid']]);
        $this->assertSame([], $this->answer($cm->cmid)['receivedinvites']);
    }

    /**
     * Test that a student who already has a group is not shown invites.
     */
    public function test_execute_does_not_list_invites_to_a_student_who_has_a_group(): void {
        $this->resetAfterTest();
        $this->redirectMessages();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $leader = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $invited = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($leader);
        create_group::execute($cm->cmid, 'Team', '', '🛡', 0, '');
        send_invite::execute($cm->cmid, $invited->id);
        $this->setUser($invited);
        create_group::execute($cm->cmid, 'Own', '', '🛡', 0, '');

        $this->assertSame([], $this->answer($cm->cmid)['receivedinvites']);
    }

    /**
     * Test that the editing field gets the text, whatever format the group description was saved in.
     *
     * Groups saved by earlier versions hold HTML with entities; a new group holds what was typed.
     */
    public function test_the_raw_description_is_plain_text_for_editing(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $creator = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($creator);
        $typed = create_group::execute($cm->cmid, 'Typed', "A&B > C\nnext", '🛡', 0, '');
        $other = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($other);
        $second = create_group::execute($cm->cmid, 'Second', '', '🛡', 0, '');
        $DB->update_record('groups', (object) [
            'id' => $second['groupid'],
            'description' => '<p>R&amp;D &gt; all</p>',
            'descriptionformat' => FORMAT_HTML,
        ]);

        $this->setUser($creator);
        $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), get_activity_data::execute($cm->cmid));

        $byname = array_column($result['groups'], 'rawdescription', 'name');
        $this->assertSame("A&B > C\nnext", $byname['Typed']);
        $this->assertSame('R&D > all', $byname['Second']);
    }

    /**
     * Test that the people offered for an invite are only those who can open the activity.
     */
    public function test_the_invite_picker_leaves_out_who_cannot_open_the_activity(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $leader = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $inside = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $outside = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        foreach ([$leader, $inside] as $member) {
            $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $member->id]);
        }
        $json = json_encode(\core_availability\tree::get_root_json([\availability_group\condition::get_json($group->id)]));
        $DB->set_field('course_modules', 'availability', $json, ['id' => $cm->cmid]);
        rebuild_course_cache($course->id, true, true);

        $this->setUser($leader);
        create_group::execute($cm->cmid, 'Mine', '', '🛡', 0, '');
        $offered = array_column($this->answer($cm->cmid)['inviteableusers'], 'userid');

        $this->assertContains((int) $inside->id, $offered);
        $this->assertNotContains((int) $outside->id, $offered);
    }
}
