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
 * Event observer for mod_playergroup.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup;

/**
 * Reacts to core group-membership changes so "delete empty groups" works no matter how the
 * group was emptied, not only through this plugin's own leave-group web service.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Keeps a playergroup-managed group coherent when a member is removed, whichever way it happened:
     * hands the leadership on when the leader left, and deletes the group when it is now empty and the
     * activity's own "delete empty groups" setting asks for it.
     *
     * groups_remove_member() fires this event on every removal path core has: this plugin's
     * own leave_group web service, a teacher removing a member from the course's native
     * Groups admin page, and enrolment removal (unenrol_user()/groups_delete_group_members()
     * both call groups_remove_member() per row). A whole-group delete (groups_delete_group(),
     * used by this activity's own instance_delete()) bulk-deletes groups_members directly and
     * never fires this event, so this observer never races that path.
     *
     * @param \core\event\group_member_removed $event The core event.
     * @return void
     */
    public static function group_member_removed(\core\event\group_member_removed $event): void {
        global $CFG, $DB;

        $groupid = (int) $event->objectid;

        $meta = $DB->get_record('playergroup_meta', ['groupid' => $groupid]);
        if (!$meta) {
            // Not a group this plugin manages.
            return;
        }

        // The group's powers (editing it, its password) belong to the leader, so a group whose leader
        // was removed would stay without anyone able to manage it. This runs before the empty-group
        // check on purpose: it applies whether or not empty groups are deleted.
        if ((int) $event->relateduserid === (int) $meta->creatorid) {
            self::hand_over_leadership($groupid);
        }

        $playergroup = $DB->get_record('playergroup', ['id' => $meta->playergroupid]);
        if (!$playergroup || empty($playergroup->deleteemptygroups)) {
            return;
        }

        if ($DB->record_exists('groups_members', ['groupid' => $groupid])) {
            // Still has members.
            return;
        }

        $cm = get_coursemodule_from_instance('playergroup', $playergroup->id, $playergroup->course);
        if (!$cm) {
            // The activity itself is being torn down concurrently; its own delete_instance()
            // already cleans up every playergroup_meta/playergroup_invites row it owns.
            return;
        }

        require_once($CFG->dirroot . '/group/lib.php');

        // Remove plugin data before deleting the native group.
        $DB->delete_records('playergroup_invites', ['groupid' => $groupid]);
        $DB->delete_records('playergroup_meta', ['groupid' => $groupid]);
        groups_delete_group($groupid);

        \mod_playergroup\event\group_deleted::create([
            'context'  => \context_module::instance($cm->id),
            'objectid' => $groupid,
        ])->trigger();
    }

    /**
     * Forgets a deleted group: removes its metadata and the invites that pointed at it.
     *
     * Groups disappear in ways this plugin does not control: a teacher deleting one on the native
     * Groups page, or a course reset removing all of them. Without this the metadata kept a groupid
     * that no longer exists. What students earned by being in the group is kept: it belongs to them.
     *
     * @param \core\event\group_deleted $event The core event.
     * @return void
     */
    public static function group_deleted(\core\event\group_deleted $event): void {
        global $DB;

        $groupid = (int) $event->objectid;
        $DB->delete_records('playergroup_invites', ['groupid' => $groupid]);
        $DB->delete_records('playergroup_meta', ['groupid' => $groupid]);
    }

    /**
     * Makes the oldest remaining member the leader of the group.
     *
     * Does nothing when nobody is left: the group then has no leader to speak of, and is either
     * deleted or kept empty by the caller.
     *
     * @param int $groupid The native group ID.
     * @return void
     */
    private static function hand_over_leadership(int $groupid): void {
        global $DB;

        $members = $DB->get_records('groups_members', ['groupid' => $groupid], 'timeadded ASC, id ASC', 'id, userid', 0, 1);
        if (empty($members)) {
            return;
        }

        $DB->set_field('playergroup_meta', 'creatorid', (int) reset($members)->userid, ['groupid' => $groupid]);
    }
}
