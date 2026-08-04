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
 * Class group_lock
 *
 * join_group, create_group and accept_invite each check membership/capacity invariants and
 * then act on them (groups_add_member(), groups_create_group()) as separate, unlocked steps.
 * Two concurrent calls for the same activity instance can both pass the checks before either
 * writes, letting a student end up in two groups or a group exceed maxmembers. Acquiring this
 * lock before the checks and releasing it after the write serialises the whole sequence per
 * instance, so the second caller re-runs the same checks against the first caller's committed
 * result instead of racing it.
 */
final class group_lock {
    /**
     * Seconds to wait for the lock before giving up. The critical section is a handful of
     * fast queries plus one write, so a contending caller is expected to succeed well within
     * this window once the current holder releases.
     */
    private const TIMEOUT = 5;

    /**
     * Acquires the per-instance lock, blocking up to {@see TIMEOUT} seconds.
     *
     * @param int $playergroupid The activity instance ID whose group operations are serialised.
     * @return \core\lock\lock The held lock; the caller must release() it, typically in a finally block.
     * @throws \moodle_exception If the lock could not be acquired within the timeout.
     */
    public static function acquire(int $playergroupid): \core\lock\lock {
        $factory = \core\lock\lock_config::get_lock_factory('mod_playergroup');
        $lock = $factory->get_lock('group_' . $playergroupid, self::TIMEOUT);

        if (!$lock) {
            throw new \moodle_exception('grouplockbusy', 'mod_playergroup');
        }

        return $lock;
    }
}
