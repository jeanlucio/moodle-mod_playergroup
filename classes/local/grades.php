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
 * Grades of the students who qualified in a PlayerGroup activity.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

/**
 * Builds the grades sent to the gradebook, for any number of students in one query.
 */
final class grades {
    /**
     * Returns the grade of each student who qualified, ready for the gradebook.
     *
     * The grade is earned once, the first time the student is in a group that reached the minimum size, and that
     * moment is the submission date reported to the gradebook. A grade already recorded keeps its own date: after
     * leaving and joining another group, the student would otherwise look like a new, possibly late, submission.
     *
     * @param \stdClass $playergroup The playergroup instance record.
     * @param int[] $userids Students to grade; empty for every student who qualified.
     * @return \stdClass[] Grade objects keyed by user id; empty when the activity has no grade.
     */
    public static function for_students(\stdClass $playergroup, array $userids = []): array {
        global $DB;

        if (empty($playergroup->grade) || $playergroup->grade <= 0) {
            return [];
        }

        $params = ['playergroupid' => $playergroup->id];
        $userfilter = '';
        if (!empty($userids)) {
            [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
            $userfilter = "AND q.userid $usersql";
            $params += $userparams;
        }
        $sql = "SELECT q.userid, q.timequalified, MIN(gg.timecreated) AS timegranted
                  FROM {playergroup_qualified} q
             LEFT JOIN {grade_items} gi ON gi.itemtype = 'mod'
                       AND gi.itemmodule = 'playergroup'
                       AND gi.iteminstance = q.playergroupid
                       AND gi.itemnumber = 0
             LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = q.userid
                 WHERE q.playergroupid = :playergroupid
                       $userfilter
              GROUP BY q.userid, q.timequalified";

        $grades = [];
        foreach ($DB->get_records_sql($sql, $params) as $member) {
            $grade                = new \stdClass();
            $grade->userid        = $member->userid;
            $grade->rawgrade      = (float) $playergroup->grade;
            $grade->datesubmitted = (int) ($member->timegranted ?: $member->timequalified);
            $grades[$member->userid] = $grade;
        }

        return $grades;
    }
}
