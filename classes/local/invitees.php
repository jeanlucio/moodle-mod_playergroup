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
 * Serialises the group membership check-then-act sequence for one activity instance.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

/**
 * Who may be invited to a group of an activity, and who may do the inviting.
 */
final class invitees {
    /**
     * Tells whether the current user may pick people to invite.
     *
     * The same rule as the invite service: the right to invite, and to see the course participants.
     * A professor who hid the participants from students decided they should not browse course-mates by name.
     *
     * @param \context_module $context The activity context.
     * @return bool True when the user may pick people to invite.
     */
    public static function can_pick(\context_module $context): bool {
        return has_capability('moodle/course:viewparticipants', $context->get_course_context())
            && has_capability('mod/playergroup:manageinvites', $context);
    }

    /**
     * Returns the SQL that selects the users who can open the activity and could take part in it.
     *
     * Being enrolled is not enough: the person also needs the right to view the activity, which a
     * role override can take away.
     *
     * @param \context_module $context The activity context.
     * @return array The SQL selecting user ids, and its parameters.
     */
    public static function enrolled_sql(\context_module $context): array {
        return get_enrolled_sql($context, 'mod/playergroup:view', 0, true);
    }

    /**
     * Keeps the users that the access restrictions of the activity let in.
     *
     * An invited person who cannot open the activity can neither accept nor decline, so the invite
     * would stay pending for good. Done for the whole list at once, without a query per user.
     *
     * @param \cm_info $cm The activity.
     * @param array $users Users keyed by their id.
     * @return array The users who may open the activity.
     */
    public static function filter_available(\cm_info $cm, array $users): array {
        return (new \core_availability\info_module($cm))->filter_user_list($users);
    }

    /**
     * Tells whether a user could open the activity and take part in it.
     *
     * @param \cm_info $cm The activity.
     * @param \context_module $context The activity context.
     * @param int $userid The user.
     * @return bool True when the user is enrolled, may view the activity and can reach it.
     */
    public static function can_receive(\cm_info $cm, \context_module $context, int $userid): bool {
        return is_enrolled($context, $userid, 'mod/playergroup:view', true)
            && \core_availability\info_module::is_user_visible($cm, $userid);
    }
}
