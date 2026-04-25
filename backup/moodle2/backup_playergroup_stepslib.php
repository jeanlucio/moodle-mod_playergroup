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
 * Backup steps for mod_playergroup are defined here.
 *
 * @package    mod_playergroup
 * @category   backup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the complete structure for the mod_playergroup backup XML file.
 *
 * XML tree:
 *   playergroup (activity config)
 *   └── playergroup_metas (content — always)
 *        └── playergroup_meta (group badge, privacy, password)
 *   └── playergroup_invites (user data — only with userinfo)
 *        └── playergroup_invite
 */
class backup_playergroup_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defines the structure of the resulting XML file.
     *
     * @return backup_nested_element The structure wrapped by the common 'activity' element.
     */
    protected function define_structure(): backup_nested_element {
        $userinfo = $this->get_setting_value('userinfo');

        // Root element: the playergroup activity instance.
        $playergroup = new backup_nested_element('playergroup', ['id'], [
            'course', 'name', 'intro', 'introformat', 'groupingid',
            'minmembers', 'maxmembers', 'canleave', 'deletegroups', 'grade',
            'completionjoingroup', 'timeopen', 'timeclose', 'timemodified',
        ]);

        // Group metadata is content: badge, privacy, password — always backed up.
        $metas = new backup_nested_element('playergroup_metas');
        $meta = new backup_nested_element('playergroup_meta', ['id'], [
            'groupid', 'creatorid', 'badge', 'privacy', 'password', 'timecreated', 'timemodified',
        ]);

        // Invites are user data: backed up only when userinfo is enabled.
        $invites = new backup_nested_element('playergroup_invites');
        $invite = new backup_nested_element('playergroup_invite', ['id'], [
            'groupid', 'senderid', 'receiverid', 'status', 'timecreated', 'timemodified',
        ]);

        // Build the XML tree.
        $playergroup->add_child($metas);
        $metas->add_child($meta);
        if ($userinfo) {
            $playergroup->add_child($invites);
            $invites->add_child($invite);
        }

        // Connect elements to their source tables.
        $playergroup->set_source_table('playergroup', ['id' => backup::VAR_ACTIVITYID]);
        $meta->set_source_table('playergroup_meta', ['playergroupid' => backup::VAR_PARENTID], 'id ASC');
        if ($userinfo) {
            $invite->set_source_table('playergroup_invites', ['playergroupid' => backup::VAR_PARENTID], 'id ASC');
        }

        // Annotate IDs that must be remapped on restore.
        $playergroup->annotate_ids('grouping', 'groupingid');
        $meta->annotate_ids('group', 'groupid');
        $meta->annotate_ids('user', 'creatorid');
        if ($userinfo) {
            $invite->annotate_ids('group', 'groupid');
            $invite->annotate_ids('user', 'senderid');
            $invite->annotate_ids('user', 'receiverid');
        }

        // Annotate file areas.
        $playergroup->annotate_files('mod_playergroup', 'intro', null);

        return $this->prepare_activity_structure($playergroup);
    }
}
