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
$PAGE->set_pagelayout('incourse');

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

// Determine whether the activity is within the configured availability window.
$now = time();
$timeopen  = (int) ($playergroup->timeopen ?? 0);
$isopen = \mod_playergroup\local\activity_window::is_open($playergroup, $now);

// Load all groups linked to this activity instance.
// The members are counted in a subquery so that the description, a TEXT column, stays out of any GROUP BY:
// not every supported database accepts that.
$sql = "SELECT g.id, g.name, g.description, g.descriptionformat, pm.badge, pm.privacy, pm.creatorid,
               COALESCE(gc.membercount, 0) AS membercount
          FROM {playergroup_meta} pm
          JOIN {groups} g ON g.id = pm.groupid
     LEFT JOIN (SELECT groupid, COUNT(id) AS membercount
                  FROM {groups_members}
              GROUP BY groupid) gc ON gc.groupid = g.id
         WHERE pm.playergroupid = :playergroupid
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
// What the buttons offer must match what the services behind them require: creating and editing need
// creategroup, inviting needs manageinvites and, to pick people, viewing the participants.
$cancreate = has_capability('mod/playergroup:creategroup', $context);
$caninviteusers = \mod_playergroup\local\invitees::can_pick($context);
$canviewnames = \mod_playergroup\local\member_list::can_view_names($context);
$userqualified = \mod_playergroup\local\qualification::is_qualified((int) $playergroup->id, (int) $USER->id);

$templatedata = new \stdClass();
$templatedata->cmid               = $cm->id;
$templatedata->hasgroup           = $hasgroup;
$templatedata->activityopen       = $isopen;
$templatedata->cancreate          = $cancreate;
$templatedata->canclose           = $caninviteusers;
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

// Names of other groups' members and leaders are only loaded when the user may see them, so the
// data never reaches the page (or its data-members attribute) otherwise. The user's own group is
// always shown.
$namegroupids = [];
foreach ($grouprecords as $g) {
    if ($canviewnames || (int) $g->id === (int) $mygroupid) {
        $namegroupids[(int) $g->id] = (int) $g->id;
    }
}

// Bulk-load creator profiles to avoid per-card queries.
$creatorids = [];
foreach ($grouprecords as $g) {
    if (isset($namegroupids[(int) $g->id])) {
        $creatorids[(int) $g->creatorid] = (int) $g->creatorid;
    }
}
$creators = [];
if (!empty($creatorids)) {
    $creatorfields = 'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename';
    $creators = $DB->get_records_list('user', 'id', $creatorids, '', $creatorfields);
}

// Bulk-load all group members (for the "view members" modal) to avoid per-card queries.
$membersbygroup = [];
if (!empty($namegroupids)) {
    [$groupidsinsql, $groupidsinparams] = $DB->get_in_or_equal($namegroupids, SQL_PARAMS_NAMED, 'grp');
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

    // Tell the student why the group does not count yet, instead of leaving them without a grade.
    $missing = $ismygroup
        ? \mod_playergroup\local\qualification::missing_members($playergroup, $membercount, $userqualified)
        : 0;
    $missingmessage = $missing > 0 ? get_string('groupneedsmembers', 'mod_playergroup', $missing) : '';

    $canviewmembers = isset($namegroupids[$groupid]);
    $creator = $canviewmembers ? ($creators[(int) $g->creatorid] ?? null) : null;
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
    $groupmembers = \mod_playergroup\local\member_list::order($groupmembers);
    $membersjson = json_encode($groupmembers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

    // Plain text for everything the template escapes itself; the HTML form is kept only for the
    // data-groupname attribute, which becomes the title of a modal that renders HTML.
    $groupname = \mod_playergroup\local\text::plain($g->name, $context);
    $templatedata->groups[] = [
        'groupid'             => $groupid,
        'name'                => $groupname,
        'namehtml'            => format_string($g->name),
        'rawname'             => $g->name,
        'description'         => format_text($g->description, (int) $g->descriptionformat, ['context' => $context]),
        'rawdescription'      => \mod_playergroup\local\text::description_for_editing(
            $g->description,
            (int) $g->descriptionformat
        ),
        'badge'               => !empty($g->badge) ? $g->badge : '🛡️',
        'membercount'         => $membercount,
        'maxmembers'          => $maxmembers,
        'membersjson'         => $membersjson,
        'canviewmembers'      => $canviewmembers,
        'missingmessage'      => $missingmessage,
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
        'canedit'             => $ismygroup && $isopen && $iscreator && $cancreate,
        'canclose'            => $caninviteusers,
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
    [$enrolledsql, $enrolledparams] = \mod_playergroup\local\invitees::enrolled_sql($context);

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
    $inviteablerecords = \mod_playergroup\local\invitees::filter_available(
        get_fast_modinfo($course)->get_cm($cm->id),
        $inviteablerecords
    );

    foreach ($inviteablerecords as $u) {
        $ingroup       = !empty($u->existinggroupname);
        $invitepending = !$ingroup && !empty($u->pendinginviteid);
        $templatedata->inviteableusers[] = [
            'userid'        => (int) $u->id,
            'fullname'      => fullname($u),
            'ingroup'       => $ingroup,
            'groupname'     => $ingroup ? \mod_playergroup\local\text::plain($u->existinggroupname, $context) : '',
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
                'groupname' => \mod_playergroup\local\text::plain($inv->groupname, $context),
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
    $rows = \mod_playergroup\local\activity_log::get_rows($context->id, 200);

    $reportdata = new \stdClass();
    $reportdata->rows     = $rows ?? [];
    $reportdata->hasrows  = !empty($rows);
    $reportdata->noreader = $rows === null;
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
    foreach (\mod_playergroup\local\groups_report::rows($grouprecords, $membersbygroup, $context) as $row) {
        [$groupname, $privacylabel, $role, $membername] = $row;
        $groupsreportrows[] = [
            'groupname'  => $groupname,
            'privacy'    => $privacylabel,
            'role'       => $role,
            'membername' => $membername,
        ];
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
