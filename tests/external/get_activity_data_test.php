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
 * Unit tests for the get_activity_data external function.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\external;

use advanced_testcase;
use core_external\external_api;

/**
 * Tests for \mod_playergroup\external\get_activity_data.
 *
 * @covers \mod_playergroup\external\get_activity_data
 */
final class get_activity_data_test extends advanced_testcase {
    /**
     * Test that each returned group carries its member list, with the creator flagged as
     * leader, and that the response validates against execute_returns() — this is what the
     * mobile app actually consumes, so a PARAM type mismatch here would break it silently.
     */
    public function test_execute_includes_group_members(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);

        $creator = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($creator->id, $course->id, 'student');
        $this->setUser($creator);
        $created = create_group::execute($cm->cmid, 'Test Group', '', '🛡', 0, '');

        $joiner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($joiner->id, $course->id, 'student');
        $this->setUser($joiner);
        join_group::execute($cm->cmid, $created['groupid'], '');

        $result = get_activity_data::execute($cm->cmid);
        $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), $result);

        $this->assertCount(1, $result['groups']);
        $members = $result['groups'][0]['members'];
        $this->assertCount(2, $members);

        $isleaderbyname = [];
        foreach ($members as $member) {
            $isleaderbyname[$member['fullname']] = $member['isleader'];
        }
        $this->assertTrue($isleaderbyname[fullname($creator)]);
        $this->assertFalse($isleaderbyname[fullname($joiner)]);
        $this->assertStringContainsString('Test Group', $result['groups'][0]['membersheading']);
    }
}
