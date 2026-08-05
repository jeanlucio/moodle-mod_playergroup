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
 * Unit tests for the member_list ordering helper.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

use advanced_testcase;

/**
 * Tests for \mod_playergroup\local\member_list.
 *
 * @covers \mod_playergroup\local\member_list
 */
final class member_list_test extends advanced_testcase {
    /**
     * Test that the leader is placed first regardless of their position in the input.
     */
    public function test_order_places_leader_first(): void {
        $members = [
            ['fullname' => 'Beatriz Leite', 'isleader' => false],
            ['fullname' => 'Yasmin Cavalcanti', 'isleader' => true],
            ['fullname' => 'Pedro Monteiro', 'isleader' => false],
        ];

        $ordered = member_list::order($members);

        $this->assertSame('Yasmin Cavalcanti', $ordered[0]['fullname']);
        $this->assertTrue($ordered[0]['isleader']);
    }

    /**
     * Test that everyone other than the leader is sorted alphabetically.
     */
    public function test_order_sorts_non_leaders_alphabetically(): void {
        $members = [
            ['fullname' => 'Rafael Araujo', 'isleader' => false],
            ['fullname' => 'Yasmin Cavalcanti', 'isleader' => true],
            ['fullname' => 'Alexandre Pinto', 'isleader' => false],
            ['fullname' => 'Beatriz Leite', 'isleader' => false],
            ['fullname' => 'Pedro Monteiro', 'isleader' => false],
        ];

        $ordered = member_list::order($members);

        $this->assertSame([
            'Yasmin Cavalcanti',
            'Alexandre Pinto',
            'Beatriz Leite',
            'Pedro Monteiro',
            'Rafael Araujo',
        ], array_column($ordered, 'fullname'));
    }

    /**
     * Test that a member list with no leader (defensive edge case) is still sorted
     * alphabetically without throwing.
     */
    public function test_order_with_no_leader_sorts_everyone_alphabetically(): void {
        $members = [
            ['fullname' => 'Rafael Araujo', 'isleader' => false],
            ['fullname' => 'Alexandre Pinto', 'isleader' => false],
        ];

        $ordered = member_list::order($members);

        $this->assertSame(['Alexandre Pinto', 'Rafael Araujo'], array_column($ordered, 'fullname'));
    }

    /**
     * Test that an empty member list returns an empty array.
     */
    public function test_order_with_empty_list_returns_empty_array(): void {
        $this->assertSame([], member_list::order([]));
    }
}
