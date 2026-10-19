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
 * Unit tests for the send_invite external function.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\external;

use advanced_testcase;
use context_course;
use moodle_exception;

/**
 * Tests for \mod_playergroup\external\send_invite.
 *
 * @covers \mod_playergroup\external\send_invite
 */
final class send_invite_test extends advanced_testcase {
    /** @var \stdClass Course record. */
    private \stdClass $course;

    /** @var \stdClass Course module record returned by create_module(). */
    private \stdClass $cm;

    /** @var \stdClass Group leader who sends invites. */
    private \stdClass $leader;

    /** @var \stdClass User who receives invites. */
    private \stdClass $receiver;

    /** @var int ID of the leader's group created in setUp. */
    private int $groupid;

    /**
     * Set up fixtures for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->redirectMessages();

        $this->course = $this->getDataGenerator()->create_course();
        $this->cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $this->course->id,
        ]);

        $this->leader = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->leader->id, $this->course->id, 'student');
        $this->setUser($this->leader);
        $result = create_group::execute($this->cm->cmid, 'Test Group', '', '🛡', 0, '');
        $this->groupid = $result['groupid'];

        $this->receiver = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->receiver->id, $this->course->id, 'student');
    }

    /**
     * Test that a leader can send an invite that lands as pending.
     */
    public function test_execute_sends_pending_invite(): void {
        global $DB;

        $this->setUser($this->leader);
        $result = send_invite::execute($this->cm->cmid, $this->receiver->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $DB->count_records('playergroup_invites', [
            'groupid'    => $this->groupid,
            'receiverid' => $this->receiver->id,
            'status'     => 0,
        ]));
    }

    /**
     * Test that the notification of the invite belongs to the course of the activity, not to the site.
     *
     * Without the course the core logs the notification in the site course, and the invite never shows in the
     * logs of the course where it was sent.
     */
    public function test_execute_sends_the_notification_in_the_course_of_the_activity(): void {
        // The event only exists when the message is really sent: the redirection of setUp() is lifted and the
        // open test transaction, which would hold the message back, is committed.
        $this->preventResetByRollback();
        $this->redirectMessages()->close();
        $events = $this->redirectEvents();

        $this->setUser($this->leader);
        send_invite::execute($this->cm->cmid, $this->receiver->id);

        $sent = array_values(array_filter(
            $events->get_events(),
            fn($event): bool => $event instanceof \core\event\notification_sent
        ));
        $this->assertCount(1, $sent);
        $this->assertEquals($this->course->id, $sent[0]->other['courseid']);
    }

    /**
     * Test that a group which already invited a student can invite again after the student leaves.
     *
     * The first invite is resolved when the student joins (accepted), so once the student leaves
     * the group no pending invite lingers and the duplicate guard no longer blocks a fresh invite.
     */
    public function test_execute_can_reinvite_after_member_leaves(): void {
        global $DB;

        $this->setUser($this->leader);
        send_invite::execute($this->cm->cmid, $this->receiver->id);

        // The receiver joins the leader's group, which marks that invite as accepted.
        $this->setUser($this->receiver);
        join_group::execute($this->cm->cmid, $this->groupid, '');
        $this->assertEquals(0, $DB->count_records('playergroup_invites', [
            'groupid'    => $this->groupid,
            'receiverid' => $this->receiver->id,
            'status'     => 0,
        ]));

        // The receiver leaves, freeing them to be invited again.
        leave_group::execute($this->cm->cmid);

        $this->setUser($this->leader);
        $result = send_invite::execute($this->cm->cmid, $this->receiver->id);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $DB->count_records('playergroup_invites', [
            'groupid'    => $this->groupid,
            'receiverid' => $this->receiver->id,
            'status'     => 0,
        ]));
    }

    /**
     * Test that a sender without moodle/course:viewparticipants cannot send an invite.
     *
     * Mirrors the guard both UIs already apply before offering the invite picker: a professor
     * who hid the participant list from students has decided students should not browse
     * course-mates by name, and this endpoint must honour that too.
     */
    public function test_execute_requires_viewparticipants_capability(): void {
        $coursecontext = context_course::instance($this->course->id);
        $prohibitedrole = $this->getDataGenerator()->create_role();
        assign_capability('moodle/course:viewparticipants', CAP_PROHIBIT, $prohibitedrole, $coursecontext);
        role_assign($prohibitedrole, $this->leader->id, $coursecontext);
        accesslib_clear_all_caches_for_unit_testing();

        $this->setUser($this->leader);
        $this->expectException(\required_capability_exception::class);
        send_invite::execute($this->cm->cmid, $this->receiver->id);
    }

    /**
     * Test that sending an invite is refused once the activity has closed, and nothing is stored.
     *
     * The receiver could not accept it, since accept_invite enforces the same window.
     */
    public function test_execute_refused_after_close_date(): void {
        global $DB;

        $DB->set_field('playergroup', 'timeclose', time() - HOURSECS, ['id' => $this->cm->id]);
        $this->setUser($this->leader);
        $sink = $this->redirectMessages();

        try {
            send_invite::execute($this->cm->cmid, $this->receiver->id);
            $this->fail('Inviting after the activity closed must be refused.');
        } catch (moodle_exception $e) {
            $this->assertSame('activityclosed', $e->errorcode);
        }

        $this->assertSame(0, $DB->count_records('playergroup_invites'));
        $this->assertSame(0, $sink->count());
    }

    /**
     * Test that sending an invite is refused before the activity opens.
     */
    public function test_execute_refused_before_open_date(): void {
        global $DB;

        $DB->set_field('playergroup', 'timeopen', time() + HOURSECS, ['id' => $this->cm->id]);
        $this->setUser($this->leader);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('activitynotopen', 'mod_playergroup'));
        send_invite::execute($this->cm->cmid, $this->receiver->id);
    }

    /**
     * Test that the invitation names the group as typed in the text message and escaped once in the HTML one.
     */
    public function test_execute_message_names_the_group_once_escaped(): void {
        global $DB;

        $DB->set_field('groups', 'name', 'P&D', ['id' => $this->groupid]);
        $this->setUser($this->leader);
        $sink = $this->redirectMessages();

        send_invite::execute($this->cm->cmid, $this->receiver->id);

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('P&D', $messages[0]->fullmessage);
        $this->assertStringNotContainsString('&amp;', $messages[0]->fullmessage);
        $this->assertStringContainsString('P&amp;D', $messages[0]->fullmessagehtml);
        $this->assertStringNotContainsString('P&amp;amp;D', $messages[0]->fullmessagehtml);
    }

    /**
     * Takes a capability away from the student role in the course.
     *
     * @param string $capability Capability to prohibit.
     */
    private function prohibit_for_students(string $capability): void {
        global $DB;

        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability($capability, CAP_PROHIBIT, $studentrole, context_course::instance($this->course->id)->id, true);
        // A new request would build the module info again; here it is cached from before the change.
        rebuild_course_cache($this->course->id, true, true);
    }

    /**
     * Counts the invites stored, to prove that a refusal wrote nothing.
     *
     * @return int Number of invites.
     */
    private function invites(): int {
        global $DB;

        return $DB->count_records('playergroup_invites');
    }

    /**
     * Test that someone who is not enrolled in the course cannot be invited, and nothing is stored.
     */
    public function test_execute_refuses_a_user_not_enrolled_in_the_course(): void {
        $stranger = $this->getDataGenerator()->create_user();
        $this->setUser($this->leader);

        try {
            send_invite::execute($this->cm->cmid, $stranger->id);
            $this->fail('A user outside the course must not be invited.');
        } catch (moodle_exception $e) {
            $this->assertSame('usernotenrolled', $e->errorcode);
        }
        $this->assertSame(0, $this->invites());
    }

    /**
     * Test that a suspended enrolment does not count: the invite could never be accepted.
     */
    public function test_execute_refuses_a_user_with_a_suspended_enrolment(): void {
        $suspended = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($suspended->id, $this->course->id, 'student', 'manual', 0, 0, ENROL_USER_SUSPENDED);
        $this->setUser($this->leader);

        try {
            send_invite::execute($this->cm->cmid, $suspended->id);
            $this->fail('A suspended student must not be invited.');
        } catch (moodle_exception $e) {
            $this->assertSame('usernotenrolled', $e->errorcode);
        }
        $this->assertSame(0, $this->invites());
    }

    /**
     * Test that someone without a group cannot invite: there is no group to invite to.
     */
    public function test_execute_refuses_a_sender_without_a_group(): void {
        $loner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($loner->id, $this->course->id, 'student');
        $this->setUser($loner);

        try {
            send_invite::execute($this->cm->cmid, $this->receiver->id);
            $this->fail('Someone without a group must not send invites.');
        } catch (moodle_exception $e) {
            $this->assertSame('notingroup', $e->errorcode);
        }
        $this->assertSame(0, $this->invites());
    }

    /**
     * Test that someone who already has a group cannot be invited to another one, and that the sender is told so.
     *
     * The sender is always in a group, so the message that speaks of "you" would explain nothing: it has to name
     * the person invited.
     */
    public function test_execute_refuses_a_receiver_who_already_has_a_group(): void {
        $this->setUser($this->receiver);
        create_group::execute($this->cm->cmid, 'Other team', '', '🛡', 0, '');

        $this->setUser($this->leader);
        try {
            send_invite::execute($this->cm->cmid, $this->receiver->id);
            $this->fail('A student already in a group must not be invited.');
        } catch (moodle_exception $e) {
            $this->assertSame('inviteealreadyingroup', $e->errorcode);
            $this->assertSame(get_string('inviteealreadyingroup', 'mod_playergroup'), $e->getMessage());
            $this->assertNotSame(get_string('alreadyingroup', 'mod_playergroup'), $e->getMessage());
        }
        $this->assertSame(0, $this->invites());
    }

    /**
     * Test that a full group cannot invite, since nobody could join it.
     */
    public function test_execute_refuses_when_the_group_is_full(): void {
        global $DB;

        $DB->set_field('playergroup', 'maxmembers', 1, ['id' => $this->cm->id]);
        $this->setUser($this->leader);

        try {
            send_invite::execute($this->cm->cmid, $this->receiver->id);
            $this->fail('A full group must not invite.');
        } catch (moodle_exception $e) {
            $this->assertSame('groupisfull', $e->errorcode);
        }
        $this->assertSame(0, $this->invites());
    }

    /**
     * Test that the same student is not invited twice while the first invite is pending.
     */
    public function test_execute_refuses_a_second_pending_invite_to_the_same_student(): void {
        $this->setUser($this->leader);
        send_invite::execute($this->cm->cmid, $this->receiver->id);

        try {
            send_invite::execute($this->cm->cmid, $this->receiver->id);
            $this->fail('A second pending invite must be refused.');
        } catch (moodle_exception $e) {
            $this->assertSame('invitealreadysent', $e->errorcode);
        }
        $this->assertSame(1, $this->invites());
    }

    /**
     * Test that a group of one activity cannot be used to invite through another activity.
     */
    public function test_execute_does_not_use_the_senders_group_of_another_activity(): void {
        $other = $this->getDataGenerator()->create_module('playergroup', ['course' => $this->course->id]);
        $this->setUser($this->leader);

        try {
            send_invite::execute($other->cmid, $this->receiver->id);
            $this->fail('The leader has no group in the other activity.');
        } catch (moodle_exception $e) {
            $this->assertSame('notingroup', $e->errorcode);
        }
        $this->assertSame(0, $this->invites());
    }

    /**
     * Test that the right to invite is checked on the server, whatever the interface offered.
     */
    public function test_execute_requires_the_manageinvites_capability(): void {
        $this->prohibit_for_students('mod/playergroup:manageinvites');
        $this->setUser($this->leader);

        $this->expectException(\required_capability_exception::class);
        send_invite::execute($this->cm->cmid, $this->receiver->id);
    }

    /**
     * Test that someone outside the course cannot use the service at all.
     */
    public function test_execute_refuses_a_sender_not_enrolled_in_the_course(): void {
        $stranger = $this->getDataGenerator()->create_user();
        $this->setUser($stranger);

        $this->expectException(\require_login_exception::class);
        send_invite::execute($this->cm->cmid, $this->receiver->id);
    }

    /**
     * Test that the answer has the shape the service declares, which is what the app relies on.
     */
    public function test_execute_returns_the_declared_structure(): void {
        $this->setUser($this->leader);

        $result = send_invite::execute($this->cm->cmid, $this->receiver->id);
        $clean = \core_external\external_api::clean_returnvalue(send_invite::execute_returns(), $result);

        $this->assertTrue($clean['success']);
        $this->assertNotSame('', $clean['message']);
    }

    /**
     * Restricts the activity to the members of a new native group, and puts the leader in it.
     *
     * @return \stdClass The group that may open the activity.
     */
    private function restrict_activity_to_a_group(): \stdClass {
        global $DB;

        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $this->leader->id]);
        $json = json_encode(\core_availability\tree::get_root_json([
            \availability_group\condition::get_json($group->id),
        ]));
        $DB->set_field('course_modules', 'availability', $json, ['id' => $this->cm->cmid]);
        rebuild_course_cache($this->course->id, true, true);

        return $group;
    }

    /**
     * Test that someone who cannot open the activity is not invited.
     *
     * The invite would sit pending for good: the person gets the message but cannot accept or decline it.
     */
    public function test_execute_refuses_someone_who_cannot_open_the_activity(): void {
        $this->restrict_activity_to_a_group();
        $before = $this->invites();

        $this->setUser($this->leader);
        try {
            send_invite::execute($this->cm->cmid, $this->receiver->id);
            $this->fail('The invite should have been refused');
        } catch (moodle_exception $e) {
            $this->assertSame('errinviteeunavailable', $e->errorcode);
        }
        $this->assertSame($before, $this->invites());
    }

    /**
     * Test that someone who may open the activity still gets the invite.
     */
    public function test_execute_invites_someone_who_can_open_the_restricted_activity(): void {
        $group = $this->restrict_activity_to_a_group();
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $this->receiver->id]);
        rebuild_course_cache($this->course->id, true, true);

        $this->setUser($this->leader);
        $this->assertTrue(send_invite::execute($this->cm->cmid, $this->receiver->id)['success']);
    }

    /**
     * Test that someone without the right to view the activity is not invited.
     */
    public function test_execute_refuses_someone_without_the_view_capability(): void {
        global $DB;

        $role = $this->getDataGenerator()->create_role();
        assign_capability('mod/playergroup:view', CAP_PROHIBIT, $role, \context_course::instance($this->course->id)->id);
        role_assign($role, $this->receiver->id, \context_course::instance($this->course->id));
        rebuild_course_cache($this->course->id, true, true);

        $this->setUser($this->leader);
        $this->expectExceptionMessage(get_string('errinviteeunavailable', 'mod_playergroup'));
        send_invite::execute($this->cm->cmid, $this->receiver->id);
    }
}
