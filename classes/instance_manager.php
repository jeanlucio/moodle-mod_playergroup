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
 * Handles complex business logic for playergroup instances.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup;

/**
 * Class instance_manager
 * Manages the background creation and deletion of Moodle native groupings.
 */
class instance_manager {
    /**
     * Creates a grouping for the activity instance.
     *
     * If a custom name is provided it is used directly; otherwise the grouping
     * is named automatically as "Groups of {activity name}".
     *
     * @param \stdClass $data The activity data object (requires ->course and ->name).
     * @param string $customname Optional custom grouping name supplied by the teacher.
     * @return int The ID of the newly created grouping.
     */
    public function process_activity_grouping(\stdClass $data, string $customname = ''): int {
        global $CFG;

        require_once($CFG->dirroot . '/group/lib.php');

        $name = $customname !== ''
            ? $customname
            : get_string('defaultgroupingname', 'mod_playergroup') . ' ' . $data->name;

        $grouping = new \stdClass();
        $grouping->courseid = $data->course;
        $grouping->name = $name;
        $grouping->description = get_string('groupingdescription', 'mod_playergroup', $data->name);
        $grouping->descriptionformat = FORMAT_HTML;
        $grouping->timecreated = time();
        $grouping->timemodified = time();

        return groups_create_grouping($grouping);
    }

    /**
     * Deletes the groups this activity instance created, and the grouping itself if
     * nothing else is left in it.
     *
     * The grouping may be one the plugin auto-created ("new" mode) or a pre-existing one
     * the teacher pointed the activity at ("existing" mode) that other activities or
     * restrictions may already depend on. Only groups registered in playergroup_meta for
     * this instance are ever deleted; the grouping record itself is removed only when doing
     * so leaves it empty, so a grouping still holding groups this instance did not create is
     * left untouched.
     *
     * @param int $groupingid The grouping ID to delete.
     * @param int $playergroupid The activity instance ID that owns the groups to delete.
     * @return void
     */
    public function delete_activity_grouping(int $groupingid, int $playergroupid): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/group/lib.php');

        if (!$DB->record_exists('groupings', ['id' => $groupingid])) {
            return;
        }

        $ownedgroupids = $DB->get_fieldset_select(
            'playergroup_meta',
            'groupid',
            'playergroupid = :playergroupid',
            ['playergroupid' => $playergroupid]
        );
        foreach ($ownedgroupids as $groupid) {
            groups_delete_group((int) $groupid);
        }

        $remaining = $DB->count_records('groupings_groups', ['groupingid' => $groupingid]);
        if ($remaining === 0) {
            groups_delete_grouping($groupingid);
        }
    }
}
