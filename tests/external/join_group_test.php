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
 * Unit tests for the join_group external function.
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
 * Tests for \mod_playergroup\external\join_group.
 *
 * @covers \mod_playergroup\external\join_group
 * @covers \mod_playergroup\local\group_lock
 */
final class join_group_test extends advanced_testcase {
    /** @var \stdClass Course record. */
    private \stdClass $course;

    /** @var \stdClass Course module record returned by create_module(). */
    private \stdClass $cm;

    /** @var \stdClass User who created the group. */
    private \stdClass $creator;

    /** @var \stdClass User who will join the group. */
    private \stdClass $joiner;

    /** @var int ID of the group created in setUp. */
    private int $groupid;

    /** @var ?moodle_database Second, independent connection used to simulate a concurrent request. */
    private ?moodle_database $seconddb = null;

    /**
     * Set up fixtures for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $this->course->id,
        ]);

        $this->creator = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->creator->id, $this->course->id, 'student');
        $this->setUser($this->creator);
        $result = create_group::execute($this->cm->cmid, 'Test Group', '', '🛡', 0, '');
        $this->groupid = $result['groupid'];

        $this->joiner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->joiner->id, $this->course->id, 'student');
        $this->setUser($this->joiner);
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
     * Test that a student can join an open group successfully.
     */
    public function test_execute_joins_group_successfully(): void {
        $result = join_group::execute($this->cm->cmid, $this->groupid, '');

        $this->assertTrue($result['success']);
        $this->assertTrue(groups_is_member($this->groupid, $this->joiner->id));
    }

    /**
     * Test that joining a group with manual completion does not throw an exception.
     *
     * Regression: COMPLETION_UNKNOWN (-1) was previously passed to update_state()
     * when completion mode was manual, causing an err_system exception.
     */
    public function test_execute_with_manual_completion_does_not_throw(): void {
        global $CFG;
        $CFG->enablecompletion = true;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course'     => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $creator = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($creator->id, $course->id, 'student');
        $this->setUser($creator);
        $created = create_group::execute($cm->cmid, 'Leaders', '', '🛡', 0, '');

        $joiner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($joiner->id, $course->id, 'student');
        $this->setUser($joiner);

        $result = join_group::execute($cm->cmid, $created['groupid'], '');
        $this->assertTrue($result['success']);
    }

    /**
     * Test that joining a group with automatic completion marks the activity as complete.
     */
    public function test_execute_with_automatic_completion_marks_complete(): void {
        global $CFG;
        $CFG->enablecompletion = true;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course'              => $course->id,
            'completion'          => COMPLETION_TRACKING_AUTOMATIC,
            'completionjoingroup' => 1,
        ]);

        $creator = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($creator->id, $course->id, 'student');
        $this->setUser($creator);
        $created = create_group::execute($cm->cmid, 'Founders', '', '🛡', 0, '');

        $joiner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($joiner->id, $course->id, 'student');
        $this->setUser($joiner);

        join_group::execute($cm->cmid, $created['groupid'], '');

        $cminfo = get_fast_modinfo($course)->get_cm($cm->cmid);
        $completion = new \completion_info($course);
        $data = $completion->get_data($cminfo, false, $joiner->id);
        $this->assertEquals(COMPLETION_COMPLETE, (int) $data->completionstate);
    }

    /**
     * Test that a user already in a group cannot join another.
     */
    public function test_execute_rejects_if_already_in_group(): void {
        $this->setUser($this->creator);
        $this->expectException(moodle_exception::class);
        join_group::execute($this->cm->cmid, $this->groupid, '');
    }

    /**
     * Test that a closed group (privacy=2) cannot be joined directly.
     */
    public function test_execute_rejects_closed_group(): void {
        global $DB;
        $DB->set_field('playergroup_meta', 'privacy', 2, ['groupid' => $this->groupid]);

        $this->expectException(moodle_exception::class);
        join_group::execute($this->cm->cmid, $this->groupid, '');
    }

    /**
     * Test that a protected group (privacy=1) can be joined with the correct password.
     */
    public function test_execute_protected_group_with_correct_password(): void {
        $this->make_group_protected('secret123');

        $result = join_group::execute($this->cm->cmid, $this->groupid, 'secret123');

        $this->assertTrue($result['success']);
        $this->assertTrue(groups_is_member($this->groupid, $this->joiner->id));
    }

    /**
     * Test that joining a protected group with the wrong password is rejected.
     *
     * Asserts it is not a coding_exception, which extends moodle_exception and
     * would therefore also satisfy a plain expectException(moodle_exception::class).
     * This business rule must throw plain moodle_exception (see amd/src/main.js's
     * Notification.alert handling), or the AJAX caller falls back to the "contact
     * a developer" dialog instead of the friendly translated message.
     */
    public function test_execute_protected_group_with_wrong_password(): void {
        $this->make_group_protected('secret123');

        try {
            join_group::execute($this->cm->cmid, $this->groupid, 'wrongpass');
            $this->fail('Expected a moodle_exception for the wrong password.');
        } catch (moodle_exception $e) {
            $this->assertNotInstanceOf(\coding_exception::class, $e);
            $this->assertEquals('wrongpassword', $e->errorcode);
        }
    }

    /**
     * Test that an invited student who joins by password has the invite marked as accepted.
     *
     * The user has a pending invite to the group and joins via the password flow instead of
     * accepting it. The invite must be marked accepted (status 1) so it does not reappear if
     * the user later leaves the group.
     */
    public function test_execute_invited_user_joins_with_password_marks_invite_accepted(): void {
        global $DB;
        $this->make_group_protected('secret123');
        $inviteid = $this->create_invite($this->groupid);

        join_group::execute($this->cm->cmid, $this->groupid, 'secret123');

        $this->assertTrue(groups_is_member($this->groupid, $this->joiner->id));
        $this->assertEquals(
            1,
            (int) $DB->get_field('playergroup_invites', 'status', ['id' => $inviteid])
        );
    }

    /**
     * Test that joining a group declines the user's pending invites to other groups.
     */
    public function test_execute_join_declines_other_pending_invites(): void {
        global $DB;

        $otherleader = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($otherleader->id, $this->course->id, 'student');
        $this->setUser($otherleader);
        $other = create_group::execute($this->cm->cmid, 'Other Group', '', '🛡', 0, '');
        $otherinviteid = $this->create_invite((int) $other['groupid']);

        $this->setUser($this->joiner);
        join_group::execute($this->cm->cmid, $this->groupid, '');

        $this->assertEquals(
            2,
            (int) $DB->get_field('playergroup_invites', 'status', ['id' => $otherinviteid])
        );
    }

    /**
     * Test that join_group throws instead of silently succeeding when the user cannot
     * actually be added to the group.
     *
     * groups_add_member() returns false (not an exception) when the caller is not enrolled in
     * the group's course. A site admin passes require_login()/require_capability() everywhere
     * regardless of enrolment, but is_enrolled() (checked inside groups_add_member()) still
     * returns false for them if they hold no enrolment in this course; without checking the
     * return value, join_group went on to award a grade and fire member_joined for a
     * membership that never happened.
     */
    public function test_execute_throws_when_add_member_fails(): void {
        global $DB, $USER;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'grade'  => 10.0,
        ]);

        $creator = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($creator->id, $course->id, 'student');
        $this->setUser($creator);
        $created = create_group::execute($cm->cmid, 'Founders', '', '🛡', 0, '');

        $this->setAdminUser();
        try {
            join_group::execute($cm->cmid, $created['groupid'], '');
            $this->fail('Expected a moodle_exception because the admin is not enrolled.');
        } catch (moodle_exception $e) {
            $this->assertEquals('joinfailed', $e->errorcode);
        }

        $this->assertFalse(groups_is_member($created['groupid'], $USER->id));
        $gradeexists = $DB->record_exists_sql(
            "SELECT gg.id
               FROM {grade_grades} gg
               JOIN {grade_items} gi ON gi.id = gg.itemid
              WHERE gi.itemtype = 'mod' AND gi.itemmodule = 'playergroup'
                AND gi.iteminstance = :instanceid AND gg.userid = :userid",
            ['instanceid' => $cm->id, 'userid' => $USER->id]
        );
        $this->assertFalse($gradeexists);
    }

    /**
     * Test that join_group is blocked while another request holds the per-instance lock.
     *
     * Simulates a genuinely concurrent request (a second, independent database connection)
     * already inside the check-then-act section for this activity instance. A same-connection
     * lock would be reentrant and not prove anything; a second connection is required to show
     * join_group::execute() actually goes through \mod_playergroup\local\group_lock.
     */
    public function test_execute_blocked_by_concurrent_lock(): void {
        $otherlock = $this->acquire_on_second_connection('group_' . $this->cm->id);

        try {
            $this->expectException(moodle_exception::class);
            join_group::execute($this->cm->cmid, $this->groupid, '');
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

    /**
     * Switch the group created in setUp to protected (privacy=1) with the given password.
     *
     * @param string $password Plain-text password to set on the group.
     */
    private function make_group_protected(string $password): void {
        global $DB;
        $DB->set_field('playergroup_meta', 'privacy', 1, ['groupid' => $this->groupid]);
        $DB->set_field('playergroup_meta', 'password', password_hash($password, PASSWORD_DEFAULT), [
            'groupid' => $this->groupid,
        ]);
    }

    /**
     * Insert a pending invite addressed to the joiner for the given group.
     *
     * @param int $groupid Group the invite points to.
     * @return int ID of the inserted invite.
     */
    private function create_invite(int $groupid): int {
        global $DB;
        return (int) $DB->insert_record('playergroup_invites', (object) [
            'playergroupid' => $this->cm->id,
            'groupid'       => $groupid,
            'senderid'      => $this->creator->id,
            'receiverid'    => $this->joiner->id,
            'status'        => 0,
            'timecreated'   => time(),
            'timemodified'  => time(),
        ]);
    }
}
