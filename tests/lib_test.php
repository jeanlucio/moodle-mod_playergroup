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
 * Unit tests for mod_playergroup lib.php functions.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup;

use advanced_testcase;

/**
 * PHPUnit test case for playergroup lib functions.
 *
 * @covers ::playergroup_add_instance
 * @covers ::playergroup_delete_instance
 */
final class lib_test extends advanced_testcase {
    /**
     * Reset the Moodle environment between tests.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test that playergroup_add_instance inserts a record and returns a valid ID.
     */
    public function test_add_instance_returns_id(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();

        $playergroup = (object) [
            'course'              => $course->id,
            'name'                => 'Test Activity',
            'intro'               => '',
            'introformat'         => FORMAT_HTML,
            'groupingmode'        => 'none',
            'minmembers'          => 2,
            'maxmembers'          => 5,
            'canleave'            => 1,
            'deletegroups'        => 0,
            'grade'               => 0,
            'completionjoingroup' => 0,
            'timeopen'            => 0,
            'timeclose'           => 0,
        ];

        $id = playergroup_add_instance($playergroup);

        $this->assertGreaterThan(0, $id);
        $this->assertTrue($DB->record_exists('playergroup', ['id' => $id]));
    }

    /**
     * Test that add_instance stores timemodified.
     */
    public function test_add_instance_stores_timemodified(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $before = time();

        $playergroup = (object) [
            'course'              => $course->id,
            'name'                => 'Time Test',
            'intro'               => '',
            'introformat'         => FORMAT_HTML,
            'groupingmode'        => 'none',
            'minmembers'          => 2,
            'maxmembers'          => 5,
            'canleave'            => 1,
            'deletegroups'        => 0,
            'grade'               => 0,
            'completionjoingroup' => 0,
            'timeopen'            => 0,
            'timeclose'           => 0,
        ];

        $id = playergroup_add_instance($playergroup);
        $record = $DB->get_record('playergroup', ['id' => $id]);

        $this->assertGreaterThanOrEqual($before, $record->timemodified);
    }

    /**
     * Helper: create a minimal playergroup activity via generator.
     *
     * @param \stdClass $course
     * @return \stdClass cm record
     */
    private function create_activity(\stdClass $course): \stdClass {
        return $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
    }

    /**
     * Test that delete_instance removes the record and associated data.
     */
    public function test_delete_instance_removes_record(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->create_activity($course);

        $this->assertTrue(playergroup_delete_instance($cm->id));
        $this->assertFalse($DB->record_exists('playergroup', ['id' => $cm->id]));
    }

    /**
     * Test that delete_instance cleans playergroup_meta rows.
     */
    public function test_delete_instance_removes_meta(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->create_activity($course);

        // Insert a fake meta row.
        $DB->insert_record('playergroup_meta', (object) [
            'playergroupid' => $cm->id,
            'groupid'       => 0,
            'creatorid'     => 2,
            'badge'         => '⚔',
            'privacy'       => 0,
            'password'      => '',
            'timecreated'   => time(),
            'timemodified'  => time(),
        ]);

        playergroup_delete_instance($cm->id);

        $this->assertFalse($DB->record_exists('playergroup_meta', ['playergroupid' => $cm->id]));
    }

    /**
     * Test that delete_instance cleans playergroup_invites rows.
     */
    public function test_delete_instance_removes_invites(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->create_activity($course);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $DB->insert_record('playergroup_invites', (object) [
            'playergroupid' => $cm->id,
            'groupid'       => 0,
            'senderid'      => $user1->id,
            'receiverid'    => $user2->id,
            'status'        => 0,
            'timecreated'   => time(),
            'timemodified'  => time(),
        ]);

        playergroup_delete_instance($cm->id);

        $this->assertFalse($DB->record_exists('playergroup_invites', ['playergroupid' => $cm->id]));
    }

    /**
     * Test that delete_instance returns false for a non-existent ID.
     */
    public function test_delete_instance_returns_false_for_missing_id(): void {
        $this->assertFalse(playergroup_delete_instance(999999));
    }

    /**
     * Test that playergroup_supports returns expected values.
     */
    public function test_supports_known_features(): void {
        $this->assertTrue(playergroup_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(playergroup_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertTrue(playergroup_supports(FEATURE_COMPLETION_HAS_RULES));
        $this->assertTrue(playergroup_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertNull(playergroup_supports('unknown_feature'));
    }
}
