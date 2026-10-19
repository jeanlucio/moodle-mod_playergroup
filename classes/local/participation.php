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
 * Who can take part in a PlayerGroup activity, as opposed to only look at it.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

/**
 * Tells whether a user may join or found a group.
 */
final class participation {
    /**
     * Tells whether a user may join or found a group of the activity.
     *
     * The capability to view is not enough: the guests have it, and so do the administrators and managers who are
     * not enrolled in the course. The core refuses to add to a group someone who is not enrolled, so the page and
     * the app must not offer the action to them, and the services must say why instead of failing halfway.
     *
     * @param \context_module $context The activity context.
     * @param int $userid The user.
     * @return bool True when the user has an active enrolment in the course and may view the activity.
     */
    public static function can_take_part(\context_module $context, int $userid): bool {
        return is_enrolled($context, $userid, 'mod/playergroup:view', true);
    }

    /**
     * Refuses a user who cannot take part in the activity.
     *
     * @param \context_module $context The activity context.
     * @param int $userid The user.
     * @return void
     * @throws \moodle_exception When the user is not enrolled in the course.
     */
    public static function require_can_take_part(\context_module $context, int $userid): void {
        if (!self::can_take_part($context, $userid)) {
            throw new \moodle_exception('errnotenrolled', 'mod_playergroup');
        }
    }
}
