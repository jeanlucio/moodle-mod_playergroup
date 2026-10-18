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
        $this->expectExceptionMessage(get_string('errnotinvitee', 'mod_playergroup'));
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

    /**
     * Takes a capability away from the student role in the course.
     *
     * @param string $capability Capability to prohibit.
     */
    private function prohibit_for_students(string $capability): void {
        global $DB;

        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability($capability, CAP_PROHIBIT, $studentrole, \context_course::instance($this->course->id)->id, true);
        // A new request would build the module info again; here it is cached from before the change.
        rebuild_course_cache($this->course->id, true, true);
    }

    /**
     * Test that declining stays possible after the activity closed.
     *
     * Declining only changes the receiver's own invite, so there is nothing to freeze; the window
     * applies to what changes the composition of the groups.
     */
    public function test_execute_still_works_after_the_close_date(): void {
        global $DB;

        $DB->set_field('playergroup', 'timeclose', time() - HOURSECS, ['id' => $this->cm->id]);

        $result = reject_invite::execute($this->inviteid);

        $this->assertTrue($result['success']);
        $this->assertSame(2, (int) $DB->get_field('playergroup_invites', 'status', ['id' => $this->inviteid]));
    }

    /**
     * Test that without the right to view the activity the core treats it as hidden, and the invite is left as it was.
     */
    public function test_execute_refuses_without_the_view_capability(): void {
        global $DB;

        $this->prohibit_for_students('mod/playergroup:view');

        try {
            reject_invite::execute($this->inviteid);
            $this->fail('Declining needs the right to view the activity.');
        } catch (\require_login_exception $e) {
            $this->assertSame(0, (int) $DB->get_field('playergroup_invites', 'status', ['id' => $this->inviteid]));
        }
    }

    /**
     * Test that a student no longer enrolled in the course cannot answer the invite.
     */
    public function test_execute_refuses_a_receiver_who_left_the_course(): void {
        global $DB;

        $instance = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->unenrol_user($instance, $this->receiver->id);

        $this->expectException(\require_login_exception::class);
        reject_invite::execute($this->inviteid);
    }

    /**
     * Test that the answer has the shape the service declares.
     */
    public function test_execute_returns_the_declared_structure(): void {
        $result = reject_invite::execute($this->inviteid);
        $clean = \core_external\external_api::clean_returnvalue(reject_invite::execute_returns(), $result);

        $this->assertTrue($clean['success']);
        $this->assertNotSame('', $clean['message']);
    }
}
