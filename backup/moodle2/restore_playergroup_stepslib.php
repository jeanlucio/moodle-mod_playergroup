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
 * All the steps to restore mod_playergroup are defined here.
 *
 * @package    mod_playergroup
 * @category   backup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the structure step to restore one mod_playergroup activity.
 *
 * Restore order:
 *   1. playergroup — creates the activity instance, remaps groupingid.
 *   2. playergroup_meta — remaps groupid and creatorid; always restored.
 *   3. playergroup_invite — remaps groupid, senderid, receiverid; only with userinfo.
 */
class restore_playergroup_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines the XML paths that this restore step will process.
     *
     * @return restore_path_element[]
     */
    protected function define_structure(): array {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('playergroup', '/activity/playergroup');
        $paths[] = new restore_path_element(
            'playergroup_meta',
            '/activity/playergroup/playergroup_metas/playergroup_meta'
        );
        if ($userinfo) {
            $paths[] = new restore_path_element(
                'playergroup_invite',
                '/activity/playergroup/playergroup_invites/playergroup_invite'
            );
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Processes the playergroup activity record.
     *
     * @param array $data Parsed element data.
     */
    protected function process_playergroup(array $data): void {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();
        $data->groupingid = (int) $this->get_mappingid('grouping', $data->groupingid);
        $data->timemodified = time();

        $newitemid = $DB->insert_record('playergroup', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Processes a playergroup_meta record (group badge, privacy, password).
     *
     * @param array $data Parsed element data.
     */
    protected function process_playergroup_meta(array $data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->playergroupid = $this->get_new_parentid('playergroup');
        $data->groupid = (int) $this->get_mappingid('group', $data->groupid);
        $data->creatorid = (int) $this->get_mappingid('user', $data->creatorid);

        $newitemid = $DB->insert_record('playergroup_meta', $data);
        $this->set_mapping('playergroup_meta', $oldid, $newitemid);
    }

    /**
     * Processes a playergroup_invite record (user data — only when userinfo is enabled).
     *
     * @param array $data Parsed element data.
     */
    protected function process_playergroup_invite(array $data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->playergroupid = $this->get_new_parentid('playergroup');
        $data->groupid = (int) $this->get_mappingid('group', $data->groupid);
        $data->senderid = (int) $this->get_mappingid('user', $data->senderid);
        $data->receiverid = (int) $this->get_mappingid('user', $data->receiverid);

        $newitemid = $DB->insert_record('playergroup_invites', $data);
        $this->set_mapping('playergroup_invite', $oldid, $newitemid);
    }

    /**
     * Runs after all elements have been restored.
     */
    protected function after_execute(): void {
        $this->add_related_files('mod_playergroup', 'intro', null);
    }
}
