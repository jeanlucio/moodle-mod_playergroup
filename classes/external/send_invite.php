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
 * External function to send a group invitation.
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
 * Class send_invite
 * Sends an invitation for a student to join the current user's group.
 */
class send_invite extends external_api {
    /**
     * Defines the parameters the AJAX call must send.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'       => new external_value(PARAM_INT, 'Course module ID'),
            'receiverid' => new external_value(PARAM_INT, 'User ID of the invite recipient'),
        ]);
    }

    /**
     * Sends a group invitation and dispatches a Moodle message notification.
     *
     * @param int $cmid Course module ID.
     * @param int $receiverid User ID of the recipient.
     * @return array Result with success flag and feedback message.
     * @throws \moodle_exception
     */
    public static function execute(int $cmid, int $receiverid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'       => $cmid,
            'receiverid' => $receiverid,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('mod/playergroup:manageinvites', $context);

        $cm = get_coursemodule_from_id('playergroup', $params['cmid'], 0, false, MUST_EXIST);
        $playergroup = $DB->get_record('playergroup', ['id' => $cm->instance], '*', MUST_EXIST);

        $coursecontext = \context_course::instance($cm->course);

        // Mirrors the check both UIs already apply before offering the invite picker: a
        // professor who hid the participant list from students has already decided students
        // should not browse course-mates by name, and this endpoint must honour that too.
        require_capability('moodle/course:viewparticipants', $coursecontext);

        if (!is_enrolled($coursecontext, $params['receiverid'], '', true)) {
            throw new \moodle_exception('usernotenrolled', 'mod_playergroup');
        }

        $hasusergroupsql = "SELECT gm.groupid
                              FROM {groups_members} gm
                              JOIN {playergroup_meta} pm ON pm.groupid = gm.groupid
                             WHERE pm.playergroupid = :playergroupid AND gm.userid = :userid";

        $sendergroupid = $DB->get_field_sql($hasusergroupsql, [
            'playergroupid' => $playergroup->id,
            'userid'        => $USER->id,
        ]);
        if (!$sendergroupid) {
            throw new \moodle_exception('notingroup', 'mod_playergroup');
        }

        $receiverhasgroup = $DB->record_exists_sql($hasusergroupsql, [
            'playergroupid' => $playergroup->id,
            'userid'        => $params['receiverid'],
        ]);
        if ($receiverhasgroup) {
            throw new \moodle_exception('alreadyingroup', 'mod_playergroup');
        }

        $membercount = $DB->count_records('groups_members', ['groupid' => (int) $sendergroupid]);
        if ($membercount >= (int) $playergroup->maxmembers) {
            throw new \moodle_exception('groupisfull', 'mod_playergroup');
        }

        $duplicateconditions = [
            'playergroupid' => $playergroup->id,
            'groupid'       => (int) $sendergroupid,
            'receiverid'    => $params['receiverid'],
            'status'        => 0,
        ];
        if ($DB->record_exists('playergroup_invites', $duplicateconditions)) {
            throw new \moodle_exception('invitealreadysent', 'mod_playergroup');
        }

        $now = time();
        $invite = (object) [
            'playergroupid' => $playergroup->id,
            'groupid'       => (int) $sendergroupid,
            'senderid'      => (int) $USER->id,
            'receiverid'    => (int) $params['receiverid'],
            'status'        => 0,
            'timecreated'   => $now,
            'timemodified'  => $now,
        ];
        $DB->insert_record('playergroup_invites', $invite);

        $receiver = \core_user::get_user($params['receiverid'], '*', MUST_EXIST);
        $group = $DB->get_record('groups', ['id' => (int) $sendergroupid], 'id, name', MUST_EXIST);
        $activityurl = new \moodle_url('/mod/playergroup/view.php', ['id' => $params['cmid']]);

        $messagedata = (object) [
            'sender' => fullname($USER),
            'group'  => format_string($group->name),
        ];

        $msg = new \core\message\message();
        $msg->component         = 'mod_playergroup';
        $msg->name              = 'invite';
        $msg->userfrom          = $USER;
        $msg->userto            = $receiver;
        $msg->subject           = get_string('invitepending', 'mod_playergroup');
        $msg->fullmessage       = get_string('invitemessage', 'mod_playergroup', $messagedata);
        $msg->fullmessageformat = FORMAT_PLAIN;
        $msg->fullmessagehtml   = '<p>' . get_string('invitemessage', 'mod_playergroup', $messagedata) . '</p>'
            . '<p><a href="' . $activityurl->out(false) . '">' . format_string($playergroup->name) . '</a></p>';
        $msg->smallmessage      = get_string('invitepending', 'mod_playergroup');
        $msg->notification      = 1;
        $msg->contexturl        = $activityurl->out(false);
        $msg->contexturlname    = format_string($playergroup->name);
        message_send($msg);

        return [
            'success' => true,
            'message' => get_string('invitesent', 'mod_playergroup'),
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
