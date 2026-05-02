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
 * Unit tests for the leave_group external function.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\external;

use advanced_testcase;
use moodle_exception;

/**
 * Tests for \mod_playergroup\external\leave_group.
 *
 * @covers \mod_playergroup\external\leave_group
 */
final class leave_group_test extends advanced_testcase {
    /** @var \stdClass Course record. */
    private \stdClass $course;

    /** @var \stdClass Course module record returned by create_module(). */
    private \stdClass $cm;

    /** @var \stdClass User who created the group (leader). */
    private \stdClass $leader;

    /** @var int ID of the group created in setUp. */
    private int $groupid;

    /**
     * Set up fixtures for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->cm = $this->getDataGenerator()->create_module('playergroup', [
            'course'            => $this->course->id,
            'canleave'          => 1,
            'deleteemptygroups' => 1,
        ]);

        $this->leader = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->leader->id, $this->course->id, 'student');
        $this->setUser($this->leader);

        $result = create_group::execute($this->cm->cmid, 'Test Group', '', '🛡', 0, '');
        $this->groupid = $result['groupid'];
    }

    /**
     * Test that a student can leave their group successfully.
     */
    public function test_execute_leaves_group_successfully(): void {
        $result = leave_group::execute($this->cm->cmid);

        $this->assertTrue($result['success']);
        $this->assertFalse(groups_is_member($this->groupid, $this->leader->id));
    }

    /**
     * Test that leaving a group when canleave is disabled throws an exception.
     */
    public function test_execute_throws_when_canleave_disabled(): void {
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course'   => $this->course->id,
            'canleave' => 0,
        ]);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, 'student');
        $this->setUser($user);
        create_group::execute($cm->cmid, 'No Leave Group', '', '🛡', 0, '');

        $this->expectException(moodle_exception::class);
        leave_group::execute($cm->cmid);
    }

    /**
     * Test that leaving a group when not a member throws an exception.
     */
    public function test_execute_throws_when_not_in_group(): void {
        $nonmember = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($nonmember->id, $this->course->id, 'student');
        $this->setUser($nonmember);

        $this->expectException(moodle_exception::class);
        leave_group::execute($this->cm->cmid);
    }

    /**
     * Test that when the last member leaves and deleteemptygroups is enabled,
     * the group and its metadata are deleted automatically.
     */
    public function test_execute_deletes_empty_group_when_setting_enabled(): void {
        global $DB;

        $result = leave_group::execute($this->cm->cmid);

        $this->assertTrue($result['success']);
        $this->assertFalse($DB->record_exists('groups', ['id' => $this->groupid]));
        $this->assertFalse($DB->record_exists('playergroup_meta', ['groupid' => $this->groupid]));
    }

    /**
     * Test that when the last member leaves and deleteemptygroups is disabled,
     * the group is preserved.
     */
    public function test_execute_preserves_empty_group_when_setting_disabled(): void {
        global $DB;

        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course'            => $this->course->id,
            'canleave'          => 1,
            'deleteemptygroups' => 0,
        ]);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id, 'student');
        $this->setUser($user);
        $created = create_group::execute($cm->cmid, 'Preserved Group', '', '🛡', 0, '');

        leave_group::execute($cm->cmid);

        $this->assertTrue($DB->record_exists('groups', ['id' => $created['groupid']]));
        $this->assertTrue($DB->record_exists('playergroup_meta', ['groupid' => $created['groupid']]));
    }

    /**
     * Test that when the leader leaves a group with remaining members,
     * leadership is transferred to the oldest remaining member.
     */
    public function test_execute_transfers_leadership_when_leader_leaves(): void {
        global $DB;

        $member = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($member->id, $this->course->id, 'student');
        $this->setUser($member);
        join_group::execute($this->cm->cmid, $this->groupid, '');

        $this->setUser($this->leader);
        leave_group::execute($this->cm->cmid);

        $meta = $DB->get_record('playergroup_meta', ['groupid' => $this->groupid], '*', MUST_EXIST);
        $this->assertEquals($member->id, (int) $meta->creatorid);
    }

    /**
     * Test that when a non-leader member leaves, leadership is unchanged.
     */
    public function test_execute_does_not_change_leader_when_member_leaves(): void {
        global $DB;

        $member = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($member->id, $this->course->id, 'student');
        $this->setUser($member);
        join_group::execute($this->cm->cmid, $this->groupid, '');

        leave_group::execute($this->cm->cmid);

        $meta = $DB->get_record('playergroup_meta', ['groupid' => $this->groupid], '*', MUST_EXIST);
        $this->assertEquals($this->leader->id, (int) $meta->creatorid);
    }

    /**
     * Test that pending invites for a deleted group are also removed.
     */
    public function test_execute_cancels_pending_invites_when_group_deleted(): void {
        global $DB;

        $invitee = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($invitee->id, $this->course->id, 'student');

        $this->setUser($this->leader);
        send_invite::execute($this->cm->cmid, $invitee->id);

        $this->assertTrue($DB->record_exists('playergroup_invites', ['groupid' => $this->groupid]));

        leave_group::execute($this->cm->cmid);

        $this->assertFalse($DB->record_exists('playergroup_invites', ['groupid' => $this->groupid]));
    }
}
