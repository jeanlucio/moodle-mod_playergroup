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
 * Privacy provider implementation for mod_playergroup.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_playergroup.
 *
 * Personal data is stored in:
 * - playergroup_meta (creatorid)
 * - playergroup_invites (senderid, receiverid)
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns metadata about personal data stored by this plugin.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('playergroup_meta', [
            'creatorid'   => 'privacy:metadata:creatorid',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:playergroup_meta');

        $collection->add_database_table('playergroup_invites', [
            'senderid'    => 'privacy:metadata:senderid',
            'receiverid'  => 'privacy:metadata:receiverid',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:playergroup_invites');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Contexts where the user created a group.
        $sql = "SELECT ctx.id
                  FROM {playergroup_meta} pm
                  JOIN {playergroup} pg ON pg.id = pm.playergroupid
                  JOIN {modules} m ON m.name = :activityname1
                  JOIN {course_modules} cm ON cm.instance = pg.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel1
                 WHERE pm.creatorid = :userid1";
        $contextlist->add_from_sql($sql, [
            'activityname1' => 'playergroup',
            'modlevel1'     => CONTEXT_MODULE,
            'userid1'       => $userid,
        ]);

        // Contexts where the user sent or received a group invite.
        $sql = "SELECT ctx.id
                  FROM {playergroup_invites} pi
                  JOIN {playergroup} pg ON pg.id = pi.playergroupid
                  JOIN {modules} m ON m.name = :activityname2
                  JOIN {course_modules} cm ON cm.instance = pg.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel2
                 WHERE pi.senderid = :userid2 OR pi.receiverid = :userid3";
        $contextlist->add_from_sql($sql, [
            'activityname2' => 'playergroup',
            'modlevel2'     => CONTEXT_MODULE,
            'userid2'       => $userid,
            'userid3'       => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist to populate.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $params = [
            'activityname' => 'playergroup',
            'modlevel'     => CONTEXT_MODULE,
            'contextid'    => $context->id,
        ];

        $sql = "SELECT pm.creatorid AS userid
                  FROM {playergroup_meta} pm
                  JOIN {playergroup} pg ON pg.id = pm.playergroupid
                  JOIN {modules} m ON m.name = :activityname
                  JOIN {course_modules} cm ON cm.instance = pg.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel
                 WHERE ctx.id = :contextid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT pi.senderid AS userid
                  FROM {playergroup_invites} pi
                  JOIN {playergroup} pg ON pg.id = pi.playergroupid
                  JOIN {modules} m ON m.name = :activityname
                  JOIN {course_modules} cm ON cm.instance = pg.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel
                 WHERE ctx.id = :contextid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT pi.receiverid AS userid
                  FROM {playergroup_invites} pi
                  JOIN {playergroup} pg ON pg.id = pi.playergroupid
                  JOIN {modules} m ON m.name = :activityname
                  JOIN {course_modules} cm ON cm.instance = pg.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel
                 WHERE ctx.id = :contextid";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        $contexts = array_reduce($contextlist->get_contexts(), function (array $carry, \context $context): array {
            if ($context->contextlevel == CONTEXT_MODULE) {
                $carry[$context->id] = $context;
            }
            return $carry;
        }, []);

        if (empty($contexts)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($contexts), SQL_PARAMS_NAMED, 'ctx');

        // Export groups created by this user — bulk fetch, then organise by context.
        $sql = "SELECT pm.id, pm.badge, pm.timecreated, ctx.id AS contextid
                  FROM {playergroup_meta} pm
                  JOIN {playergroup} pg ON pg.id = pm.playergroupid
                  JOIN {modules} m ON m.name = 'playergroup'
                  JOIN {course_modules} cm ON cm.instance = pg.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id
                 WHERE ctx.id $insql
                   AND pm.creatorid = :userid";
        $metarecords = $DB->get_recordset_sql($sql, array_merge($inparams, ['userid' => $userid]));
        $allmetadata = [];
        foreach ($metarecords as $record) {
            $allmetadata[$record->contextid][] = (object)[
                'badge'       => $record->badge,
                'timecreated' => transform::datetime($record->timecreated),
            ];
        }
        $metarecords->close();

        foreach ($allmetadata as $contextid => $data) {
            writer::with_context($contexts[$contextid])->export_data(
                [get_string('privacy:metadata:playergroup_meta', 'mod_playergroup')],
                (object)['groups' => $data]
            );
        }

        // Export invite records (sent and received) — bulk fetch, then organise by context.
        $sql = "SELECT pi.id, pi.senderid, pi.receiverid, pi.status, pi.timecreated, ctx.id AS contextid
                  FROM {playergroup_invites} pi
                  JOIN {playergroup} pg ON pg.id = pi.playergroupid
                  JOIN {modules} m ON m.name = 'playergroup'
                  JOIN {course_modules} cm ON cm.instance = pg.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id
                 WHERE ctx.id $insql
                   AND (pi.senderid = :userid1 OR pi.receiverid = :userid2)";
        $inviterecords = $DB->get_recordset_sql($sql, array_merge($inparams, [
            'userid1' => $userid,
            'userid2' => $userid,
        ]));
        $allinvites = [];
        foreach ($inviterecords as $record) {
            $allinvites[$record->contextid][] = (object)[
                'issender'    => transform::yesno($record->senderid == $userid),
                'isreceiver'  => transform::yesno($record->receiverid == $userid),
                'status'      => (int) $record->status,
                'timecreated' => transform::datetime($record->timecreated),
            ];
        }
        $inviterecords->close();

        foreach ($allinvites as $contextid => $invites) {
            writer::with_context($contexts[$contextid])->export_data(
                [get_string('privacy:metadata:playergroup_invites', 'mod_playergroup')],
                (object)['invites' => $invites]
            );
        }
    }

    /**
     * Delete all user data for all users in the specified context.
     *
     * @param \context $context The context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('playergroup', $context->instanceid);
        if (!$cm) {
            return;
        }

        $DB->delete_records_select(
            'playergroup_invites',
            'playergroupid = :playergroupid',
            ['playergroupid' => $cm->instance]
        );

        $DB->delete_records_select(
            'playergroup_meta',
            'playergroupid = :playergroupid',
            ['playergroupid' => $cm->instance]
        );
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        // Collect all playergroup instance IDs for the approved contexts.
        $instanceids = [];
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id('playergroup', $context->instanceid);
            if ($cm) {
                $instanceids[] = (int) $cm->instance;
            }
        }

        if (empty($instanceids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED, 'pg');

        $DB->delete_records_select(
            'playergroup_meta',
            "playergroupid $insql AND creatorid = :creatorid",
            array_merge($inparams, ['creatorid' => $userid])
        );

        $DB->delete_records_select(
            'playergroup_invites',
            "playergroupid $insql AND (senderid = :senderid OR receiverid = :receiverid)",
            array_merge($inparams, ['senderid' => $userid, 'receiverid' => $userid])
        );
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $cm = get_coursemodule_from_id('playergroup', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $DB->delete_records_select(
            'playergroup_meta',
            "playergroupid = :playergroupid AND creatorid $insql",
            array_merge(['playergroupid' => $cm->instance], $inparams)
        );

        [$sendersql, $senderparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'snd');
        $DB->delete_records_select(
            'playergroup_invites',
            "playergroupid = :pgid1 AND senderid $sendersql",
            array_merge(['pgid1' => $cm->instance], $senderparams)
        );

        [$receiversql, $receiverparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'rcv');
        $DB->delete_records_select(
            'playergroup_invites',
            "playergroupid = :pgid2 AND receiverid $receiversql",
            array_merge(['pgid2' => $cm->instance], $receiverparams)
        );
    }
}
