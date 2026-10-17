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
 * Unit tests for the create_group external function.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\external;

use advanced_testcase;
use context_module;
use moodle_database;
use moodle_exception;

/**
 * Tests for \mod_playergroup\external\create_group.
 *
 * @covers \mod_playergroup\external\create_group
 * @covers \mod_playergroup\local\group_lock
 */
final class create_group_test extends advanced_testcase {
    /** @var \stdClass Course record. */
    private \stdClass $course;

    /** @var \stdClass Course module record (cm row). */
    private \stdClass $cm;

    /** @var \stdClass Student user with creategroup capability. */
    private \stdClass $student;

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
        $this->student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, 'student');
        $this->setUser($this->student);
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
     * Test successful group creation returns success and a valid groupid.
     */
    public function test_execute_creates_group_successfully(): void {
        global $DB;

        $result = create_group::execute($this->cm->cmid, 'Heroes', '<p>Brave heroes</p>', '⚔', 0, '');

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['groupid']);
        $this->assertTrue($DB->record_exists('groups', ['id' => $result['groupid']]));
        $this->assertTrue($DB->record_exists('playergroup_meta', [
            'groupid'       => $result['groupid'],
            'playergroupid' => $this->cm->id,
            'creatorid'     => $this->student->id,
        ]));
    }

    /**
     * Test that a protected group stores a hashed password.
     */
    public function test_execute_hashes_password_for_protected_group(): void {
        global $DB;

        $result = create_group::execute($this->cm->cmid, 'Vault', '', '🔒', 1, 'secret123');

        $meta = $DB->get_record('playergroup_meta', ['groupid' => $result['groupid']]);
        $this->assertNotEmpty($meta->password);
        $this->assertTrue(password_verify('secret123', $meta->password));
    }

    /**
     * Test that a protected group without a password is refused and leaves nothing behind.
     *
     * A protected group stored with an empty hash can never be joined, since join_group
     * rejects every password against an empty hash.
     */
    public function test_execute_rejects_protected_group_without_password(): void {
        global $DB;

        $groupsbefore = $DB->count_records('groups', ['courseid' => $this->course->id]);

        try {
            create_group::execute($this->cm->cmid, 'Vault', '', '🔒', 1, '');
            $this->fail('A protected group without a password must be rejected.');
        } catch (moodle_exception $e) {
            $this->assertSame('errpasswordrequired', $e->errorcode);
        }

        $this->assertSame($groupsbefore, $DB->count_records('groups', ['courseid' => $this->course->id]));
        $this->assertSame(0, $DB->count_records('playergroup_meta'));
    }

    /**
     * Test that a whitespace-only password is refused, as the join dialog cannot submit one.
     */
    public function test_execute_rejects_protected_group_with_blank_password(): void {
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('errpasswordrequired', 'mod_playergroup'));
        create_group::execute($this->cm->cmid, 'Vault', '', '🔒', 1, '   ');
    }

    /**
     * Test that a privacy level outside 0, 1 and 2 is refused.
     */
    public function test_execute_rejects_invalid_privacy_level(): void {
        foreach ([-1, 3] as $privacy) {
            try {
                create_group::execute($this->cm->cmid, 'Odd', '', '🛡', $privacy, '');
                $this->fail("Privacy level $privacy must be rejected.");
            } catch (moodle_exception $e) {
                $this->assertSame('errinvalidprivacy', $e->errorcode);
            }
        }
    }

    /**
     * Test that an open group stores privacy=0.
     */
    public function test_execute_open_group_privacy_zero(): void {
        global $DB;

        $result = create_group::execute($this->cm->cmid, 'Open Squad', '', '🏳', 0, '');

        $meta = $DB->get_record('playergroup_meta', ['groupid' => $result['groupid']]);
        $this->assertEquals(0, (int) $meta->privacy);
    }

    /**
     * Test that a closed group stores privacy=2.
     */
    public function test_execute_closed_group_privacy_two(): void {
        global $DB;

        $result = create_group::execute($this->cm->cmid, 'Elite', '', '🛡', 2, '');

        $meta = $DB->get_record('playergroup_meta', ['groupid' => $result['groupid']]);
        $this->assertEquals(2, (int) $meta->privacy);
    }

    /**
     * Test that the creator is added as a member of the new group.
     */
    public function test_execute_adds_creator_as_member(): void {
        $result = create_group::execute($this->cm->cmid, 'Founders', '', '⚡', 0, '');

        $this->assertTrue(groups_is_member($result['groupid'], $this->student->id));
    }

    /**
     * Test that a user without creategroup capability gets an exception.
     */
    public function test_execute_requires_capability(): void {
        $context = context_module::instance($this->cm->cmid);
        $studentrole = $this->getDataGenerator()->create_role();
        assign_capability('mod/playergroup:creategroup', CAP_PROHIBIT, $studentrole, $context);
        role_assign($studentrole, $this->student->id, $context);
        accesslib_clear_all_caches_for_unit_testing();

        $this->expectException(\required_capability_exception::class);
        create_group::execute($this->cm->cmid, 'Forbidden', '', '❌', 0, '');
    }

    /**
     * Test that a student who already has a group cannot create another one.
     */
    public function test_execute_rejects_duplicate_group(): void {
        global $DB;

        // First creation.
        $result = create_group::execute($this->cm->cmid, 'First', '', '1️⃣', 0, '');
        $this->assertTrue($result['success']);

        // Second creation by the same student in the same activity.
        $this->expectException(moodle_exception::class);
        create_group::execute($this->cm->cmid, 'Second', '', '2️⃣', 0, '');
    }

    /**
     * Test that an invalid cmid (non-existent) throws an exception.
     */
    public function test_execute_invalid_cmid_throws(): void {
        $this->expectException(\dml_missing_record_exception::class);
        create_group::execute(999999, 'Test', '', '❓', 0, '');
    }

    /**
     * Test that creating a group with manual completion does not throw an exception.
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
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $result = create_group::execute($cm->cmid, 'Manual Group', '', '🛡', 0, '');
        $this->assertTrue($result['success']);
    }

    /**
     * Test that creating a group with automatic completion marks the activity as complete when a
     * group of one member is enough, that is, when the minimum is 1.
     */
    public function test_execute_with_automatic_completion_marks_complete(): void {
        global $CFG;
        $CFG->enablecompletion = true;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course'              => $course->id,
            'minmembers'          => 1,
            'completion'          => COMPLETION_TRACKING_AUTOMATIC,
            'completionjoingroup' => 1,
        ]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        create_group::execute($cm->cmid, 'Auto Group', '', '🛡', 0, '');

        $cminfo = get_fast_modinfo($course)->get_cm($cm->cmid);
        $completion = new \completion_info($course);
        $data = $completion->get_data($cminfo, false, $student->id);
        $this->assertEquals(COMPLETION_COMPLETE, (int) $data->completionstate);
    }

    /**
     * Test that create_group throws instead of leaving an orphaned native group when the
     * caller cannot actually be added to it.
     *
     * groups_add_member() returns false (not an exception) when the caller is not enrolled
     * in the group's course. A site admin passes require_login()/require_capability()
     * everywhere regardless of enrolment, but is_enrolled() (checked inside
     * groups_add_member()) still returns false for them if they hold no enrolment in this
     * course; without checking the return value, create_group left behind a native group
     * with no real member and a playergroup_meta row pointing at it.
     */
    public function test_execute_throws_and_cleans_up_when_add_member_fails(): void {
        global $DB;

        $groupsbefore = $DB->count_records('groups', ['courseid' => $this->course->id]);
        $metabefore = $DB->count_records('playergroup_meta', ['playergroupid' => $this->cm->id]);

        $this->setAdminUser();
        try {
            create_group::execute($this->cm->cmid, 'Ghost Group', '', '👻', 0, '');
            $this->fail('Expected a moodle_exception because the admin is not enrolled.');
        } catch (moodle_exception $e) {
            $this->assertEquals('joinfailed', $e->errorcode);
        }

        $this->assertEquals($groupsbefore, $DB->count_records('groups', ['courseid' => $this->course->id]));
        $this->assertEquals($metabefore, $DB->count_records('playergroup_meta', ['playergroupid' => $this->cm->id]));
    }

    /**
     * Test that create_group is blocked while another request holds the per-instance lock.
     *
     * Simulates a genuinely concurrent request (a second, independent database connection)
     * already inside the check-then-act section for this activity instance. A same-connection
     * lock would be reentrant and not prove anything; a second connection is required to show
     * create_group::execute() actually goes through \mod_playergroup\local\group_lock.
     */
    public function test_execute_blocked_by_concurrent_lock(): void {
        $otherlock = $this->acquire_on_second_connection('group_' . $this->cm->id);

        try {
            $this->expectException(moodle_exception::class);
            create_group::execute($this->cm->cmid, 'Blocked', '', '🔒', 0, '');
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
     * Test that no group is created after the activity closed.
     */
    public function test_execute_refused_after_close_date(): void {
        global $DB;

        $DB->set_field('playergroup', 'timeclose', time() - HOURSECS, ['id' => $this->cm->id]);
        $groupsbefore = $DB->count_records('groups', ['courseid' => $this->course->id]);

        try {
            create_group::execute($this->cm->cmid, 'Late', '', '🛡', 0, '');
            $this->fail('A group cannot be created after the activity closed.');
        } catch (moodle_exception $e) {
            $this->assertSame('activityclosed', $e->errorcode);
        }
        $this->assertSame($groupsbefore, $DB->count_records('groups', ['courseid' => $this->course->id]));
    }

    /**
     * Test that no group is created before the activity opens.
     */
    public function test_execute_refused_before_open_date(): void {
        global $DB;

        $DB->set_field('playergroup', 'timeopen', time() + HOURSECS, ['id' => $this->cm->id]);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('activitynotopen', 'mod_playergroup'));
        create_group::execute($this->cm->cmid, 'Early', '', '🛡', 0, '');
    }

    /**
     * Test that someone outside the course cannot create a group.
     */
    public function test_execute_refuses_a_user_not_enrolled_in_the_course(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\require_login_exception::class);
        create_group::execute($this->cm->cmid, 'Intruder', '', '🛡', 0, '');
    }

    /**
     * Test that the answer has the shape the service declares, including the new group's id.
     */
    public function test_execute_returns_the_declared_structure(): void {
        $result = create_group::execute($this->cm->cmid, 'Shape', '', '🛡', 0, '');
        $clean = \core_external\external_api::clean_returnvalue(create_group::execute_returns(), $result);

        $this->assertTrue($clean['success']);
        $this->assertGreaterThan(0, $clean['groupid']);
        $this->assertNotSame('', $clean['message']);
    }

    /**
     * Test that the description is kept as the plain text the student typed.
     *
     * The field is a plain textarea. Stored as HTML, the line breaks vanish on the page and the editing
     * field shows "&amp;" for "&".
     */
    public function test_the_description_is_kept_as_typed(): void {
        global $DB;

        $typed = "P&D > everything\nSecond line";
        $result = create_group::execute($this->cm->cmid, 'Notes', $typed, '⚔', 0, '');

        $group = $DB->get_record('groups', ['id' => $result['groupid']], '*', MUST_EXIST);
        $this->assertSame($typed, $group->description);
        $this->assertEquals(FORMAT_PLAIN, $group->descriptionformat);

        $shown = format_text($group->description, (int) $group->descriptionformat);
        $this->assertStringContainsString('<br', $shown);
        $this->assertStringContainsString('P&amp;D &gt; everything', $shown);
    }

    /**
     * Test that markup in the description is removed, not stored.
     */
    public function test_markup_in_the_description_is_removed(): void {
        global $DB;

        $result = create_group::execute(
            $this->cm->cmid,
            'Markup',
            '<p>Hello</p><script>alert(1)</script><b>there</b>',
            '⚔',
            0,
            ''
        );

        $stored = $DB->get_field('groups', 'description', ['id' => $result['groupid']]);
        $this->assertStringNotContainsString('<', $stored);
        $this->assertStringContainsString('Hello', $stored);
        $this->assertStringContainsString('there', $stored);
    }

    /**
     * Provides names the group cannot have.
     *
     * @return array[] The submitted name.
     */
    public static function invalid_names(): array {
        return [
            'empty' => [''],
            'only spaces' => ['    '],
            'too long' => [str_repeat('a', 255)],
            'too long in multibyte letters' => [str_repeat('é', 255)],
        ];
    }

    /**
     * Test that an empty name, or one longer than the column, is refused with a message and creates nothing.
     *
     * @dataProvider invalid_names
     * @param string $name The submitted name.
     */
    public function test_an_invalid_name_is_refused_and_creates_nothing(string $name): void {
        global $DB;

        $groups = $DB->count_records('groups', ['courseid' => $this->course->id]);
        try {
            create_group::execute($this->cm->cmid, $name, '', '⚔', 0, '');
            $this->fail('The name should have been refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('errinvalidgroupname', $e->errorcode);
        }
        $this->assertSame($groups, $DB->count_records('groups', ['courseid' => $this->course->id]));
        $this->assertSame(0, $DB->count_records('playergroup_meta', ['playergroupid' => $this->cm->id]));
    }

    /**
     * Test that the longest name the column holds is accepted, and spaces around it are dropped.
     */
    public function test_the_longest_name_is_accepted(): void {
        global $DB;

        $name = str_repeat('é', 254);
        $result = create_group::execute($this->cm->cmid, "  $name  ", '', '⚔', 0, '');

        $this->assertSame($name, $DB->get_field('groups', 'name', ['id' => $result['groupid']]));
    }

    /**
     * Test that a badge longer than its column is refused.
     */
    public function test_a_badge_longer_than_the_column_is_refused(): void {
        try {
            create_group::execute($this->cm->cmid, 'Name', '', str_repeat('x', 256), 0, '');
            $this->fail('The badge should have been refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('errinvalidbadge', $e->errorcode);
        }
    }

    /**
     * Provides the rights without which a closed group could never get a member.
     *
     * @return array[] The capability taken from the students.
     */
    public static function invite_rights(): array {
        return [
            'invite' => ['mod/playergroup:manageinvites'],
            'see participants' => ['moodle/course:viewparticipants'],
        ];
    }

    /**
     * Test that a closed group is refused to someone who cannot invite.
     *
     * A closed group only grows by invitation, so the group would stay with its founder for good.
     *
     * @dataProvider invite_rights
     * @param string $capability The capability taken from the students.
     */
    public function test_a_closed_group_is_refused_when_the_user_cannot_invite(string $capability): void {
        global $DB;

        $this->prohibit_for_students($capability);

        try {
            create_group::execute($this->cm->cmid, 'Closed', '', '🛡', 2, '');
            $this->fail('The closed group should have been refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('errcannotclose', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('playergroup_meta', ['playergroupid' => $this->cm->id]));
        $this->assertTrue(create_group::execute($this->cm->cmid, 'Open', '', '🛡', 0, '')['success']);
    }

    /**
     * Test that a closed group is accepted when the user can invite.
     */
    public function test_a_closed_group_is_accepted_when_the_user_can_invite(): void {
        global $DB;

        $result = create_group::execute($this->cm->cmid, 'Closed', '', '🛡', 2, '');

        $this->assertEquals(2, $DB->get_field('playergroup_meta', 'privacy', ['groupid' => $result['groupid']]));
    }

    /**
     * Test that founding a group settles the invites the student had received.
     *
     * Left pending, they came back as new invites when the student left the group weeks later.
     */
    public function test_founding_a_group_declines_the_pending_invites_received(): void {
        global $DB;
        $this->redirectMessages();

        $sender = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($sender);
        create_group::execute($this->cm->cmid, 'Sender Group', '', '🛡', 0, '');
        send_invite::execute($this->cm->cmid, $this->student->id);
        $this->assertSame(1, $DB->count_records('playergroup_invites', ['receiverid' => $this->student->id, 'status' => 0]));

        $this->setUser($this->student);
        create_group::execute($this->cm->cmid, 'Mine', '', '🛡', 0, '');

        $this->assertSame(0, $DB->count_records('playergroup_invites', ['receiverid' => $this->student->id, 'status' => 0]));
        $this->assertSame(1, $DB->count_records('playergroup_invites', ['receiverid' => $this->student->id, 'status' => 2]));
    }

    /**
     * Test that the settled invite does not come back when the student leaves the group.
     */
    public function test_a_declined_invite_does_not_return_after_leaving_the_group(): void {
        $this->redirectMessages();

        $sender = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($sender);
        create_group::execute($this->cm->cmid, 'Sender Group', '', '🛡', 0, '');
        send_invite::execute($this->cm->cmid, $this->student->id);
        $this->setUser($this->student);
        create_group::execute($this->cm->cmid, 'Mine', '', '🛡', 0, '');

        leave_group::execute($this->cm->cmid);

        $data = get_activity_data::execute($this->cm->cmid);
        $this->assertSame([], $data['receivedinvites']);
    }

    /**
     * Test that invites of other activities are left alone.
     */
    public function test_founding_a_group_leaves_the_invites_of_other_activities(): void {
        global $DB;
        $this->redirectMessages();

        $other = $this->getDataGenerator()->create_module('playergroup', ['course' => $this->course->id]);
        $sender = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($sender);
        create_group::execute($other->cmid, 'Other Group', '', '🛡', 0, '');
        send_invite::execute($other->cmid, $this->student->id);

        $this->setUser($this->student);
        create_group::execute($this->cm->cmid, 'Mine', '', '🛡', 0, '');

        $this->assertSame(1, $DB->count_records('playergroup_invites', ['receiverid' => $this->student->id, 'status' => 0]));
    }
}
