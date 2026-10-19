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
 * Tracks which students earned the grade and completion by being in a group of the minimum size.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

/**
 * A student qualifies the first time they are in a group that has at least the minimum number of
 * members configured by the teacher, and keeps that afterwards: leaving the group, or the group
 * shrinking, does not take the grade or the completion away.
 *
 * The state is stored (playergroup_qualified) instead of derived from the current group
 * membership, because the membership no longer tells whether the minimum was ever reached.
 */
final class qualification {
    /**
     * Returns the group size that counts for the grade and completion.
     *
     * The form refuses a minimum below 1, but older records may hold any value, and a group
     * always has at least its founder.
     *
     * @param \stdClass $playergroup Activity record with minmembers.
     * @return int Required number of members, at least 1.
     */
    public static function required_members(\stdClass $playergroup): int {
        return max(1, (int) $playergroup->minmembers);
    }

    /**
     * Tells whether a student has already qualified in an activity.
     *
     * @param int $playergroupid The activity instance ID.
     * @param int $userid The student.
     * @return bool True when the student earned the grade and completion.
     */
    public static function is_qualified(int $playergroupid, int $userid): bool {
        global $DB;

        return $DB->record_exists('playergroup_qualified', ['playergroupid' => $playergroupid, 'userid' => $userid]);
    }

    /**
     * Records the members of one group when it has reached the minimum size.
     *
     * Must be called while holding the activity's group lock, right after the membership changed,
     * so the member count it sees is the one the join or creation produced.
     *
     * @param \stdClass $playergroup Activity record.
     * @param int $groupid Native group ID, linked to the activity.
     * @return int[] IDs of the students who qualified now, that were not qualified before.
     */
    public static function record_group(\stdClass $playergroup, int $groupid): array {
        global $DB;

        $memberids = $DB->get_fieldset_select('groups_members', 'userid', 'groupid = :groupid', ['groupid' => $groupid]);
        if (count($memberids) < self::required_members($playergroup)) {
            return [];
        }

        return self::insert_missing((int) $playergroup->id, $memberids);
    }

    /**
     * Records every group of the activity that has reached the minimum size.
     *
     * Used when the teacher lowers the minimum, since groups that were below it may now count.
     *
     * @param \stdClass $playergroup Activity record.
     * @return int[] IDs of the students who qualified now, that were not qualified before.
     */
    public static function record_all_groups(\stdClass $playergroup): array {
        global $DB;

        $sql = "SELECT gm.id, gm.groupid, gm.userid
                  FROM {groups_members} gm
                  JOIN {playergroup_meta} pm ON pm.groupid = gm.groupid
                 WHERE pm.playergroupid = :playergroupid";
        $membersbygroup = [];
        $members = $DB->get_recordset_sql($sql, ['playergroupid' => $playergroup->id]);
        foreach ($members as $member) {
            $membersbygroup[(int) $member->groupid][] = (int) $member->userid;
        }
        $members->close();

        $required = self::required_members($playergroup);
        $qualifying = [];
        foreach ($membersbygroup as $memberids) {
            if (count($memberids) >= $required) {
                $qualifying = array_merge($qualifying, $memberids);
            }
        }

        return self::insert_missing((int) $playergroup->id, $qualifying);
    }

    /**
     * Gives the grade and the completion to students who just qualified.
     *
     * Completion is updated for each of them, not only for the student who triggered the change:
     * when a group reaches the minimum, the members who were already in it qualify at that moment,
     * and nothing recalculates automatic completion later.
     *
     * @param \stdClass $playergroup Activity record.
     * @param \stdClass $cm Course module (needs id and course).
     * @param int[] $userids Students who qualified.
     * @return void
     */
    public static function award(\stdClass $playergroup, \stdClass $cm, array $userids): void {
        global $CFG;

        if (empty($userids)) {
            return;
        }

        // The grade function lives in lib.php. The web services load it, but this also runs from the observer of
        // the member-added event, in requests that never loaded it (the Groups page of the course, for one).
        require_once($CFG->dirroot . '/mod/playergroup/lib.php');

        $modinfo = get_fast_modinfo($cm->course);
        $cminfo = $modinfo->get_cm($cm->id);
        $completion = new \completion_info($modinfo->get_course());
        $automatic = $completion->is_enabled($cminfo) == COMPLETION_TRACKING_AUTOMATIC;

        if ($automatic) {
            foreach ($userids as $userid) {
                $completion->update_state($cminfo, COMPLETION_COMPLETE, $userid);
            }
        }

        // One query and one gradebook call for everyone: lowering the minimum can qualify a whole class at once.
        $grades = grades::for_students($playergroup, $userids);
        if (!empty($grades)) {
            playergroup_grade_item_update($playergroup, $grades);
        }
    }

    /**
     * Returns how many more members a group needs before it counts, for the student's own group.
     *
     * @param \stdClass $playergroup Activity record.
     * @param int $membercount Current number of members of the group.
     * @param bool $userqualified Whether the student already qualified (so the group size no longer matters).
     * @return int Members still missing; 0 when the group counts or the student already qualified.
     */
    public static function missing_members(\stdClass $playergroup, int $membercount, bool $userqualified): int {
        if ($userqualified) {
            return 0;
        }

        return max(0, self::required_members($playergroup) - $membercount);
    }

    /**
     * Stores a row for each student not yet recorded in the activity.
     *
     * @param int $playergroupid The activity instance ID.
     * @param int[] $userids Students that should be recorded.
     * @return int[] The students that were added.
     */
    private static function insert_missing(int $playergroupid, array $userids): array {
        global $DB;

        $userids = array_values(array_unique(array_map('intval', $userids)));
        if (empty($userids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
        $params['playergroupid'] = $playergroupid;
        $existing = $DB->get_fieldset_select(
            'playergroup_qualified',
            'userid',
            "playergroupid = :playergroupid AND userid $insql",
            $params
        );

        $missing = array_values(array_diff($userids, array_map('intval', $existing)));
        $now = time();
        $rows = array_map(fn(int $userid): \stdClass => (object) [
            'playergroupid' => $playergroupid,
            'userid' => $userid,
            'timequalified' => $now,
        ], $missing);
        if (!empty($rows)) {
            $DB->insert_records('playergroup_qualified', $rows);
        }

        return $missing;
    }
}
