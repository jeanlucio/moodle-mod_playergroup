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
 * Unit tests for the grade awarding logic in playergroup_update_grades().
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup;

use advanced_testcase;

/**
 * PHPUnit tests for playergroup_update_grades().
 *
 * Verifies that grades are permanently awarded at join/create time and are not
 * removed when a student leaves a group.
 *
 * @covers ::playergroup_update_grades
 */
final class playergroup_grade_test extends advanced_testcase {
    /**
     * Reset the Moodle environment between tests.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Create a playergroup activity and return its full DB record.
     *
     * @param \stdClass $course
     * @param float $grade Maximum grade (0 = disabled).
     * @return \stdClass The playergroup instance record.
     */
    private function create_graded_activity(\stdClass $course, float $grade = 10.0): \stdClass {
        global $DB;

        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'grade'  => $grade,
        ]);
        return $DB->get_record('playergroup', ['id' => $cm->id], '*', MUST_EXIST);
    }

    /**
     * Register a user as a member of a new group inside the given activity.
     *
     * @param \stdClass $playergroup The playergroup instance record.
     * @param \stdClass $user
     * @return int The group ID.
     */
    private function add_to_group(\stdClass $playergroup, \stdClass $user): int {
        global $DB;

        $group = $this->getDataGenerator()->create_group(['courseid' => $playergroup->course]);
        $DB->insert_record('groups_members', (object) [
            'groupid'   => $group->id,
            'userid'    => $user->id,
            'timeadded' => time(),
            'component' => '',
            'itemid'    => 0,
        ]);
        $DB->insert_record('playergroup_meta', (object) [
            'playergroupid' => $playergroup->id,
            'groupid'       => $group->id,
            'creatorid'     => $user->id,
            'badge'         => '⚔',
            'privacy'       => 0,
            'password'      => '',
            'timecreated'   => time(),
            'timemodified'  => time(),
        ]);
        return $group->id;
    }

    /**
     * Return the rawgrade stored in grade_grades for a given user and instance.
     *
     * @param \stdClass $playergroup
     * @param int $userid
     * @return float|null Null when no grade record exists.
     */
    private function get_rawgrade(\stdClass $playergroup, int $userid): ?float {
        global $DB;

        $sql = "SELECT gg.rawgrade
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gi.itemtype = 'mod'
                   AND gi.itemmodule = 'playergroup'
                   AND gi.iteminstance = :instanceid
                   AND gg.userid = :userid";

        $value = $DB->get_field_sql($sql, ['instanceid' => $playergroup->id, 'userid' => $userid]);
        return $value === false ? null : (float) $value;
    }

    /**
     * A member called with userid > 0 must receive the configured grade immediately.
     */
    public function test_awards_grade_to_member(): void {
        $course = $this->getDataGenerator()->create_course();
        $playergroup = $this->create_graded_activity($course, 10.0);
        $user = $this->getDataGenerator()->create_user();
        $this->add_to_group($playergroup, $user);

        playergroup_update_grades($playergroup, $user->id);

        $this->assertEquals(10.0, $this->get_rawgrade($playergroup, $user->id));
    }

    /**
     * Bulk recalculation (userid = 0) must award the grade to all current members.
     */
    public function test_bulk_awards_all_current_members(): void {
        $course = $this->getDataGenerator()->create_course();
        $playergroup = $this->create_graded_activity($course, 5.0);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->add_to_group($playergroup, $user1);
        $this->add_to_group($playergroup, $user2);

        playergroup_update_grades($playergroup);

        $this->assertEquals(5.0, $this->get_rawgrade($playergroup, $user1->id));
        $this->assertEquals(5.0, $this->get_rawgrade($playergroup, $user2->id));
    }

    /**
     * A grade earned at join time must remain after the student leaves the group.
     *
     * Bulk recalculation must not nullify grades for former members because
     * grade_update() only modifies entries present in the supplied array.
     */
    public function test_grade_persists_after_leaving(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $playergroup = $this->create_graded_activity($course, 10.0);
        $user = $this->getDataGenerator()->create_user();
        $groupid = $this->add_to_group($playergroup, $user);

        playergroup_update_grades($playergroup, $user->id);
        $this->assertEquals(10.0, $this->get_rawgrade($playergroup, $user->id));

        // Simulate leaving: remove from groups_members (leave_group does not touch grades).
        $DB->delete_records('groups_members', ['groupid' => $groupid, 'userid' => $user->id]);

        // Bulk recalculation must leave the former member's grade untouched.
        playergroup_update_grades($playergroup);
        $this->assertEquals(10.0, $this->get_rawgrade($playergroup, $user->id));
    }

    /**
     * When grade is disabled (grade = 0), no grade record must be written.
     */
    public function test_no_grade_when_disabled(): void {
        $course = $this->getDataGenerator()->create_course();
        $playergroup = $this->create_graded_activity($course, 0.0);
        $user = $this->getDataGenerator()->create_user();
        $this->add_to_group($playergroup, $user);

        playergroup_update_grades($playergroup, $user->id);

        $this->assertNull($this->get_rawgrade($playergroup, $user->id));
    }
}
