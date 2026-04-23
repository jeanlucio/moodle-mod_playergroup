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
 * Core module functions for the PlayerGroup plugin.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Indicates API features that the plugin supports.
 *
 * @param string $feature The feature string.
 * @return ?bool True if supported, null if not.
 */
function playergroup_supports(string $feature): ?bool {
    $features = [
        FEATURE_MOD_INTRO              => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_COMPLETION_HAS_RULES   => true,
        FEATURE_GRADE_HAS_GRADE        => true,
        FEATURE_GRADE_OUTCOMES         => true,
        FEATURE_BACKUP_MOODLE2         => true,
        FEATURE_SHOW_DESCRIPTION       => true,
    ];

    return $features[$feature] ?? null;
}

/**
 * Saves a new instance of the playergroup into the database.
 *
 * @param \stdClass $playergroup The submitted form data.
 * @param ?\mod_playergroup_mod_form $mform The form instance.
 * @return int The new instance ID.
 */
function playergroup_add_instance(\stdClass $playergroup, ?\mod_playergroup_mod_form $mform = null): int {
    global $DB;

    $playergroup->timemodified = time();
    $playergroup->timeopen     = $playergroup->timeopen ?? 0;
    $playergroup->timeclose    = $playergroup->timeclose ?? 0;
    $playergroup->groupingid   = 0; // Temporary zero until grouping is created.

    // Insert the base record first to get an ID.
    $playergroup->id = $DB->insert_record('playergroup', $playergroup);

    // Create the automated grouping and store its ID.
    $manager = new \mod_playergroup\instance_manager();
    $groupingid = $manager->process_activity_grouping($playergroup);

    $updateobj = new \stdClass();
    $updateobj->id        = $playergroup->id;
    $updateobj->groupingid = $groupingid;
    $DB->update_record('playergroup', $updateobj);

    playergroup_grade_item_update($playergroup);

    return $playergroup->id;
}

/**
 * Updates an existing instance of the playergroup.
 *
 * @param \stdClass $playergroup The submitted form data.
 * @param ?\mod_playergroup_mod_form $mform The form instance.
 * @return bool True on success.
 */
function playergroup_update_instance(\stdClass $playergroup, ?\mod_playergroup_mod_form $mform = null): bool {
    global $DB;

    $playergroup->timemodified = time();
    $playergroup->timeopen     = $playergroup->timeopen ?? 0;
    $playergroup->timeclose    = $playergroup->timeclose ?? 0;
    $playergroup->id           = $playergroup->instance;

    $result = $DB->update_record('playergroup', $playergroup);

    playergroup_grade_item_update($playergroup);

    return $result;
}

/**
 * Deletes an instance of the playergroup and its associated data.
 *
 * @param int $id The instance ID.
 * @return bool True on success.
 */
function playergroup_delete_instance(int $id): bool {
    global $CFG, $DB;

    require_once($CFG->libdir . '/gradelib.php');

    if (!$playergroup = $DB->get_record('playergroup', ['id' => $id])) {
        return false;
    }

    // Conditionally delete the grouping and all its groups based on professor's preference.
    if (!empty($playergroup->groupingid) && !empty($playergroup->deletegroups)) {
        $manager = new \mod_playergroup\instance_manager();
        $manager->delete_activity_grouping($playergroup->groupingid);
    }

    // Remove the grade item from the gradebook.
    grade_update('mod/playergroup', $playergroup->course, 'mod', 'playergroup', $id, 0, null, ['deleted' => 1]);

    // Clean up plugin-specific tables.
    $DB->delete_records('playergroup_invites', ['playergroupid' => $id]);
    $DB->delete_records('playergroup_meta', ['playergroupid' => $id]);

    return $DB->delete_records('playergroup', ['id' => $id]);
}

/**
 * Creates or updates the grade item in the gradebook for a playergroup instance.
 *
 * @param \stdClass $playergroup The playergroup instance record.
 * @param mixed $grades Grade data, null, or GRADE_UPDATE_ITEM_ONLY.
 * @return int GRADE_UPDATE_OK or GRADE_UPDATE_FAILED.
 */
function playergroup_grade_item_update(\stdClass $playergroup, mixed $grades = null): int {
    global $CFG;

    require_once($CFG->libdir . '/gradelib.php');

    if (!isset($playergroup->courseid)) {
        $playergroup->courseid = $playergroup->course;
    }

    if (!empty($playergroup->grade) && $playergroup->grade > 0) {
        $params = [
            'itemname'  => $playergroup->name,
            'gradetype' => GRADE_TYPE_VALUE,
            'grademax'  => $playergroup->grade,
            'grademin'  => 0,
        ];
    } else {
        $params = ['gradetype' => GRADE_TYPE_NONE];
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update(
        'mod/playergroup',
        $playergroup->courseid,
        'mod',
        'playergroup',
        $playergroup->id,
        0,
        $grades,
        $params
    );
}

/**
 * Updates all grades in the gradebook for a playergroup instance.
 *
 * @param \stdClass $playergroup The playergroup instance.
 * @param int $userid Update only this user's grade (0 = all users).
 * @return void
 */
function playergroup_update_grades(\stdClass $playergroup, int $userid = 0): void {
    playergroup_grade_item_update($playergroup, GRADE_UPDATE_ITEM_ONLY);
}

/**
 * Determines if a student has met the custom completion rules.
 *
 * Called by Moodle when FEATURE_COMPLETION_HAS_RULES is true. Checks whether
 * the student belongs to any group within this activity's grouping.
 *
 * @param \stdClass $course The course object.
 * @param object $cm The course module info (cm_info or stdClass).
 * @param int $userid The user ID to check.
 * @param bool $type Fallback return value when no rules are applicable.
 * @return bool True if the student belongs to a group, $type otherwise.
 */
function playergroup_get_completion_state(\stdClass $course, object $cm, int $userid, bool $type): bool {
    global $DB;

    $playergroup = $DB->get_record('playergroup', ['id' => $cm->instance], '*', MUST_EXIST);

    if (empty($playergroup->groupingid)) {
        return $type;
    }

    $sql = "SELECT gm.groupid
              FROM {groups_members} gm
              JOIN {playergroup_meta} pm ON pm.groupid = gm.groupid
             WHERE pm.playergroupid = :playergroupid
               AND gm.userid = :userid";

    return $DB->record_exists_sql($sql, [
        'playergroupid' => $playergroup->id,
        'userid'        => $userid,
    ]);
}

/**
 * Describes the active custom completion rules.
 *
 * @param \stdClass $course The course object.
 * @param \cm_info $cm The course module info.
 * @return array An array of active completion rule descriptions.
 */
function playergroup_get_completion_active_rule_descriptions(\stdClass $course, \cm_info $cm): array {
    $descriptions = [];

    $rules = $cm->customdata['customcompletionrules'] ?? [];
    if (!empty($rules['completionjoingroup'])) {
        $descriptions[] = get_string('completionjoingroup_desc', 'mod_playergroup');
    }

    return $descriptions;
}
