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
}
