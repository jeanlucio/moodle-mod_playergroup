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
 * Unit tests for the groups-and-members export controller.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\controller;

use advanced_testcase;
use mod_playergroup\external\create_group;
use mod_playergroup\external\join_group;

/**
 * Tests for \mod_playergroup\controller\export_groups.
 *
 * @covers \mod_playergroup\controller\export_groups
 */
final class export_groups_test extends advanced_testcase {
    /**
     * Test that the CSV export contains one row per member, with the creator marked as leader.
     */
    public function test_execute_streams_csv_with_one_row_per_member(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);

        $creator = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($creator->id, $course->id, 'student');
        $this->setUser($creator);
        $created = create_group::execute($cm->cmid, 'Export Group', '', '🛡', 1, 'secret123');

        $joiner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($joiner->id, $course->id, 'student');
        $this->setUser($joiner);
        join_group::execute($cm->cmid, $created['groupid'], 'secret123');

        $controller = new export_groups();
        ob_start();
        $controller->execute($cm->id, 'csv', $course->shortname);
        $csv = ob_get_clean();

        $this->assertStringContainsString('Export Group', $csv);
        $this->assertStringContainsString(get_string('groupprotected', 'mod_playergroup'), $csv);
        $this->assertStringContainsString(get_string('leader', 'mod_playergroup'), $csv);
        $this->assertStringContainsString(get_string('member', 'mod_playergroup'), $csv);
        $this->assertStringContainsString(fullname($creator), $csv);
        $this->assertStringContainsString(fullname($joiner), $csv);
    }

    /**
     * Test that a closed (invite-only) group is labelled correctly in the export.
     */
    public function test_execute_streams_csv_with_closed_group_privacy_label(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);

        $creator = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($creator->id, $course->id, 'student');
        $this->setUser($creator);
        create_group::execute($cm->cmid, 'Closed Group', '', '🔒', 2, '');

        $controller = new export_groups();
        ob_start();
        $controller->execute($cm->id, 'csv', $course->shortname);
        $csv = ob_get_clean();

        $this->assertStringContainsString(get_string('groupclosed', 'mod_playergroup'), $csv);
    }

    /**
     * Test that an activity with no groups produces just the header row.
     */
    public function test_execute_streams_csv_with_no_groups(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);

        $controller = new export_groups();
        ob_start();
        $controller->execute($cm->id, 'csv', $course->shortname);
        $csv = ob_get_clean();

        $this->assertStringContainsString(get_string('report_group', 'mod_playergroup'), $csv);
        $this->assertStringNotContainsString(get_string('leader', 'mod_playergroup'), $csv);
    }
}
