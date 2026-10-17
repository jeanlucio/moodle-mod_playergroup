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
 * External function to edit an existing group.
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
 * External API endpoint for the group creator to edit their group's details.
 */
class edit_group extends external_api {
    /**
     * Defines the parameters the AJAX call must send.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'        => new external_value(PARAM_INT, 'Course module ID'),
            'groupid'     => new external_value(PARAM_INT, 'ID of the group to edit'),
            'name'        => new external_value(PARAM_TEXT, 'New group name'),
            'description' => new external_value(PARAM_RAW, 'New group description'),
            'badge'       => new external_value(PARAM_TEXT, 'New emoji or badge string'),
            'privacy'  => new external_value(
                PARAM_INT,
                'Privacy level: 0=open, 1=protected, 2=closed',
                VALUE_DEFAULT,
                0
            ),
            'password' => new external_value(
                PARAM_RAW,
                'New password (plain-text); empty string keeps the existing one',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Executes the group update.
     *
     * @param int $cmid Course module ID.
     * @param int $groupid ID of the group to edit.
     * @param string $name New group name.
     * @param string $description New group description.
     * @param string $badge New emoji or badge identifier.
     * @param int $privacy New privacy level.
     * @param string $password New plain-text password; empty keeps the existing hash.
     * @return array Result with success flag and feedback message.
     * @throws \moodle_exception
     */
    public static function execute(
        int $cmid,
        int $groupid,
        string $name,
        string $description,
        string $badge,
        int $privacy = 0,
        string $password = ''
    ): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/group/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'        => $cmid,
            'groupid'     => $groupid,
            'name'        => $name,
            'description' => $description,
            'badge'       => $badge,
            'privacy'     => $privacy,
            'password'    => $password,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('mod/playergroup:creategroup', $context);

        $cm = get_coursemodule_from_id('playergroup', $params['cmid'], 0, false, MUST_EXIST);
        $playergroup = $DB->get_record('playergroup', ['id' => $cm->instance], '*', MUST_EXIST);

        \mod_playergroup\local\activity_window::require_open($playergroup);

        $meta = $DB->get_record('playergroup_meta', [
            'groupid'       => $params['groupid'],
            'playergroupid' => $playergroup->id,
        ], '*', MUST_EXIST);

        if ((int) $meta->creatorid !== (int) $USER->id) {
            throw new \moodle_exception('nopermissions', 'error', '', 'edit group');
        }

        // The leader recorded for a group kept after its last member left stays on record. Only someone who is
        // still in the group may change it, as the page itself offers editing only inside one's own group.
        if (!$DB->record_exists('groups_members', ['groupid' => $params['groupid'], 'userid' => $USER->id])) {
            throw new \moodle_exception('nopermissions', 'error', '', 'edit group');
        }

        $name = \mod_playergroup\local\text::require_valid_group_fields($params['name'], $params['badge']);

        // Resolve the privacy settings before writing anything, so a rejected request leaves the
        // group untouched. A protected group needs a password: either a new one or the stored hash.
        $privacylevel = (int) $params['privacy'];
        if (!in_array($privacylevel, [0, 1, 2], true)) {
            throw new \moodle_exception('errinvalidprivacy', 'mod_playergroup');
        }
        $hashedpassword = '';
        if ($privacylevel === 1) {
            if ($params['password'] !== '') {
                if (trim($params['password']) === '') {
                    throw new \moodle_exception('errpasswordrequired', 'mod_playergroup');
                }
                $hashedpassword = password_hash($params['password'], PASSWORD_DEFAULT);
            } else if (empty($meta->password)) {
                throw new \moodle_exception('errpasswordrequired', 'mod_playergroup');
            } else {
                $hashedpassword = $meta->password;
            }
        }

        $group = $DB->get_record('groups', ['id' => $params['groupid']], '*', MUST_EXIST);
        $group->name              = $name;
        $group->description       = \mod_playergroup\local\text::clean_description($params['description']);
        $group->descriptionformat = FORMAT_PLAIN;
        $group->timemodified      = time();
        groups_update_group($group);

        $meta->badge        = $params['badge'];
        $meta->privacy      = $privacylevel;
        $meta->password     = $hashedpassword;
        $meta->timemodified = time();
        $DB->update_record('playergroup_meta', $meta);

        return [
            'success' => true,
            'message' => get_string('groupupdated', 'mod_playergroup'),
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
