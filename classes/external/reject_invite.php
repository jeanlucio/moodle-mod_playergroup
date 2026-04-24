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
 * External function to reject a group invitation.
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
 * Class reject_invite
 * Declines a pending group invitation.
 */
class reject_invite extends external_api {
    /**
     * Defines the parameters the AJAX call must send.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'inviteid' => new external_value(PARAM_INT, 'ID of the invitation to decline'),
        ]);
    }

    /**
     * Declines the invitation by setting its status to 2.
     *
     * @param int $inviteid Invitation ID.
     * @return array Result with success flag and feedback message.
     * @throws \moodle_exception
     */
    public static function execute(int $inviteid): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['inviteid' => $inviteid]);

        $invite = $DB->get_record('playergroup_invites', ['id' => $params['inviteid']], '*', MUST_EXIST);

        if ((int) $invite->receiverid !== (int) $USER->id) {
            throw new \moodle_exception('nopermissions', 'error', '', 'reject invite');
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
        $DB->set_field('playergroup_invites', 'status', 2, ['id' => $invite->id]);
        $DB->set_field('playergroup_invites', 'timemodified', $now, ['id' => $invite->id]);

        return [
            'success' => true,
            'message' => get_string('inviterejected', 'mod_playergroup'),
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
