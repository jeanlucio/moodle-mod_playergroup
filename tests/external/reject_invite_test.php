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
use moodle_exception;

/**
 * Tests for \mod_playergroup\external\reject_invite.
 *
 * @covers \mod_playergroup\external\reject_invite
 */
final class reject_invite_test extends advanced_testcase {
    /** @var \stdClass Course record. */
    private \stdClass $course;

    /** @var \stdClass Course module record returned by create_module(). */
    private \stdClass $cm;

    /** @var \stdClass User who sent the invite. */
    private \stdClass $sender;

    /** @var \stdClass User who received the invite. */
    private \stdClass $receiver;

    /** @var int ID of the group created in setUp. */
    private int $groupid;

    /** @var int ID of the pending invite created in setUp. */
    private int $inviteid;

    /**
     * Set up fixtures for each test.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $this->course->id,
        ]);

        $this->sender = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->sender->id, $this->course->id, 'student');
        $this->setUser($this->sender);
        $result = create_group::execute($this->cm->cmid, 'Alpha', '', '🛡', 0, '');
        $this->groupid = $result['groupid'];

        $this->receiver = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->receiver->id, $this->course->id, 'student');

        $invite = (object) [
            'playergroupid' => $this->cm->id,
            'groupid'       => $this->groupid,
            'senderid'      => $this->sender->id,
            'receiverid'    => $this->receiver->id,
            'status'        => 0,
            'timecreated'   => time(),
            'timemodified'  => time(),
        ];
        $this->inviteid = $DB->insert_record('playergroup_invites', $invite);

        $this->setUser($this->receiver);
    }

    /**
     * Test that a receiver can decline a pending invite successfully.
     */
    public function test_execute_rejects_invite_successfully(): void {
        global $DB;

        $result = reject_invite::execute($this->inviteid);

        $this->assertTrue($result['success']);
        $this->assertFalse(groups_is_member($this->groupid, $this->receiver->id));
        $this->assertEquals(
            2,
            (int) $DB->get_field('playergroup_invites', 'status', ['id' => $this->inviteid])
        );
    }

    /**
     * Test that a user cannot decline an invite addressed to someone else.
     */
    public function test_execute_rejects_wrong_user(): void {
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($other->id, $this->course->id, 'student');
        $this->setUser($other);

        $this->expectException(moodle_exception::class);
        reject_invite::execute($this->inviteid);
    }

    /**
     * Test that an invite that was already handled cannot be declined again.
     */
    public function test_execute_rejects_already_handled_invite(): void {
        global $DB;
        $DB->set_field('playergroup_invites', 'status', 1, ['id' => $this->inviteid]);

        $this->expectException(moodle_exception::class);
        reject_invite::execute($this->inviteid);
    }

    /**
     * Test that a non-existent inviteid throws an exception.
     */
    public function test_execute_invalid_inviteid_throws(): void {
        $this->expectException(\dml_missing_record_exception::class);
        reject_invite::execute(999999);
    }
}
