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
     * Test that a groupid belonging to a different activity instance is rejected, proving the
     * lookup is scoped by playergroupid rather than trusting the raw groupid alone.
     */
    public function test_execute_rejects_groupid_from_another_instance(): void {
        $othercm = $this->getDataGenerator()->create_module('playergroup', ['course' => $this->course->id]);

        $this->expectException(\dml_missing_record_exception::class);
        edit_group::execute($othercm->cmid, $this->groupid, 'Cross Instance', '', '🛡', 0, '');
    }
}
