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
 * Unit tests for the rows of the groups-and-members report.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

use advanced_testcase;

/**
 * Tests for \mod_playergroup\local\groups_report.
 *
 * @covers \mod_playergroup\local\groups_report
 */
final class groups_report_test extends advanced_testcase {
    /**
     * Builds a group record the way the report queries return it.
     *
     * @param int $id Group id.
     * @param string $name Group name.
     * @param int $privacy Privacy level.
     * @param int $creatorid Id of the leader.
     * @return \stdClass The record.
     */
    private function group(int $id, string $name, int $privacy, int $creatorid): \stdClass {
        return (object) ['id' => $id, 'name' => $name, 'privacy' => $privacy, 'creatorid' => $creatorid];
    }

    /**
     * Test that each member gets a row, with the leader marked and the privacy of the group spelled out.
     */
    public function test_rows_lists_each_member_with_role_and_privacy(): void {
        $this->resetAfterTest();
        $leader = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $leader->userid = $leader->id;
        $other->userid = $other->id;

        $rows = iterator_to_array(
            groups_report::rows([$this->group(5, 'Alpha', 1, $leader->id)], [5 => [$leader, $other]]),
            false
        );

        $this->assertSame([
            ['Alpha', get_string('groupprotected', 'mod_playergroup'), get_string('leader', 'mod_playergroup'),
                fullname($leader)],
            ['Alpha', get_string('groupprotected', 'mod_playergroup'), get_string('member', 'mod_playergroup'),
                fullname($other)],
        ], $rows);
    }

    /**
     * Test that a group with no member still has a row, so the report does not hide it.
     */
    public function test_rows_keeps_a_group_without_members(): void {
        $this->resetAfterTest();

        $rows = iterator_to_array(groups_report::rows([$this->group(7, 'Empty', 0, 0)], []), false);

        $this->assertSame([
            ['Empty', get_string('groupopen', 'mod_playergroup'), '', get_string('groupnomembers', 'mod_playergroup')],
        ], $rows);
    }

    /**
     * Test that the groups with members and the empty ones come out together, in the order given.
     */
    public function test_rows_mixes_empty_and_filled_groups_in_order(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $user->userid = $user->id;
        $groups = [$this->group(1, 'First', 2, 0), $this->group(2, 'Second', 0, $user->id)];

        $rows = iterator_to_array(groups_report::rows($groups, [2 => [$user]]), false);

        $this->assertCount(2, $rows);
        $this->assertSame(['First', get_string('groupclosed', 'mod_playergroup')], array_slice($rows[0], 0, 2));
        $this->assertSame('', $rows[0][2]);
        $this->assertSame(['Second', get_string('groupopen', 'mod_playergroup')], array_slice($rows[1], 0, 2));
        $this->assertSame(get_string('leader', 'mod_playergroup'), $rows[1][2]);
    }

    /**
     * Test that an activity with no group has no row at all.
     */
    public function test_rows_is_empty_without_groups(): void {
        $this->assertSame([], iterator_to_array(groups_report::rows([], []), false));
    }

    /**
     * Test that the group name comes out as plain text, as typed.
     */
    public function test_rows_writes_the_group_name_as_plain_text(): void {
        $this->resetAfterTest();

        $rows = iterator_to_array(groups_report::rows([$this->group(3, 'P&D', 0, 0)], []), false);

        $this->assertSame('P&D', $rows[0][0]);
    }
}
