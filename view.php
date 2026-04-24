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
$id = required_param('id', PARAM_INT);

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

$templatedata = new \stdClass();
$templatedata->cmid        = $cm->id;
$templatedata->hasgroup    = $hasgroup;
$templatedata->canleave    = $hasgroup && !empty($playergroup->canleave) && $isopen;
$templatedata->activityopen = $isopen;
$templatedata->groups      = [];
$templatedata->mygroup     = null;

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

foreach ($grouprecords as $g) {
    $membercount = (int) $g->membercount;
    $maxmembers  = (int) $playergroup->maxmembers;
    $privacy     = (int) ($g->privacy ?? 0);
    $groupid     = (int) $g->id;
    $isfull      = $membercount >= $maxmembers;

    $badge = !empty($g->badge) ? $g->badge : '🛡️';
    $card = [
        'groupid'            => $groupid,
        'name'               => format_string($g->name),
        'rawname'            => s($g->name),
        'description'        => format_text($g->description, (int) $g->descriptionformat, ['context' => $context]),
        'rawdescription'     => s($g->description ?? ''),
        'badge'              => $badge,
        'membercount'        => $membercount,
        'maxmembers'         => $maxmembers,
        'privacy'            => $privacy,
        'isprivacyopen'      => $privacy === 0,
        'isprivacyprotected' => $privacy === 1,
        'isprivacyclosed'    => $privacy === 2,
        'isfull'             => $isfull,
        'canjoin'            => $isopen && !$hasgroup && $privacy !== 2 && !$isfull,
        'canedit'            => false,
    ];

    $templatedata->groups[] = $card;

    if ($hasgroup && $groupid === (int) $mygroupid) {
        $mycard           = $card;
        $mycard['canedit'] = $isopen && ((int) $g->creatorid === (int) $USER->id);
        $templatedata->mygroup = $mycard;
    }
}

/** @var \mod_playergroup\output\renderer $renderer */
$renderer = $PAGE->get_renderer('mod_playergroup');
echo $renderer->render_student_view($templatedata);

echo $OUTPUT->footer();
