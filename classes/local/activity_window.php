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
 * Availability window (timeopen/timeclose) of a PlayerGroup activity.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

/**
 * Single definition of when the activity accepts changes to groups.
 *
 * The interface hides every action outside the window, but a web service can be called directly,
 * so each one that changes group composition or sends an invite must enforce the same rule on the
 * server. Keeping it here stops the endpoints from drifting apart.
 */
final class activity_window {
    /**
     * Tells whether the activity is inside its availability window.
     *
     * A bound of 0 means "no limit" on that side.
     *
     * @param \stdClass $playergroup Activity record with timeopen and timeclose.
     * @param int|null $now Current timestamp; defaults to the present moment.
     * @return bool True when groups may be changed.
     */
    public static function is_open(\stdClass $playergroup, ?int $now = null): bool {
        $now ??= time();
        $timeopen = (int) ($playergroup->timeopen ?? 0);
        $timeclose = (int) ($playergroup->timeclose ?? 0);

        return ($timeopen === 0 || $now >= $timeopen) && ($timeclose === 0 || $now <= $timeclose);
    }

    /**
     * Throws unless the activity is inside its availability window.
     *
     * @param \stdClass $playergroup Activity record with timeopen and timeclose.
     * @return void
     * @throws \moodle_exception activitynotopen before the window, activityclosed after it.
     */
    public static function require_open(\stdClass $playergroup): void {
        if (self::is_open($playergroup)) {
            return;
        }

        $timeopen = (int) ($playergroup->timeopen ?? 0);
        if ($timeopen > 0 && time() < $timeopen) {
            throw new \moodle_exception('activitynotopen', 'mod_playergroup');
        }
        throw new \moodle_exception('activityclosed', 'mod_playergroup');
    }
}
