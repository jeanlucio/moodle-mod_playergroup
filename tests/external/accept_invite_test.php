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
 * Unit tests for the accept_invite external function.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\external;

use advanced_testcase;
use moodle_database;
use moodle_exception;

/**
 * Tests for \mod_playergroup\external\accept_invite.
 *
 * @covers \mod_playergroup\external\accept_invite
 * @covers \mod_playergroup\local\group_lock
 */
final class accept_invite_test extends advanced_testcase {
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

    /** @var ?moodle_database Second, independent connection used to simulate a concurrent request. */
    private ?moodle_database $seconddb = null;

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
     * Dispose of the second connection, if one was opened.
     */
    protected function tearDown(): void {
        if ($this->seconddb !== null) {
            $this->seconddb->dispose();
            $this->seconddb = null;
        }
        parent::tearDown();
    }

    /**
     * Test that a receiver can accept a pending invite successfully.
     */
    public function test_execute_accepts_invite_successfully(): void {
        global $DB;

        $result = accept_invite::execute($this->inviteid);

        $this->assertTrue($result['success']);
        $this->assertTrue(groups_is_member($this->groupid, $this->receiver->id));
        $this->assertEquals(
            1,
            (int) $DB->get_field('playergroup_invites', 'status', ['id' => $this->inviteid])
        );
    }

    /**
     * Test that accepting an invite with manual completion does not throw an exception.
     *
     * Regression: COMPLETION_UNKNOWN (-1) was previously passed to update_state()
     * when completion mode was manual, causing an err_system exception.
     */
    public function test_execute_with_manual_completion_does_not_throw(): void {
        global $CFG, $DB;
        $CFG->enablecompletion = true;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course'     => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $sender = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($sender->id, $course->id, 'student');
        $this->setUser($sender);
        $created = create_group::execute($cm->cmid, 'Manual Group', '', '🛡', 0, '');

        $receiver = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($receiver->id, $course->id, 'student');

        $invite = (object) [
            'playergroupid' => $cm->id,
            'groupid'       => $created['groupid'],
            'senderid'      => $sender->id,
            'receiverid'    => $receiver->id,
            'status'        => 0,
            'timecreated'   => time(),
            'timemodified'  => time(),
        ];
        $inviteid = $DB->insert_record('playergroup_invites', $invite);

        $this->setUser($receiver);
        $result = accept_invite::execute($inviteid);
        $this->assertTrue($result['success']);
    }

    /**
     * Test that accepting an invite with automatic completion marks the activity as complete.
     */
    public function test_execute_with_automatic_completion_marks_complete(): void {
        global $CFG, $DB;
        $CFG->enablecompletion = true;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course'              => $course->id,
            'completion'          => COMPLETION_TRACKING_AUTOMATIC,
            'completionjoingroup' => 1,
        ]);

        $sender = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($sender->id, $course->id, 'student');
        $this->setUser($sender);
        $created = create_group::execute($cm->cmid, 'Auto Group', '', '🛡', 0, '');

        $receiver = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($receiver->id, $course->id, 'student');

        $invite = (object) [
            'playergroupid' => $cm->id,
            'groupid'       => $created['groupid'],
            'senderid'      => $sender->id,
            'receiverid'    => $receiver->id,
            'status'        => 0,
            'timecreated'   => time(),
            'timemodified'  => time(),
        ];
        $inviteid = $DB->insert_record('playergroup_invites', $invite);

        $this->setUser($receiver);
        accept_invite::execute($inviteid);

        $cminfo = get_fast_modinfo($course)->get_cm($cm->cmid);
        $completion = new \completion_info($course);
        $data = $completion->get_data($cminfo, false, $receiver->id);
        $this->assertEquals(COMPLETION_COMPLETE, (int) $data->completionstate);
    }

    /**
     * Test that a user cannot accept an invite addressed to someone else.
     */
    public function test_execute_rejects_wrong_user(): void {
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($other->id, $this->course->id, 'student');
        $this->setUser($other);

        $this->expectException(moodle_exception::class);
        accept_invite::execute($this->inviteid);
    }

    /**
     * Test that an invite that was already handled cannot be accepted again.
     */
    public function test_execute_rejects_already_handled_invite(): void {
        global $DB;
        $DB->set_field('playergroup_invites', 'status', 1, ['id' => $this->inviteid]);

        $this->expectException(moodle_exception::class);
        accept_invite::execute($this->inviteid);
    }

    /**
     * Test that accept_invite is blocked while another request holds the per-instance lock.
     *
     * Simulates a genuinely concurrent request (a second, independent database connection)
     * already inside the check-then-act section for this activity instance. A same-connection
     * lock would be reentrant and not prove anything; a second connection is required to show
     * accept_invite::execute() actually goes through \mod_playergroup\local\group_lock.
     */
    public function test_execute_blocked_by_concurrent_lock(): void {
        $otherlock = $this->acquire_on_second_connection('group_' . $this->cm->id);

        try {
            $this->expectException(moodle_exception::class);
            accept_invite::execute($this->inviteid);
        } finally {
            $otherlock->release();
        }
    }

    /**
     * Acquires the mod_playergroup lock factory's lock for the given resource key from a
     * second, independently-connected database session, so contention against it reflects a
     * genuinely different session rather than this test's own (self-reentrant) connection.
     *
     * @param string $resourcekey The bare resource key (without the factory's type prefix).
     * @return \core\lock\lock The lock, held on the second connection; the caller must release it.
     */
    private function acquire_on_second_connection(string $resourcekey): \core\lock\lock {
        global $DB;

        $cfg = $DB->export_dbconfig();
        if (!isset($cfg->dboptions)) {
            $cfg->dboptions = [];
        }

        $this->seconddb = moodle_database::get_driver_instance($cfg->dbtype, $cfg->dblibrary);
        $this->seconddb->connect($cfg->dbhost, $cfg->dbuser, $cfg->dbpass, $cfg->dbname, $cfg->prefix, $cfg->dboptions);

        $original = $GLOBALS['DB'];
        $GLOBALS['DB'] = $this->seconddb;
        try {
            $factory = \core\lock\lock_config::get_lock_factory('mod_playergroup');
            $lock = $factory->get_lock($resourcekey, 1);
        } finally {
            $GLOBALS['DB'] = $original;
        }

        if (!$lock) {
            $this->fail('Precondition: the second connection must be able to acquire the lock.');
        }

        return $lock;
    }
}
