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
 * Serialises the group membership check-then-act sequence for one activity instance.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

/**
 * Keeps a group led by someone who is in it.
 */
final class leadership {
    /**
     * Makes a student who just joined the leader when the recorded leader is no longer in the group.
     *
     * A group kept after its last member left still names that member as its leader. Without this the
     * students who join later could never edit the group, and its card would keep naming someone who is
     * gone. Called by the observer of the member-added event, so it runs inside the group lock when the student
     * joins through the web services.
     *
     * @param int $groupid The native group ID.
     * @param int $userid The student who joined.
     * @return void
     */
    public static function claim_if_vacant(int $groupid, int $userid): void {
        global $DB;

        $creatorid = $DB->get_field('playergroup_meta', 'creatorid', ['groupid' => $groupid]);
        if ($creatorid === false || (int) $creatorid === $userid) {
            return;
        }

        if (!$DB->record_exists('groups_members', ['groupid' => $groupid, 'userid' => (int) $creatorid])) {
            $DB->set_field('playergroup_meta', 'creatorid', $userid, ['groupid' => $groupid]);
        }
    }
}
