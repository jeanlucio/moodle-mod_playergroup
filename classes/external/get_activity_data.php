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
 * External function to retrieve all data needed to render the mobile view.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;

/**
 * Class get_activity_data
 * Returns the complete data set required to render the PlayerGroup activity in the mobile app.
 */
class get_activity_data extends external_api {
    /**
     * Defines the input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    /**
     * Returns all data needed to render the activity in the mobile app.
     *
     * @param int $cmid Course module ID.
     * @return array
     * @throws \moodle_exception
     */
    public static function execute(int $cmid): array {
        global $DB, $USER, $CFG;

        require_once($CFG->dirroot . '/mod/playergroup/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('mod/playergroup:view', $context);

        $cm = get_coursemodule_from_id('playergroup', $params['cmid'], 0, false, MUST_EXIST);
        $playergroup = $DB->get_record('playergroup', ['id' => $cm->instance], '*', MUST_EXIST);

        $now = time();
        $timeopen  = (int) ($playergroup->timeopen ?? 0);
        $timeclose = (int) ($playergroup->timeclose ?? 0);
        $isopen = ($timeopen === 0 || $now >= $timeopen) && ($timeclose === 0 || $now <= $timeclose);

        $availabilitymessage = '';
        if (!$isopen) {
            if ($timeopen > 0 && $now < $timeopen) {
                $availabilitymessage = get_string('activityopensfrom', 'mod_playergroup', userdate($timeopen));
            } else {
                $availabilitymessage = get_string('activityclosedmsg', 'mod_playergroup');
            }
        }

        $isteacher = has_capability('mod/playergroup:manage', $context);
        $coursecontext = $context->get_course_context();
        $caninviteusers = has_capability('moodle/course:viewparticipants', $coursecontext);

        $mygroupsql = "SELECT gm.groupid
                         FROM {groups_members} gm
                         JOIN {playergroup_meta} pm ON pm.groupid = gm.groupid
                        WHERE pm.playergroupid = :playergroupid AND gm.userid = :userid";
        $mygroupid = $DB->get_field_sql($mygroupsql, ['playergroupid' => $playergroup->id, 'userid' => $USER->id]);
        $hasgroup = !empty($mygroupid);

        $groupsql = "SELECT g.id, g.name, g.description, g.descriptionformat,
                            pm.badge, pm.privacy, pm.creatorid,
                            COUNT(gm.id) AS membercount
                       FROM {playergroup_meta} pm
                       JOIN {groups} g ON g.id = pm.groupid
                  LEFT JOIN {groups_members} gm ON gm.groupid = g.id
                      WHERE pm.playergroupid = :playergroupid
                   GROUP BY g.id, g.name, g.description, g.descriptionformat,
                            pm.badge, pm.privacy, pm.creatorid
                   ORDER BY g.name ASC";
        $grouprecords = $DB->get_records_sql($groupsql, ['playergroupid' => $playergroup->id]);

        $creatorids = [];
        foreach ($grouprecords as $g) {
            $creatorids[(int) $g->creatorid] = (int) $g->creatorid;
        }
        $creators = [];
        if (!empty($creatorids)) {
            $creatorfields = 'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename';
            $creators = $DB->get_records_list('user', 'id', $creatorids, '', $creatorfields);
        }

        // Bulk-load all group members (for the "view members" panel) to avoid per-card queries.
        $membersbygroup = [];
        $displayedgroupids = array_map(fn($g): int => (int) $g->id, $grouprecords);
        if (!empty($displayedgroupids)) {
            [$groupidsinsql, $groupidsinparams] = $DB->get_in_or_equal($displayedgroupids, SQL_PARAMS_NAMED, 'grp');
            $membersql = "SELECT gm.id, gm.groupid, u.id AS userid, u.firstname, u.lastname, u.firstnamephonetic,
                                 u.lastnamephonetic, u.middlename, u.alternatename
                            FROM {groups_members} gm
                            JOIN {user} u ON u.id = gm.userid
                           WHERE gm.groupid $groupidsinsql
                        ORDER BY gm.groupid ASC, gm.timeadded ASC";
            foreach ($DB->get_records_sql($membersql, $groupidsinparams) as $member) {
                $membersbygroup[(int) $member->groupid][] = $member;
            }
        }

        $canleaveany = $hasgroup && !empty($playergroup->canleave) && $isopen;
        $usergroupisfull = false;
        $groups = [];

        foreach ($grouprecords as $g) {
            $membercount = (int) $g->membercount;
            $maxmembers  = (int) $playergroup->maxmembers;
            $privacy     = (int) ($g->privacy ?? 0);
            $groupid     = (int) $g->id;
            $isfull      = $membercount >= $maxmembers;
            $ismygroup   = $hasgroup && $groupid === (int) $mygroupid;
            $iscreator   = (int) $g->creatorid === (int) $USER->id;

            $creator = $creators[(int) $g->creatorid] ?? null;
            $leaderbadge = $creator ? get_string('leadernamed', 'mod_playergroup', fullname($creator)) : '';

            $groupname = format_string($g->name);
            $groupmembers = [];
            foreach ($membersbygroup[$groupid] ?? [] as $member) {
                $groupmembers[] = [
                    'fullname' => fullname($member),
                    'isleader' => (int) $member->userid === (int) $g->creatorid,
                ];
            }
            $groupmembers = \mod_playergroup\local\member_list::order($groupmembers);

            $groups[] = [
                'groupid'            => $groupid,
                'name'               => $groupname,
                'rawname'            => $g->name,
                'description'        => format_text(
                    $g->description,
                    (int) $g->descriptionformat,
                    ['context' => $context]
                ),
                'rawdescription'     => $g->description ?? '',
                'badge'              => !empty($g->badge) ? $g->badge : '🛡️',
                'membercount'        => $membercount,
                'maxmembers'         => $maxmembers,
                'members'            => $groupmembers,
                'membersheading'     => get_string('groupmembers_named', 'mod_playergroup', $groupname),
                'privacy'            => $privacy,
                'isprivacyopen'      => $privacy === 0,
                'isprivacyprotected' => $privacy === 1,
                'isprivacyclosed'    => $privacy === 2,
                'isfull'             => $isfull,
                'ismygroup'          => $ismygroup,
                'leaderbadge'        => $leaderbadge,
                'canjoin'            => $isopen && !$hasgroup && $privacy !== 2 && !$isfull,
                'caninvite'          => $ismygroup && $isopen && !$isfull && $caninviteusers,
                'canedit'            => $ismygroup && $isopen && $iscreator,
                'canleave'           => $ismygroup && $canleaveany,
            ];

            if ($ismygroup) {
                $usergroupisfull = $isfull;
            }
        }

        $receivedinvites = [];
        $inviteableusers = [];

        // Restricted to users who can see the course participant list: a professor that
        // hides participants from students has already decided students should not browse
        // course-mates by name, and this picker must honour that choice.
        if ($hasgroup && $isopen && !$usergroupisfull && !empty($mygroupid) && $caninviteusers) {
            [$enrolledsql, $enrolledparams] = get_enrolled_sql($coursecontext, '', 0, true);

            $inviteablesql = "SELECT u.id, u.firstname, u.lastname, u.firstnamephonetic,
                                     u.lastnamephonetic, u.middlename, u.alternatename,
                                     g_their.name AS existinggroupname,
                                     pi_pend.id   AS pendinginviteid
                                FROM {user} u
                           LEFT JOIN (
                                     SELECT gm_s.userid, MIN(g_s.name) AS name
                                       FROM {groups_members} gm_s
                                       JOIN {playergroup_meta} pm_s ON pm_s.groupid = gm_s.groupid
                                       JOIN {groups} g_s            ON g_s.id = gm_s.groupid
                                      WHERE pm_s.playergroupid = :playergroupid2
                                      GROUP BY gm_s.userid
                                     ) g_their ON g_their.userid = u.id
                           LEFT JOIN (
                                     SELECT pi_s.receiverid, MIN(pi_s.id) AS id
                                       FROM {playergroup_invites} pi_s
                                      WHERE pi_s.groupid = :groupid
                                        AND pi_s.status  = 0
                                      GROUP BY pi_s.receiverid
                                     ) pi_pend ON pi_pend.receiverid = u.id
                               WHERE u.id IN ($enrolledsql)
                                 AND u.deleted   = 0
                                 AND u.suspended = 0
                                 AND u.id        <> :currentuserid
                            ORDER BY u.firstname ASC, u.lastname ASC";

            $inviteableparams = array_merge($enrolledparams, [
                'currentuserid'  => $USER->id,
                'playergroupid2' => $playergroup->id,
                'groupid'        => (int) $mygroupid,
            ]);
            $inviteablerecords = $DB->get_records_sql($inviteablesql, $inviteableparams);

            foreach ($inviteablerecords as $u) {
                $ingroup       = !empty($u->existinggroupname);
                $invitepending = !$ingroup && !empty($u->pendinginviteid);
                $inviteableusers[] = [
                    'userid'        => (int) $u->id,
                    'fullname'      => fullname($u),
                    'ingroup'       => $ingroup,
                    'groupname'     => $ingroup ? format_string($u->existinggroupname) : '',
                    'invitepending' => $invitepending,
                    'caninvite'     => !$ingroup && !$invitepending,
                ];
            }
        } else if (!$hasgroup) {
            $invitessql = "SELECT pi.id, pi.senderid, pi.groupid, pi.timecreated,
                                  g.name AS groupname, pm.badge
                             FROM {playergroup_invites} pi
                             JOIN {groups} g ON g.id = pi.groupid
                             JOIN {playergroup_meta} pm ON pm.groupid = pi.groupid
                            WHERE pi.receiverid    = :receiverid
                              AND pi.playergroupid = :playergroupid
                              AND pi.status        = 0
                         ORDER BY pi.timecreated DESC";

            $inviterecords = $DB->get_records_sql($invitessql, [
                'receiverid'    => $USER->id,
                'playergroupid' => $playergroup->id,
            ]);

            if (!empty($inviterecords)) {
                $senderids = [];
                foreach ($inviterecords as $inv) {
                    $senderids[(int) $inv->senderid] = (int) $inv->senderid;
                }
                $senderfields = 'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename';
                $senders = $DB->get_records_list('user', 'id', $senderids, '', $senderfields);

                foreach ($inviterecords as $inv) {
                    $sender = $senders[(int) $inv->senderid] ?? null;
                    $sendername = $sender ? fullname($sender) : '';
                    $receivedinvites[] = [
                        'inviteid'  => (int) $inv->id,
                        'groupname' => format_string($inv->groupname),
                        'badge'     => !empty($inv->badge) ? $inv->badge : '🛡️',
                        'sentby'    => get_string('invitedby', 'mod_playergroup', $sendername),
                    ];
                }
            }
        }

        return [
            'activityid'          => (int) $playergroup->id,
            'name'                => format_string($playergroup->name),
            'intro'               => format_module_intro('playergroup', $playergroup, $params['cmid']),
            'maxmembers'          => (int) $playergroup->maxmembers,
            'canleave'            => !empty($playergroup->canleave),
            'timeopen'            => $timeopen,
            'timeclose'           => $timeclose,
            'activityopen'        => $isopen,
            'availabilitymessage' => $availabilitymessage,
            'hasgroup'            => $hasgroup,
            'mygroupid'           => $hasgroup ? (int) $mygroupid : 0,
            'isteacher'           => $isteacher,
            'groups'              => $groups,
            'receivedinvites'     => $receivedinvites,
            'inviteableusers'     => $inviteableusers,
            'warnings'            => [],
        ];
    }

    /**
     * Defines the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $memberstructure = new external_single_structure([
            'fullname' => new external_value(PARAM_TEXT, 'Member full name'),
            'isleader' => new external_value(PARAM_BOOL, 'True if this member created the group'),
        ]);

        $groupstructure = new external_single_structure([
            'groupid'            => new external_value(PARAM_INT, 'Group ID'),
            'name'               => new external_value(PARAM_TEXT, 'Group name (formatted)'),
            'rawname'            => new external_value(PARAM_TEXT, 'Group name (escaped)'),
            'description'        => new external_value(PARAM_RAW, 'Group description (HTML)'),
            'rawdescription'     => new external_value(PARAM_TEXT, 'Group description (escaped)'),
            'badge'              => new external_value(PARAM_TEXT, 'Group badge (emoji or text)'),
            'membercount'        => new external_value(PARAM_INT, 'Current number of members'),
            'maxmembers'         => new external_value(PARAM_INT, 'Maximum allowed members'),
            'members'            => new external_multiple_structure($memberstructure, 'List of group members'),
            'membersheading'     => new external_value(PARAM_TEXT, 'Title for the members list ("Members of X")'),
            'privacy'            => new external_value(PARAM_INT, 'Privacy level: 0=open, 1=protected, 2=closed'),
            'isprivacyopen'      => new external_value(PARAM_BOOL, 'True if privacy is open'),
            'isprivacyprotected' => new external_value(PARAM_BOOL, 'True if privacy is password-protected'),
            'isprivacyclosed'    => new external_value(PARAM_BOOL, 'True if privacy is closed (invite only)'),
            'isfull'             => new external_value(PARAM_BOOL, 'True if group is at capacity'),
            'ismygroup'          => new external_value(PARAM_BOOL, 'True if this is the user\'s group'),
            'leaderbadge'        => new external_value(PARAM_TEXT, 'Leader display label'),
            'canjoin'            => new external_value(PARAM_BOOL, 'True if current user can join this group'),
            'caninvite'          => new external_value(PARAM_BOOL, 'True if current user can send invites from this group'),
            'canedit'            => new external_value(PARAM_BOOL, 'True if current user can edit this group'),
            'canleave'           => new external_value(PARAM_BOOL, 'True if current user can leave this group'),
        ]);

        $invitestructure = new external_single_structure([
            'inviteid'  => new external_value(PARAM_INT, 'Invitation ID'),
            'groupname' => new external_value(PARAM_TEXT, 'Name of the group that sent the invite'),
            'badge'     => new external_value(PARAM_TEXT, 'Group badge'),
            'sentby'    => new external_value(PARAM_TEXT, 'Sender display label'),
        ]);

        $inviteableuserstructure = new external_single_structure([
            'userid'        => new external_value(PARAM_INT, 'User ID'),
            'fullname'      => new external_value(PARAM_TEXT, 'User full name'),
            'ingroup'       => new external_value(PARAM_BOOL, 'True if user already belongs to a group'),
            'groupname'     => new external_value(PARAM_TEXT, 'Name of the group the user belongs to (if any)'),
            'invitepending' => new external_value(PARAM_BOOL, 'True if an invite is already pending for this user'),
            'caninvite'     => new external_value(PARAM_BOOL, 'True if the current user can invite this person'),
        ]);

        return new external_single_structure([
            'activityid'          => new external_value(PARAM_INT, 'Activity instance ID'),
            'name'                => new external_value(PARAM_TEXT, 'Activity name'),
            'intro'               => new external_value(PARAM_RAW, 'Activity intro/description HTML'),
            'maxmembers'          => new external_value(PARAM_INT, 'Max members per group'),
            'canleave'            => new external_value(PARAM_BOOL, 'Whether students can leave groups'),
            'timeopen'            => new external_value(PARAM_INT, 'Unix timestamp when activity opens (0 = always)'),
            'timeclose'           => new external_value(PARAM_INT, 'Unix timestamp when activity closes (0 = never)'),
            'activityopen'        => new external_value(PARAM_BOOL, 'True if the activity is currently available'),
            'availabilitymessage' => new external_value(PARAM_TEXT, 'Message shown when not available'),
            'hasgroup'            => new external_value(PARAM_BOOL, 'True if the current user belongs to a group'),
            'mygroupid'           => new external_value(PARAM_INT, 'Current user\'s group ID (0 if none)'),
            'isteacher'           => new external_value(PARAM_BOOL, 'True if the current user is a teacher/manager'),
            'groups'              => new external_multiple_structure($groupstructure, 'List of all groups in this activity'),
            'receivedinvites'     => new external_multiple_structure($invitestructure, 'Pending invitations received by the user'),
            'inviteableusers'     => new external_multiple_structure(
                $inviteableuserstructure,
                'Users the current user can invite to their group'
            ),
            'warnings'            => new external_warnings(),
        ]);
    }
}
