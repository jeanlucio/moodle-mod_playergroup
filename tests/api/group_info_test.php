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
 * Unit tests for the group_info public API.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\api;

use advanced_testcase;
use mod_playergroup\external\create_group;

/**
 * Tests for \mod_playergroup\api\group_info.
 *
 * @covers \mod_playergroup\api\group_info
 */
final class group_info_test extends advanced_testcase {
    /**
     * Test that a user with no group in the course gets null.
     */
    public function test_get_player_group_in_course_returns_null_without_group(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $this->assertNull(group_info::get_player_group_in_course($course->id, $user->id));
    }

    /**
     * Test that a user's group summary is returned with the expected fields.
     */
    public function test_get_player_group_in_course_returns_summary(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course'     => $course->id,
            'maxmembers' => 5,
        ]);

        $creator = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($creator->id, $course->id, 'student');
        $this->setUser($creator);
        $created = create_group::execute($cm->cmid, 'API Group', '', '⚔', 0, '');

        $info = group_info::get_player_group_in_course($course->id, $creator->id);

        $this->assertNotNull($info);
        $this->assertEquals((int) $created['groupid'], $info->groupid);
        $this->assertEquals('API Group', $info->groupname);
        $this->assertEquals('⚔', $info->badge);
        $this->assertEquals(1, $info->membercount);
        $this->assertEquals(5, $info->maxmembers);
        $this->assertEquals(fullname($creator), $info->leadername);
    }

    /**
     * Test that a group created with no badge falls back to the default shield emoji.
     */
    public function test_get_player_group_in_course_default_badge_when_empty(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);

        $creator = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($creator->id, $course->id, 'student');
        $this->setUser($creator);
        create_group::execute($cm->cmid, 'No Badge Group', '', '', 0, '');

        $info = group_info::get_player_group_in_course($course->id, $creator->id);

        $this->assertEquals('🛡️', $info->badge);
    }

    /**
     * Test that an empty groupids array short-circuits to an empty result.
     */
    public function test_get_badges_for_groups_returns_empty_for_empty_input(): void {
        $this->assertSame([], group_info::get_badges_for_groups([]));
    }

    /**
     * Test that badges are bulk-returned keyed by group ID.
     */
    public function test_get_badges_for_groups_returns_bulk_map(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm1 = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $cm2 = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);

        $user1 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
        $this->setUser($user1);
        $group1 = create_group::execute($cm1->cmid, 'Dragons', '', '🐉', 0, '');

        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');
        $this->setUser($user2);
        $group2 = create_group::execute($cm2->cmid, 'Wolves', '', '🐺', 0, '');

        $badges = group_info::get_badges_for_groups([$group1['groupid'], $group2['groupid']]);

        $this->assertSame([
            (int) $group1['groupid'] => '🐉',
            (int) $group2['groupid'] => '🐺',
        ], $badges);
    }

    /**
     * Test that a native group with no playergroup_meta row is omitted from the result,
     * rather than causing an error or a false default entry.
     */
    public function test_get_badges_for_groups_omits_groups_without_meta(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $plaingroup = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        $badges = group_info::get_badges_for_groups([$plaingroup->id]);

        $this->assertArrayNotHasKey((int) $plaingroup->id, $badges);
    }
}
