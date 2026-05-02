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
 * External function to join an existing group.
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
 * Class join_group
 * External API endpoint for students to join an existing group within an activity.
 */
class join_group extends external_api {
    /**
     * Defines the parameters the AJAX call must send.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'     => new external_value(PARAM_INT, 'Course module ID'),
            'groupid'  => new external_value(PARAM_INT, 'ID of the group to join'),
            'password' => new external_value(PARAM_RAW, 'Password for protected groups', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Executes the group join.
     *
     * @param int $cmid Course module ID.
     * @param int $groupid ID of the group to join.
     * @param string $password Password for protected groups (empty for open groups).
     * @return array Result with success flag and feedback message.
     * @throws \moodle_exception
     */
    public static function execute(int $cmid, int $groupid, string $password = ''): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->dirroot . '/mod/playergroup/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'     => $cmid,
            'groupid'  => $groupid,
            'password' => $password,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('mod/playergroup:view', $context);

        $cm = get_coursemodule_from_id('playergroup', $params['cmid'], 0, false, MUST_EXIST);
        $playergroup = $DB->get_record('playergroup', ['id' => $cm->instance], '*', MUST_EXIST);

        $now = time();
        if ($playergroup->timeopen > 0 && $now < $playergroup->timeopen) {
            throw new \moodle_exception('activitynotopen', 'mod_playergroup');
        }
        if ($playergroup->timeclose > 0 && $now > $playergroup->timeclose) {
            throw new \moodle_exception('activityclosed', 'mod_playergroup');
        }

        $hassql = "SELECT gm.groupid
                     FROM {groups_members} gm
                     JOIN {playergroup_meta} pm ON pm.groupid = gm.groupid
                    WHERE pm.playergroupid = :playergroupid
                      AND gm.userid = :userid";

        if ($DB->record_exists_sql($hassql, ['playergroupid' => $playergroup->id, 'userid' => $USER->id])) {
            throw new \moodle_exception('alreadyingroup', 'mod_playergroup');
        }

        $meta = $DB->get_record(
            'playergroup_meta',
            ['groupid' => $params['groupid'], 'playergroupid' => $playergroup->id],
            '*',
            MUST_EXIST
        );

        if ((int) $meta->privacy === 2) {
            throw new \moodle_exception('groupclosed', 'mod_playergroup');
        }

        if ((int) $meta->privacy === 1) {
            if (empty($meta->password) || !password_verify($params['password'], $meta->password)) {
                throw new \moodle_exception('wrongpassword', 'mod_playergroup');
            }
        }

        $membercount = $DB->count_records('groups_members', ['groupid' => $params['groupid']]);
        if ($membercount >= (int) $playergroup->maxmembers) {
            throw new \moodle_exception('groupisfull', 'mod_playergroup');
        }

        groups_add_member($params['groupid'], $USER->id);

        $modinfo = get_fast_modinfo($cm->course);
        $cminfo = $modinfo->get_cm($cm->id);
        $completion = new \completion_info($modinfo->get_course());
        if ($completion->is_enabled($cminfo) == COMPLETION_TRACKING_AUTOMATIC) {
            $completion->update_state($cminfo, COMPLETION_COMPLETE, $USER->id);
        }

        playergroup_update_grades($playergroup, $USER->id);

        \mod_playergroup\event\member_joined::create([
            'context'  => $context,
            'objectid' => $params['groupid'],
        ])->trigger();

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
