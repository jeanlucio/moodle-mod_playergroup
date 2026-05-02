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
use moodle_exception;

/**
 * Tests for \mod_playergroup\external\create_group.
 *
 * @covers \mod_playergroup\external\create_group
 */
final class create_group_test extends advanced_testcase {
    /** @var \stdClass Course record. */
    private \stdClass $course;

    /** @var \stdClass Course module record (cm row). */
    private \stdClass $cm;

    /** @var \stdClass Student user with creategroup capability. */
    private \stdClass $student;

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
     * Test that creating a group with automatic completion marks the activity as complete.
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
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        create_group::execute($cm->cmid, 'Auto Group', '', '🛡', 0, '');

        $cminfo = get_fast_modinfo($course)->get_cm($cm->cmid);
        $completion = new \completion_info($course);
        $data = $completion->get_data($cminfo, false, $student->id);
        $this->assertEquals(COMPLETION_COMPLETE, (int) $data->completionstate);
    }
}
