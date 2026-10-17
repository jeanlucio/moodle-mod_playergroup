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
    /** Longest grouping name the groupings table holds. */
    public const MAX_GROUPING_NAME = 255;

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

        $name = $customname !== '' ? $customname : $this->default_grouping_name($data->course, $data->name);

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
     * Builds the name of the grouping made for an activity when the teacher typed none.
     *
     * The name must fit the column, and be one the course does not use yet: two activities with the same name
     * would otherwise get groupings with the same name, which the restore of the course joins into one.
     *
     * @param int $courseid The course.
     * @param string $activityname Name of the activity.
     * @return string A name of at most 255 characters that no grouping of the course has.
     */
    private function default_grouping_name(int $courseid, string $activityname): string {
        $base = get_string('defaultgroupingname', 'mod_playergroup') . ' ' . $activityname;
        $base = \core_text::substr($base, 0, self::MAX_GROUPING_NAME);

        $name = $base;
        for ($suffix = 2; groups_get_grouping_by_name($courseid, $name); $suffix++) {
            $tail = ' (' . $suffix . ')';
            $name = \core_text::substr($base, 0, self::MAX_GROUPING_NAME - \core_text::strlen($tail)) . $tail;
        }

        return $name;
    }

    /**
     * Deletes the groups this activity instance created, and the grouping if nothing else is left in it.
     *
     * Only groups registered in playergroup_meta for this instance are ever deleted, whatever the
     * grouping mode: with no grouping at all, or with one the teacher already removed by hand, the
     * groups the students created through the activity must still go. The grouping may be one the
     * plugin auto-created ("new" mode) or a pre-existing one the teacher pointed the activity at
     * ("existing" mode) that other activities or restrictions may depend on, so the grouping record
     * itself is removed only when it exists and doing so leaves it empty.
     *
     * @param int $playergroupid The activity instance ID that owns the groups to delete.
     * @param int $groupingid The activity's grouping ID, or 0 when it has none.
     * @return void
     */
    public function delete_activity_groups(int $playergroupid, int $groupingid = 0): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/group/lib.php');

        $ownedgroupids = $DB->get_fieldset_select(
            'playergroup_meta',
            'groupid',
            'playergroupid = :playergroupid',
            ['playergroupid' => $playergroupid]
        );
        foreach ($ownedgroupids as $groupid) {
            groups_delete_group((int) $groupid);
        }

        $groupingexists = $groupingid > 0 && $DB->record_exists('groupings', ['id' => $groupingid]);
        if ($groupingexists && $DB->count_records('groupings_groups', ['groupingid' => $groupingid]) === 0) {
            groups_delete_grouping($groupingid);
        }
    }
}
