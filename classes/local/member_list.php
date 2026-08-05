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
 * Orders a group's member list for display: leader first, then alphabetical.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

/**
 * Class member_list
 *
 * Shared by view.php (web) and get_activity_data.php (mobile app) so the "view members" list
 * is ordered the same way in both places, instead of each view re-implementing its own sort.
 */
final class member_list {
    /**
     * Reorders a group's members with the leader first, then the rest alphabetically.
     *
     * Members otherwise arrive in join order (see the callers' SQL), which does not reflect
     * the current leader once leadership has been transferred to a member who joined later.
     *
     * @param array $members Array of ['fullname' => string, 'isleader' => bool, ...].
     * @return array The same entries, reordered.
     */
    public static function order(array $members): array {
        $leader = null;
        $rest = [];
        foreach ($members as $member) {
            if ($leader === null && !empty($member['isleader'])) {
                $leader = $member;
            } else {
                $rest[] = $member;
            }
        }

        \core_collator::asort_array_of_arrays_by_key($rest, 'fullname');
        $rest = array_values($rest);

        if ($leader !== null) {
            array_unshift($rest, $leader);
        }

        return $rest;
    }
}
