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
     * Deletes an empty playergroup-managed group, honouring the activity's own
     * "delete empty groups" setting, regardless of how the group became empty.
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
}
