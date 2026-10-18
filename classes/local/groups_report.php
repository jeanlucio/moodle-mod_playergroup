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
 * Rows of the groups-and-members report and of its export.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

/**
 * Builds the rows the teacher reads on the page and downloads, so both always list the same thing.
 */
final class groups_report {
    /**
     * Returns one row per member of each group, and one placeholder row for a group with no member.
     *
     * The activity can keep empty groups (when it does not delete them), and they still show to the students
     * and take new members. A report built only from members would leave them out, and say that no group
     * exists when every group is empty.
     *
     * @param iterable $groups Group records with name, privacy and creatorid.
     * @param array $membersbygroup User records (with userid and the name fields) keyed by group id.
     * @param \context|null $context Context for the name filters; defaults to the page context.
     * @return \Generator Rows of [group name, privacy label, role, member name], all plain text.
     */
    public static function rows(iterable $groups, array $membersbygroup, ?\context $context = null): \Generator {
        foreach ($groups as $g) {
            $groupname = text::plain($g->name, $context);
            $privacylabel = self::privacy_label((int) ($g->privacy ?? 0));
            $members = $membersbygroup[(int) $g->id] ?? [];

            if (empty($members)) {
                yield [$groupname, $privacylabel, '', get_string('groupnomembers', 'mod_playergroup')];
                continue;
            }

            foreach ($members as $member) {
                if ((int) $member->userid === (int) $g->creatorid) {
                    $role = get_string('leader', 'mod_playergroup');
                } else {
                    $role = get_string('member', 'mod_playergroup');
                }

                yield [$groupname, $privacylabel, $role, fullname($member)];
            }
        }
    }

    /**
     * Returns the label of a group privacy level.
     *
     * @param int $privacy The privacy level: 0 open, 1 protected by password, 2 closed to invitations.
     * @return string The localised label.
     */
    private static function privacy_label(int $privacy): string {
        if ($privacy === 1) {
            return get_string('groupprotected', 'mod_playergroup');
        }
        if ($privacy === 2) {
            return get_string('groupclosed', 'mod_playergroup');
        }

        return get_string('groupopen', 'mod_playergroup');
    }
}
