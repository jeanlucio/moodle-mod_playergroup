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
 * External function to accept a group invitation.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Class accept_invite
 * Accepts a pending group invitation, adds the student to the group, and declines all other pending invites.
 */
class accept_invite extends external_api {
    /**
     * Defines the parameters the AJAX call must send.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'inviteid' => new external_value(PARAM_INT, 'ID of the invitation to accept'),
        ]);
    }

    /**
     * Accepts the invitation: adds the user to the group and declines other pending invites.
     *
     * @param int $inviteid Invitation ID.
     * @return array Result with success flag and feedback message.
     * @throws \moodle_exception
     */
    public static function execute(int $inviteid): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->dirroot . '/mod/playergroup/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), ['inviteid' => $inviteid]);

        $invite = $DB->get_record('playergroup_invites', ['id' => $params['inviteid']], '*', MUST_EXIST);

        if ((int) $invite->receiverid !== (int) $USER->id) {
            throw new \moodle_exception('nopermissions', 'error', '', 'accept invite');
        }
        if ((int) $invite->status !== 0) {
            throw new \moodle_exception('invitealreadyhandled', 'mod_playergroup');
        }

        $playergroup = $DB->get_record('playergroup', ['id' => $invite->playergroupid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance(
            'playergroup',
            $playergroup->id,
            $playergroup->course,
            false,
            MUST_EXIST
        );

        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/playergroup:view', $context);

        $now = time();
        if ($playergroup->timeopen > 0 && $now < $playergroup->timeopen) {
            throw new \moodle_exception('activitynotopen', 'mod_playergroup');
        }
        if ($playergroup->timeclose > 0 && $now > $playergroup->timeclose) {
            throw new \moodle_exception('activityclosed', 'mod_playergroup');
        }

        $lock = \mod_playergroup\local\group_lock::acquire($playergroup->id);
        try {
            $hasusergroupsql = "SELECT gm.groupid
                                  FROM {groups_members} gm
                                  JOIN {playergroup_meta} pm ON pm.groupid = gm.groupid
                                 WHERE pm.playergroupid = :playergroupid AND gm.userid = :userid";

            $hasgroupparams = ['playergroupid' => $playergroup->id, 'userid' => $USER->id];
            if ($DB->record_exists_sql($hasusergroupsql, $hasgroupparams)) {
                throw new \moodle_exception('alreadyingroup', 'mod_playergroup');
            }

            $membercount = $DB->count_records('groups_members', ['groupid' => (int) $invite->groupid]);
            if ($membercount >= (int) $playergroup->maxmembers) {
                throw new \moodle_exception('groupisfull', 'mod_playergroup');
            }

            if (!groups_add_member((int) $invite->groupid, $USER->id)) {
                throw new \moodle_exception('joinfailed', 'mod_playergroup');
            }
        } finally {
            $lock->release();
        }

        $now = time();
        $DB->set_field('playergroup_invites', 'status', 1, ['id' => $invite->id]);
        $DB->set_field('playergroup_invites', 'timemodified', $now, ['id' => $invite->id]);

        // Decline all other pending invites for this user in this activity.
        $otherselect = 'receiverid = :receiverid AND playergroupid = :playergroupid AND status = 0 AND id <> :inviteid';
        $otherparams = [
            'receiverid'    => (int) $USER->id,
            'playergroupid' => (int) $playergroup->id,
            'inviteid'      => (int) $invite->id,
        ];
        $DB->set_field_select('playergroup_invites', 'timemodified', $now, $otherselect, $otherparams);
        $DB->set_field_select('playergroup_invites', 'status', 2, $otherselect, $otherparams);

        \mod_playergroup\event\invite_accepted::create([
            'context'  => $context,
            'objectid' => (int) $invite->id,
        ])->trigger();

        \mod_playergroup\event\member_joined::create([
            'context'  => $context,
            'objectid' => (int) $invite->groupid,
        ])->trigger();

        $modinfo = get_fast_modinfo($cm->course);
        $cminfo = $modinfo->get_cm($cm->id);
        $completion = new \completion_info($modinfo->get_course());
        if ($completion->is_enabled($cminfo) == COMPLETION_TRACKING_AUTOMATIC) {
            $completion->update_state($cminfo, COMPLETION_COMPLETE, $USER->id);
        }

        playergroup_update_grades($playergroup, $USER->id);

        return [
            'success' => true,
            'message' => get_string('groupjoined', 'mod_playergroup'),
        ];
    }

    /**
     * Defines the return structure for the AJAX call.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'True if successful'),
            'message' => new external_value(PARAM_TEXT, 'Feedback message'),
        ]);
    }
}
