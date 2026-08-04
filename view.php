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
 * Prints a particular instance of playergroup.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

// Course module ID.
$id  = required_param('id', PARAM_INT);
$tab = optional_param('tab', 'groups', PARAM_ALPHA);

$cm = get_coursemodule_from_id('playergroup', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$playergroup = $DB->get_record('playergroup', ['id' => $cm->instance], '*', MUST_EXIST);

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/playergroup:view', $context);

$PAGE->set_url('/mod/playergroup/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($playergroup->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Record that the student has viewed this activity (completion tracking).
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// Dispatch the course_module_viewed event for Moodle logs.
$event = \mod_playergroup\event\course_module_viewed::create([
    'objectid' => $playergroup->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('playergroup', $playergroup);
$event->trigger();

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($playergroup->name));

// Determine whether the activity is within the configured availability window.
$now = time();
$timeopen  = (int) ($playergroup->timeopen ?? 0);
$timeclose = (int) ($playergroup->timeclose ?? 0);
$isopen = ($timeopen === 0 || $now >= $timeopen) && ($timeclose === 0 || $now <= $timeclose);

// Load all groups linked to this activity instance.
$sql = "SELECT g.id, g.name, g.description, g.descriptionformat, pm.badge, pm.privacy, pm.creatorid,
               COUNT(gm.id) AS membercount
          FROM {playergroup_meta} pm
          JOIN {groups} g ON g.id = pm.groupid
     LEFT JOIN {groups_members} gm ON gm.groupid = g.id
         WHERE pm.playergroupid = :playergroupid
      GROUP BY g.id, g.name, g.description, g.descriptionformat, pm.badge, pm.privacy, pm.creatorid
      ORDER BY g.name ASC";
$grouprecords = $DB->get_records_sql($sql, ['playergroupid' => $playergroup->id]);

// Retrieve the current user's group ID within this activity (false if none).
$mygroupsql = "SELECT gm.groupid
                 FROM {groups_members} gm
                 JOIN {playergroup_meta} pm ON pm.groupid = gm.groupid
                WHERE pm.playergroupid = :playergroupid AND gm.userid = :userid";
$mygroupid = $DB->get_field_sql($mygroupsql, ['playergroupid' => $playergroup->id, 'userid' => $USER->id]);
$hasgroup = !empty($mygroupid);
$isteacher = has_capability('mod/playergroup:manage', $context);
$coursecontext = $context->get_course_context();
$caninviteusers = has_capability('moodle/course:viewparticipants', $coursecontext);

$templatedata = new \stdClass();
$templatedata->cmid               = $cm->id;
$templatedata->hasgroup           = $hasgroup;
$templatedata->activityopen       = $isopen;
$templatedata->caninvite          = false;
$templatedata->hasinviteableusers = false;
$templatedata->inviteableusers    = [];
$templatedata->hasinvites         = false;
$templatedata->receivedinvites    = [];
$templatedata->groups             = [];
$templatedata->isteacher          = $isteacher;
$templatedata->reporturl          = (new moodle_url(
    '/mod/playergroup/view.php',
    ['id' => $cm->id, 'tab' => 'report']
))->out(false);
$templatedata->groupsreporturl    = (new moodle_url(
    '/mod/playergroup/view.php',
    ['id' => $cm->id, 'tab' => 'groupsreport']
))->out(false);

if (!$isopen) {
    if ($timeopen > 0 && $now < $timeopen) {
        $templatedata->availabilitymessage = get_string(
            'activityopensfrom',
            'mod_playergroup',
            userdate($timeopen)
        );
    } else {
        $templatedata->availabilitymessage = get_string('activityclosedmsg', 'mod_playergroup');
    }
}

$canleaveany = $hasgroup && !empty($playergroup->canleave) && $isopen;
$usergroupisfull = false;

// Bulk-load creator profiles to avoid per-card queries.
$creatorids = [];
foreach ($grouprecords as $g) {
    $creatorids[(int) $g->creatorid] = (int) $g->creatorid;
}
$creators = [];
if (!empty($creatorids)) {
    $creatorfields = 'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename';
    $creators = $DB->get_records_list('user', 'id', $creatorids, '', $creatorfields);
}

// Bulk-load all group members (for the "view members" modal) to avoid per-card queries.
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

foreach ($grouprecords as $g) {
    $membercount = (int) $g->membercount;
    $maxmembers  = (int) $playergroup->maxmembers;
    $privacy     = (int) ($g->privacy ?? 0);
    $groupid     = (int) $g->id;
    $isfull      = $membercount >= $maxmembers;
    $ismygroup   = $hasgroup && $groupid === (int) $mygroupid;
    $iscreator   = (int) $g->creatorid === (int) $USER->id;

    $creator = $creators[(int) $g->creatorid] ?? null;
    if ($creator) {
        $leaderbadge = get_string('leadernamed', 'mod_playergroup', fullname($creator));
    } else {
        $leaderbadge = '';
    }

    $groupmembers = [];
    foreach ($membersbygroup[$groupid] ?? [] as $member) {
        $groupmembers[] = [
            'fullname' => fullname($member),
            'isleader' => (int) $member->userid === (int) $g->creatorid,
        ];
    }
    $membersjson = json_encode($groupmembers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    $groupname = format_string($g->name);
    $templatedata->groups[] = [
        'groupid'             => $groupid,
        'name'                => $groupname,
        'rawname'             => $g->name,
        'description'         => format_text($g->description, (int) $g->descriptionformat, ['context' => $context]),
        'rawdescription'      => $g->description ?? '',
        'badge'               => !empty($g->badge) ? $g->badge : '🛡️',
        'membercount'         => $membercount,
        'maxmembers'          => $maxmembers,
        'membersjson'         => $membersjson,
        'privacy'             => $privacy,
        'isprivacyopen'       => $privacy === 0,
        'isprivacyprotected'  => $privacy === 1,
        'isprivacyclosed'     => $privacy === 2,
        'isfull'              => $isfull,
        'ismygroup'           => $ismygroup,
        'leaderbadge'         => $leaderbadge,
        'joinarialabel'       => get_string('joingroup_named', 'mod_playergroup', $groupname),
        'editarialabel'       => get_string('editgroup_named', 'mod_playergroup', $groupname),
        'leavearialabel'      => get_string('leavegroup_named', 'mod_playergroup', $groupname),
        'viewmembersarialabel' => get_string('viewmembers_named', 'mod_playergroup', $groupname),
        'canjoin'             => $isopen && !$hasgroup && $privacy !== 2 && !$isfull,
        'caninvite'           => $ismygroup && $isopen && !$isfull && $caninviteusers,
        'canedit'             => $ismygroup && $isopen && $iscreator,
        'canleave'            => $ismygroup && $canleaveany,
    ];

    if ($ismygroup) {
        $usergroupisfull = $isfull;
    }
}

// Populate invite-related template data after the card loop. Restricted to users who can
// see the course participant list: a professor that hides participants from students has
// already decided students should not browse course-mates by name, and this picker must
// honour that instead of offering an alternate route to the same information.
if ($hasgroup && $isopen && !$usergroupisfull && !empty($mygroupid) && $caninviteusers) {
    $templatedata->caninvite = true;

    // List all other actively enrolled students, including those already in a group
    // or with a pending invite — marked with status flags for the template.
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
        $templatedata->inviteableusers[] = [
            'userid'        => (int) $u->id,
            'fullname'      => fullname($u),
            'ingroup'       => $ingroup,
            'groupname'     => $ingroup ? format_string($u->existinggroupname) : '',
            'invitepending' => $invitepending,
            'caninvite'     => !$ingroup && !$invitepending,
        ];
    }
    $templatedata->hasinviteableusers = !empty($templatedata->inviteableusers);
} else if (!$hasgroup) {
    $invitessql = "SELECT pi.id, pi.senderid, pi.groupid, pi.timecreated,
                          g.name AS groupname, pm.badge
                     FROM {playergroup_invites} pi
                     JOIN {groups} g ON g.id = pi.groupid
                     JOIN {playergroup_meta} pm ON pm.groupid = pi.groupid
                    WHERE pi.receiverid = :receiverid
                      AND pi.playergroupid = :playergroupid
                      AND pi.status = 0
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
            $templatedata->receivedinvites[] = [
                'inviteid'  => (int) $inv->id,
                'groupname' => format_string($inv->groupname),
                'badge'     => !empty($inv->badge) ? $inv->badge : '🛡️',
                'sentby'    => get_string('invitedby', 'mod_playergroup', $sendername),
            ];
        }
        $templatedata->hasinvites = true;
    }
}

/** @var \mod_playergroup\output\renderer $renderer */
$renderer = $PAGE->get_renderer('mod_playergroup');

// Teacher: activity log report.
if ($tab === 'report' && $isteacher) {
    $eventsmap = [
        '\\mod_playergroup\\event\\group_created'  => get_string('event_group_created', 'mod_playergroup'),
        '\\mod_playergroup\\event\\member_joined'  => get_string('event_member_joined', 'mod_playergroup'),
        '\\mod_playergroup\\event\\member_left'    => get_string('event_member_left', 'mod_playergroup'),
        '\\mod_playergroup\\event\\invite_accepted' => get_string('event_invite_accepted', 'mod_playergroup'),
    ];

    [$inevents, $ineventsparams] = $DB->get_in_or_equal(
        array_keys($eventsmap),
        SQL_PARAMS_NAMED,
        'evt'
    );

    $logsql = "SELECT l.id, l.timecreated, l.userid, l.eventname
                 FROM {logstore_standard_log} l
                WHERE l.contextid = :contextid
                  AND l.eventname $inevents
             ORDER BY l.timecreated DESC";

    $logentries = $DB->get_records_sql(
        $logsql,
        array_merge(['contextid' => $context->id], $ineventsparams),
        0,
        200
    );

    $userids = [];
    foreach ($logentries as $entry) {
        $userids[(int) $entry->userid] = (int) $entry->userid;
    }

    $logusers = [];
    if (!empty($userids)) {
        $userfields = 'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename';
        $logusers = $DB->get_records_list('user', 'id', $userids, '', $userfields);
    }

    $rows = [];
    foreach ($logentries as $entry) {
        $loguser = $logusers[(int) $entry->userid] ?? null;
        $rows[] = [
            'date'     => userdate($entry->timecreated),
            'username' => $loguser ? fullname($loguser) : '?',
            'action'   => $eventsmap[$entry->eventname] ?? $entry->eventname,
        ];
    }

    $reportdata = new \stdClass();
    $reportdata->rows    = $rows;
    $reportdata->hasrows = !empty($rows);
    $reportdata->backurl = (new moodle_url('/mod/playergroup/view.php', ['id' => $cm->id]))->out(false);
    $reportdata->exporturl = (new moodle_url('/mod/playergroup/export.php', ['id' => $cm->id]))->out(false);

    echo $renderer->render_activity_report($reportdata);
    echo $OUTPUT->footer();
    exit;
}

// Teacher: groups-and-members composition report. Reuses the group/member data already
// bulk-loaded above for the cards, rather than querying it again.
if ($tab === 'groupsreport' && $isteacher) {
    $groupsreportrows = [];
    foreach ($grouprecords as $g) {
        $groupid = (int) $g->id;
        $privacy = (int) ($g->privacy ?? 0);

        if ($privacy === 1) {
            $privacylabel = get_string('groupprotected', 'mod_playergroup');
        } else if ($privacy === 2) {
            $privacylabel = get_string('groupclosed', 'mod_playergroup');
        } else {
            $privacylabel = get_string('groupopen', 'mod_playergroup');
        }

        foreach ($membersbygroup[$groupid] ?? [] as $member) {
            if ((int) $member->userid === (int) $g->creatorid) {
                $role = get_string('leader', 'mod_playergroup');
            } else {
                $role = get_string('member', 'mod_playergroup');
            }

            $groupsreportrows[] = [
                'groupname'  => format_string($g->name),
                'privacy'    => $privacylabel,
                'role'       => $role,
                'membername' => fullname($member),
            ];
        }
    }

    $groupsreportdata = new \stdClass();
    $groupsreportdata->rows      = $groupsreportrows;
    $groupsreportdata->hasrows   = !empty($groupsreportrows);
    $groupsreportdata->backurl   = (new moodle_url('/mod/playergroup/view.php', ['id' => $cm->id]))->out(false);
    $groupsreportdata->exporturl = (new moodle_url(
        '/mod/playergroup/export.php',
        ['id' => $cm->id, 'type' => 'groups']
    ))->out(false);

    echo $renderer->render_groups_report($groupsreportdata);
    echo $OUTPUT->footer();
    exit;
}

echo $renderer->render_student_view($templatedata);

echo $OUTPUT->footer();
