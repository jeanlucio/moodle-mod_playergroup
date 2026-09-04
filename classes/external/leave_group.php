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
 * External function to leave a group.
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
 * Class leave_group
 * External API endpoint for students to leave their current group within an activity.
 */
class leave_group extends external_api {
    /**
     * Defines the parameters the AJAX call must send.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    /**
     * Executes the group leave.
     *
     * @param int $cmid Course module ID.
     * @return array Result with success flag and feedback message.
     * @throws \moodle_exception
     */
    public static function execute(int $cmid): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/group/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('mod/playergroup:view', $context);

        $cm = get_coursemodule_from_id('playergroup', $params['cmid'], 0, false, MUST_EXIST);
        $playergroup = $DB->get_record('playergroup', ['id' => $cm->instance], '*', MUST_EXIST);

        if (empty($playergroup->canleave)) {
            throw new \moodle_exception('cannotleavegroup', 'mod_playergroup');
        }

        $sql = "SELECT gm.groupid
                  FROM {groups_members} gm
                  JOIN {playergroup_meta} pm ON pm.groupid = gm.groupid
                 WHERE pm.playergroupid = :playergroupid
                   AND gm.userid = :userid";

        $mygroupid = $DB->get_field_sql($sql, ['playergroupid' => $playergroup->id, 'userid' => $USER->id]);

        if (!$mygroupid) {
            throw new \moodle_exception('notingroup', 'mod_playergroup');
        }

        $meta = $DB->get_record('playergroup_meta', ['groupid' => $mygroupid], '*', MUST_EXIST);

        // Find the oldest remaining member to determine next leader or detect empty group.
        $nextsql = "SELECT id, userid
                      FROM {groups_members}
                     WHERE groupid = :groupid
                       AND userid != :userid
                     ORDER BY timeadded ASC";
        $nextleaders = $DB->get_records_sql($nextsql, ['groupid' => $mygroupid, 'userid' => $USER->id], 0, 1);
        $nextleader = !empty($nextleaders) ? (int) reset($nextleaders)->userid : null;
        $willbeempty = ($nextleader === null);

        // Removal below fires \core\event\group_member_removed synchronously, and
        // \mod_playergroup\observer::group_member_removed() reacts to it by deleting the
        // group when it is now empty and the activity has "delete empty groups" enabled —
        // the same cleanup this method used to do inline. Centralising it in the observer
        // means it also runs for every other way a group can be emptied (a teacher removing
        // the last member from the course's native Groups page, or an unenrolment), not only
        // when a student uses this web service. See classes/observer.php for the full
        // rationale.
        groups_remove_member((int) $mygroupid, $USER->id);

        \mod_playergroup\event\member_left::create([
            'context'  => $context,
            'objectid' => (int) $mygroupid,
        ])->trigger();

        if (!$willbeempty && (int) $meta->creatorid === (int) $USER->id) {
            // Transfer leadership to the oldest remaining member.
            $DB->set_field('playergroup_meta', 'creatorid', $nextleader, ['groupid' => $mygroupid]);
        }

        return [
            'success' => true,
            'message' => get_string('groupleft', 'mod_playergroup'),
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
