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
 * What a course reset removes from the PlayerGroup activities of the course.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

/**
 * Follows the options the teacher already ticked on the core "Reset course" page.
 *
 * The activity adds no option of its own: the data it keeps is the other half of what those options
 * clear. Completion and grades are derived from who qualified, so resetting either one while the
 * qualifications stayed would bring them straight back, and the groups and invites go with the groups.
 */
final class course_reset {
    /**
     * Applies the course reset options to the activities of the course.
     *
     * Called by core after it has removed enrolments and groups and cleared the completion data, and
     * before it resets the gradebook, with the same options the form returned.
     *
     * @param \stdClass $data Reset options; courseid plus the core flags (reset_completion, ...).
     * @return array[] Status rows for the reset report: component, item and error.
     */
    public static function reset(\stdClass $data): array {
        global $DB;

        $status = [];
        $component = get_string('modulenameplural', 'mod_playergroup');
        $courseid = (int) $data->courseid;
        $inactivities = 'playergroupid IN (SELECT id FROM {playergroup} WHERE course = :course)';

        // Completion and grades are earned by qualifying; clearing either means that has to be earned again.
        $resetsearned = !empty($data->reset_completion) || !empty($data->reset_gradebook_grades)
            || !empty($data->reset_gradebook_items);
        if ($resetsearned) {
            $DB->delete_records_select('playergroup_qualified', $inactivities, ['course' => $courseid]);
            $status[] = ['component' => $component, 'item' => get_string('resetqualified', 'mod_playergroup'), 'error' => false];
        }

        // Students unenrolled by the reset keep no qualification: if they enrol again, their completion
        // data was cleared on leaving and the rule would otherwise report them as done.
        if (!empty($data->unenrol_users)) {
            [$enrolledsql, $enrolledparams] = get_enrolled_sql(\context_course::instance($courseid));
            $DB->delete_records_select(
                'playergroup_qualified',
                "$inactivities AND userid NOT IN ($enrolledsql)",
                array_merge(['course' => $courseid], $enrolledparams)
            );
        }

        // Invites point at groups and at their members; once those are reset they mean nothing.
        $resetsgroups = !empty($data->reset_groups_remove) || !empty($data->reset_groups_members);
        if ($resetsgroups) {
            $DB->delete_records_select('playergroup_invites', $inactivities, ['course' => $courseid]);
            $status[] = ['component' => $component, 'item' => get_string('resetinvites', 'mod_playergroup'), 'error' => false];
        }

        // The window of the activity moves with the start date of the course, like the dates of the core activities.
        // Shifted dates may be earlier too, so the shift can be negative.
        if (!empty($data->timeshift)) {
            shift_course_mod_dates('playergroup', ['timeopen', 'timeclose'], (int) $data->timeshift, $courseid);
            $status[] = ['component' => $component, 'item' => get_string('date'), 'error' => false];
        }

        return $status;
    }
}
