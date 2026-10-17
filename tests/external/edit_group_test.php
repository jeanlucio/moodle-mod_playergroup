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
 * Unit tests for the edit_group external function.
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
 * Tests for \mod_playergroup\external\edit_group.
 *
 * @covers \mod_playergroup\external\edit_group
 */
final class edit_group_test extends advanced_testcase {
    /** @var \stdClass Course record. */
    private \stdClass $course;

    /** @var \stdClass Course module record (cm row). */
    private \stdClass $cm;

    /** @var \stdClass Student who created the group. */
    private \stdClass $creator;

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
            'course' => $this->course->id,
        ]);

        $this->creator = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->creator->id, $this->course->id, 'student');
        $this->setUser($this->creator);
        $result = create_group::execute($this->cm->cmid, 'Original Name', '<p>Original</p>', '🛡', 0, '');
        $this->groupid = $result['groupid'];
    }

    /**
     * Test that the creator can update the group's name, description and badge.
     */
    public function test_execute_updates_group_successfully(): void {
        global $DB;

        $result = edit_group::execute(
            $this->cm->cmid,
            $this->groupid,
            'New Name',
            '<p>New description</p>',
            '⚔',
            0,
            ''
        );

        $this->assertTrue($result['success']);

        $group = $DB->get_record('groups', ['id' => $this->groupid], '*', MUST_EXIST);
        $this->assertEquals('New Name', $group->name);
        $this->assertStringContainsString('New description', $group->description);

        $meta = $DB->get_record('playergroup_meta', ['groupid' => $this->groupid], '*', MUST_EXIST);
        $this->assertEquals('⚔', $meta->badge);
    }

    /**
     * Test that a user who did not create the group cannot edit it.
     */
    public function test_execute_rejects_non_creator(): void {
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($other->id, $this->course->id, 'student');
        $this->setUser($other);

        $this->expectException(moodle_exception::class);
        edit_group::execute($this->cm->cmid, $this->groupid, 'Hijacked', '', '👾', 0, '');
    }

    /**
     * Test that switching to protected privacy with a new password stores its hash.
     */
    public function test_execute_hashes_new_password_for_protected_group(): void {
        global $DB;

        edit_group::execute($this->cm->cmid, $this->groupid, 'Original Name', '', '🛡', 1, 'secret123');

        $meta = $DB->get_record('playergroup_meta', ['groupid' => $this->groupid], '*', MUST_EXIST);
        $this->assertNotEmpty($meta->password);
        $this->assertTrue(password_verify('secret123', $meta->password));
    }

    /**
     * Test that leaving the password blank on an already-protected group keeps its hash.
     */
    public function test_execute_keeps_existing_password_when_blank(): void {
        global $DB;

        edit_group::execute($this->cm->cmid, $this->groupid, 'Original Name', '', '🛡', 1, 'secret123');
        $originalhash = $DB->get_field('playergroup_meta', 'password', ['groupid' => $this->groupid]);

        edit_group::execute($this->cm->cmid, $this->groupid, 'Renamed', '', '🛡', 1, '');
        $keptash = $DB->get_field('playergroup_meta', 'password', ['groupid' => $this->groupid]);

        $this->assertSame($originalhash, $keptash);
        $this->assertTrue(password_verify('secret123', $keptash));
    }

    /**
     * Test that changing privacy away from protected clears the stored password.
     */
    public function test_execute_clears_password_when_no_longer_protected(): void {
        global $DB;

        edit_group::execute($this->cm->cmid, $this->groupid, 'Original Name', '', '🛡', 1, 'secret123');
        edit_group::execute($this->cm->cmid, $this->groupid, 'Original Name', '', '🛡', 0, '');

        $password = $DB->get_field('playergroup_meta', 'password', ['groupid' => $this->groupid]);
        $this->assertSame('', $password);
    }

    /**
     * Test that making an open group protected without a password is refused and changes nothing.
     */
    public function test_execute_rejects_protected_without_password_and_writes_nothing(): void {
        global $DB;

        try {
            edit_group::execute($this->cm->cmid, $this->groupid, 'Renamed', '', '⚔', 1, '');
            $this->fail('Switching to protected without a password must be rejected.');
        } catch (moodle_exception $e) {
            $this->assertSame('errpasswordrequired', $e->errorcode);
        }

        $this->assertSame('Original Name', $DB->get_field('groups', 'name', ['id' => $this->groupid]));
        $meta = $DB->get_record('playergroup_meta', ['groupid' => $this->groupid], '*', MUST_EXIST);
        $this->assertSame(0, (int) $meta->privacy);
        $this->assertNotSame('⚔', $meta->badge);
    }

    /**
     * Test that a protected group saved earlier with an empty hash cannot be saved blank again.
     */
    public function test_execute_rejects_blank_password_when_stored_hash_is_empty(): void {
        global $DB;

        $DB->set_field('playergroup_meta', 'privacy', 1, ['groupid' => $this->groupid]);
        $DB->set_field('playergroup_meta', 'password', '', ['groupid' => $this->groupid]);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('errpasswordrequired', 'mod_playergroup'));
        edit_group::execute($this->cm->cmid, $this->groupid, 'Renamed', '', '🛡', 1, '');
    }

    /**
     * Test that a whitespace-only new password is refused.
     */
    public function test_execute_rejects_whitespace_only_password(): void {
        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('errpasswordrequired', 'mod_playergroup'));
        edit_group::execute($this->cm->cmid, $this->groupid, 'Renamed', '', '🛡', 1, '   ');
    }

    /**
     * Test that a privacy level outside 0, 1 and 2 is refused.
     */
    public function test_execute_rejects_invalid_privacy_level(): void {
        foreach ([-1, 3] as $privacy) {
            try {
                edit_group::execute($this->cm->cmid, $this->groupid, 'Renamed', '', '🛡', $privacy, '');
                $this->fail("Privacy level $privacy must be rejected.");
            } catch (moodle_exception $e) {
                $this->assertSame('errinvalidprivacy', $e->errorcode);
            }
        }
    }

    /**
     * Test that a groupid belonging to a different activity instance is rejected, proving the
     * lookup is scoped by playergroupid rather than trusting the raw groupid alone.
     */
    public function test_execute_rejects_groupid_from_another_instance(): void {
        $othercm = $this->getDataGenerator()->create_module('playergroup', ['course' => $this->course->id]);

        $this->expectException(\dml_missing_record_exception::class);
        edit_group::execute($othercm->cmid, $this->groupid, 'Cross Instance', '', '🛡', 0, '');
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
     * Test that the right to create groups is checked on the server, and the group is left untouched.
     *
     * A leader whose right was taken away after creating the group must not keep editing it.
     */
    public function test_execute_requires_the_creategroup_capability(): void {
        global $DB;

        $this->prohibit_for_students('mod/playergroup:creategroup');

        try {
            edit_group::execute($this->cm->cmid, $this->groupid, 'Renamed', '', '⚔', 0, '');
            $this->fail('Editing needs the creategroup capability.');
        } catch (\required_capability_exception $e) {
            $this->assertSame('Original Name', $DB->get_field('groups', 'name', ['id' => $this->groupid]));
        }
    }

    /**
     * Test that a group that does not exist is refused.
     */
    public function test_execute_refuses_an_unknown_group(): void {
        $this->expectException(\dml_missing_record_exception::class);
        edit_group::execute($this->cm->cmid, 999999, 'Renamed', '', '⚔', 0, '');
    }

    /**
     * Test that the activity window is enforced after the close date, and nothing is written.
     */
    public function test_execute_refused_after_close_date(): void {
        global $DB;

        $DB->set_field('playergroup', 'timeclose', time() - HOURSECS, ['id' => $this->cm->id]);

        try {
            edit_group::execute($this->cm->cmid, $this->groupid, 'Renamed', '', '⚔', 0, '');
            $this->fail('A group cannot be edited after the activity closed.');
        } catch (moodle_exception $e) {
            $this->assertSame('activityclosed', $e->errorcode);
        }
        $this->assertSame('Original Name', $DB->get_field('groups', 'name', ['id' => $this->groupid]));
    }

    /**
     * Test that the activity window is enforced before the activity opens.
     */
    public function test_execute_refused_before_open_date(): void {
        global $DB;

        $DB->set_field('playergroup', 'timeopen', time() + HOURSECS, ['id' => $this->cm->id]);

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('activitynotopen', 'mod_playergroup'));
        edit_group::execute($this->cm->cmid, $this->groupid, 'Renamed', '', '⚔', 0, '');
    }

    /**
     * Test that someone outside the course cannot use the service.
     */
    public function test_execute_refuses_a_user_not_enrolled_in_the_course(): void {
        $this->setUser($this->getDataGenerator()->create_user());

        $this->expectException(\require_login_exception::class);
        edit_group::execute($this->cm->cmid, $this->groupid, 'Renamed', '', '⚔', 0, '');
    }

    /**
     * Test that the answer has the shape the service declares.
     */
    public function test_execute_returns_the_declared_structure(): void {
        $result = edit_group::execute($this->cm->cmid, $this->groupid, 'Renamed', '', '⚔', 0, '');
        $clean = \core_external\external_api::clean_returnvalue(edit_group::execute_returns(), $result);

        $this->assertTrue($clean['success']);
        $this->assertNotSame('', $clean['message']);
    }

    /**
     * Test that an edited description is kept as typed, with its line breaks and symbols.
     */
    public function test_the_edited_description_is_kept_as_typed(): void {
        global $DB;

        $typed = "Q&A > notes\nSecond line";
        edit_group::execute($this->cm->cmid, $this->groupid, 'Name', $typed, '⚔', 0, '');

        $group = $DB->get_record('groups', ['id' => $this->groupid], '*', MUST_EXIST);
        $this->assertSame($typed, $group->description);
        $this->assertEquals(FORMAT_PLAIN, $group->descriptionformat);
    }

    /**
     * Test that a former leader who is no longer in the group cannot change it.
     *
     * With empty groups kept, the leader recorded for the group stays when the last member leaves.
     */
    public function test_a_former_leader_outside_the_group_cannot_edit_it(): void {
        global $DB;

        $DB->set_field('playergroup', 'deleteemptygroups', 0, ['id' => $this->cm->id]);
        groups_remove_member($this->groupid, $this->creator->id);
        $this->assertEquals($this->creator->id, $DB->get_field('playergroup_meta', 'creatorid', ['groupid' => $this->groupid]));

        $this->expectException(moodle_exception::class);
        edit_group::execute($this->cm->cmid, $this->groupid, 'Taken', '', '⚔', 2, '');
    }

    /**
     * Test that the refused edit changes nothing.
     */
    public function test_a_refused_edit_by_a_former_leader_leaves_the_group_untouched(): void {
        global $DB;

        $DB->set_field('playergroup', 'deleteemptygroups', 0, ['id' => $this->cm->id]);
        groups_remove_member($this->groupid, $this->creator->id);

        try {
            edit_group::execute($this->cm->cmid, $this->groupid, 'Taken', '', '⚔', 2, '');
            $this->fail('The edit should have been refused');
        } catch (moodle_exception $e) {
            $this->assertSame('Original Name', $DB->get_field('groups', 'name', ['id' => $this->groupid]));
            $this->assertEquals(0, $DB->get_field('playergroup_meta', 'privacy', ['groupid' => $this->groupid]));
        }
    }

    /**
     * Provides names the group cannot have.
     *
     * @return array[] The submitted name and a label.
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
     * Test that an empty name, or one longer than the column, is refused with a message and changes nothing.
     *
     * @dataProvider invalid_names
     * @param string $name The submitted name.
     */
    public function test_an_invalid_name_is_refused(string $name): void {
        global $DB;

        try {
            edit_group::execute($this->cm->cmid, $this->groupid, $name, '', '⚔', 0, '');
            $this->fail('The name should have been refused');
        } catch (moodle_exception $e) {
            $this->assertSame('errinvalidgroupname', $e->errorcode);
        }
        $this->assertSame('Original Name', $DB->get_field('groups', 'name', ['id' => $this->groupid]));
    }

    /**
     * Test that the longest name the column holds is accepted, and spaces around it are dropped.
     */
    public function test_the_longest_name_is_accepted(): void {
        global $DB;

        $name = str_repeat('é', 254);
        edit_group::execute($this->cm->cmid, $this->groupid, "  $name  ", '', '⚔', 0, '');

        $this->assertSame($name, $DB->get_field('groups', 'name', ['id' => $this->groupid]));
    }

    /**
     * Test that a badge longer than its column is refused.
     */
    public function test_a_badge_longer_than_the_column_is_refused(): void {
        try {
            edit_group::execute($this->cm->cmid, $this->groupid, 'Name', '', str_repeat('x', 256), 0, '');
            $this->fail('The badge should have been refused');
        } catch (moodle_exception $e) {
            $this->assertSame('errinvalidbadge', $e->errorcode);
        }
    }

    /**
     * Test that a group cannot be closed by someone who can no longer invite.
     */
    public function test_a_group_cannot_be_closed_without_the_right_to_invite(): void {
        global $DB;

        $this->prohibit_for_students('mod/playergroup:manageinvites');

        try {
            edit_group::execute($this->cm->cmid, $this->groupid, 'Original Name', '', '⚔', 2, '');
            $this->fail('The group should not have been closed');
        } catch (moodle_exception $e) {
            $this->assertSame('errcannotclose', $e->errorcode);
        }
        $this->assertEquals(0, $DB->get_field('playergroup_meta', 'privacy', ['groupid' => $this->groupid]));
    }

    /**
     * Test that a group that is already closed can still be renamed after the right to invite is gone.
     */
    public function test_a_closed_group_can_still_be_edited_without_the_right_to_invite(): void {
        global $DB;

        edit_group::execute($this->cm->cmid, $this->groupid, 'Original Name', '', '⚔', 2, '');
        $this->prohibit_for_students('mod/playergroup:manageinvites');

        $this->assertTrue(edit_group::execute($this->cm->cmid, $this->groupid, 'Renamed', '', '⚔', 2, '')['success']);
        $this->assertEquals(2, $DB->get_field('playergroup_meta', 'privacy', ['groupid' => $this->groupid]));
    }
}
