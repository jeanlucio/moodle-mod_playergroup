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
     * Deletes the automated grouping and all its group associations.
     *
     * @param int $groupingid The grouping ID to delete.
     * @return void
     */
    public function delete_activity_grouping(int $groupingid): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/group/lib.php');

        if (!$DB->record_exists('groupings', ['id' => $groupingid])) {
            return;
        }

        // Delete each group that belongs to this grouping before deleting the grouping itself.
        // groups_delete_grouping() only removes the grouping record, not the groups.
        $groupids = $DB->get_fieldset_select(
            'groupings_groups',
            'groupid',
            'groupingid = :groupingid',
            ['groupingid' => $groupingid]
        );
        foreach ($groupids as $groupid) {
            groups_delete_group((int) $groupid);
        }

        groups_delete_grouping($groupingid);
    }
}
