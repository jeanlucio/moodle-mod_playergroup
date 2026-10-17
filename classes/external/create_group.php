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
 * External function to create a group.
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
 * Class create_group
 * External API endpoint for students to create a group within an activity.
 */
class create_group extends external_api {
    /**
     * Defines the parameters the AJAX call must send.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'        => new external_value(PARAM_INT, 'Course module ID'),
            'name'        => new external_value(PARAM_TEXT, 'Name of the group'),
            'description' => new external_value(PARAM_RAW, 'Group description (may contain HTML)'),
            'badge'       => new external_value(PARAM_TEXT, 'Emoji or badge string'),
            'privacy'     => new external_value(PARAM_INT, 'Privacy level: 0=open, 1=protected, 2=closed', VALUE_DEFAULT, 0),
            'password'    => new external_value(PARAM_RAW, 'Plain-text password for protected groups', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Executes the group creation.
     *
     * @param int $cmid Course module ID.
     * @param string $name Group name.
     * @param string $description Group description (raw HTML, will be sanitised).
     * @param string $badge Emoji or badge identifier.
     * @param int $privacy Privacy level: 0=open, 1=protected (password), 2=closed.
     * @param string $password Plain-text password for protected groups (stored hashed).
     * @return array Result with success flag, group ID, and feedback message.
     * @throws \moodle_exception
     */
    public static function execute(
        int $cmid,
        string $name,
        string $description,
        string $badge,
        int $privacy = 0,
        string $password = ''
    ): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->dirroot . '/mod/playergroup/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid'        => $cmid,
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

        // Reject unusable settings before anything is written: a protected group without a
        // password can never be joined, since join_group refuses an empty stored hash.
        $name = \mod_playergroup\local\text::require_valid_group_fields($params['name'], $params['badge']);
        $privacylevel = (int) $params['privacy'];
        if (!in_array($privacylevel, [0, 1, 2], true)) {
            throw new \moodle_exception('errinvalidprivacy', 'mod_playergroup');
        }
        if ($privacylevel === 2 && !\mod_playergroup\local\invitees::can_pick($context)) {
            throw new \moodle_exception('errcannotclose', 'mod_playergroup');
        }
        if ($privacylevel === 1 && trim($params['password']) === '') {
            throw new \moodle_exception('errpasswordrequired', 'mod_playergroup');
        }

        $lock = \mod_playergroup\local\group_lock::acquire($playergroup->id);
        try {
            // Ensure the student does not already belong to a group in this activity.
            $hassql = "SELECT gm.groupid
                         FROM {groups_members} gm
                         JOIN {playergroup_meta} pm ON pm.groupid = gm.groupid
                        WHERE pm.playergroupid = :playergroupid
                          AND gm.userid = :userid";

            $hassqlparams = ['playergroupid' => $playergroup->id, 'userid' => $USER->id];
            if ($DB->record_exists_sql($hassql, $hassqlparams)) {
                throw new \moodle_exception('alreadyingroup', 'mod_playergroup');
            }

            // Create the native Moodle group.
            $newgroup = new \stdClass();
            $newgroup->courseid = $cm->course;
            $newgroup->name = $name;
            $newgroup->description = \mod_playergroup\local\text::clean_description($params['description']);
            $newgroup->descriptionformat = FORMAT_PLAIN;
            $newgroup->timecreated = time();
            $groupid = groups_create_group($newgroup);

            // Assign the new group to this activity's grouping, unless it is gone: the core fails on a
            // grouping that does not exist, after it has already written to groupings_groups.
            if ($playergroup->groupingid > 0 && $DB->record_exists('groupings', ['id' => $playergroup->groupingid])) {
                groups_assign_grouping($playergroup->groupingid, $groupid);
            }

            // Add the founding student as the first group member. groups_add_member()
            // returns false (no exception) when the caller is not enrolled in the course -
            // undo the native group so it is never left orphaned with no real member.
            if (!groups_add_member($groupid, $USER->id)) {
                groups_delete_group($groupid);
                throw new \moodle_exception('joinfailed', 'mod_playergroup');
            }

            $hashedpassword = $privacylevel === 1 ? password_hash($params['password'], PASSWORD_DEFAULT) : '';

            // Store metadata (badge, privacy, creator) in the plugin's table.
            $meta = new \stdClass();
            $meta->groupid       = $groupid;
            $meta->playergroupid = $playergroup->id;
            $meta->creatorid     = $USER->id;
            $meta->badge         = $params['badge'];
            $meta->privacy       = $privacylevel;
            $meta->password      = $hashedpassword;
            $meta->timecreated   = time();
            $meta->timemodified  = time();
            $DB->insert_record('playergroup_meta', $meta);

            // With a minimum of one member the founder qualifies at once; otherwise the group
            // still has to grow.
            $qualified = \mod_playergroup\local\qualification::record_group($playergroup, $groupid);
        } finally {
            $lock->release();
        }

        \mod_playergroup\local\qualification::award($playergroup, $cm, $qualified);

        \mod_playergroup\event\group_created::create([
            'context'  => $context,
            'objectid' => $groupid,
        ])->trigger();

        return [
            'success' => true,
            'groupid' => $groupid,
            'message' => get_string('groupcreated', 'mod_playergroup'),
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
            'groupid' => new external_value(PARAM_INT, 'The new group ID'),
            'message' => new external_value(PARAM_TEXT, 'Feedback message'),
        ]);
    }
}
