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
 * Seed script for manual testing of mod_playergroup (EN).
 *
 * Creates a demo course with 30 students distributed across 6 groups,
 * 3 students without a group, and pending invites for the ungrouped students.
 * Run with --reset to wipe and recreate everything.
 *
 * Usage:
 *   php mod/playergroup/cli/seed.php --password=MyDevPass1!
 *   php mod/playergroup/cli/seed.php --password=MyDevPass1! --reset
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
// Resolve config.php across both Moodle layouts: 5.x public root (4 levels up) and 4.x classic root (3 levels up).
require(file_exists(__DIR__ . '/../../../../config.php')
    ? __DIR__ . '/../../../../config.php'
    : __DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/group/lib.php');

$CFG->noemailever = true;

[$options, $unrecognised] = cli_get_params(
    ['reset' => false, 'help' => false, 'password' => false, 'force' => false],
    ['h' => 'help', 'r' => 'reset', 'p' => 'password', 'f' => 'force']
);

if ($options['help']) {
    cli_writeln("Seed script for mod_playergroup manual testing.\n");
    cli_writeln("Options:");
    cli_writeln("  --password=<value>   Password for all seed user accounts (required).");
    cli_writeln("  --reset              Wipe the demo course and recreate everything.");
    cli_writeln("  --force              Bypass the development-site environment guard.");
    cli_writeln("  --help               Show this message.");
    exit(0);
}

$devpatterns = ['localhost', '127.0.0.1', '.local', '.test'];
$isdev = false;
foreach ($devpatterns as $pattern) {
    if (str_contains($CFG->wwwroot, $pattern)) {
        $isdev = true;
        break;
    }
}
if (!$isdev && !$options['force']) {
    cli_error(
        'This seed script must only be run on development sites. ' .
        '$CFG->wwwroot must contain localhost, 127.0.0.1, .local, or .test. ' .
        'Use --force to bypass this check on development sites with a public domain.'
    );
}

if ($options['password'] === false) {
    cli_error('--password=<value> is required. Example: --password=MyDevPass1!');
}

/** @var string Shortname of the demo course created by this seed script. */
const SEED_COURSE_SHORTNAME = 'playergroup-demo-en';

/** @var string Password for all seed users (set via --password flag). */
define('SEED_PASSWORD', (string) $options['password']);

/** @var string Password for the protected group "Crystal Mages". */
const SEED_GROUP_PASSWORD = 'crystal123';

cli_writeln("=== mod_playergroup seed (EN) ===\n");

// 1. Reset.
if ($options['reset']) {
    $existing = $DB->get_record('course', ['shortname' => SEED_COURSE_SHORTNAME]);
    if ($existing) {
        cli_writeln("Removing existing demo course (id={$existing->id})...");
        // Disable recycle bin temporarily so course deletion skips the backup
        // step — the playergroup module has no backup class yet.
        $recyclebincourse = get_config('tool_recyclebin', 'coursebinenable');
        $recyclebincategory = get_config('tool_recyclebin', 'categorybinenable');
        set_config('coursebinenable', 0, 'tool_recyclebin');
        set_config('categorybinenable', 0, 'tool_recyclebin');
        delete_course($existing, false);
        set_config('coursebinenable', $recyclebincourse, 'tool_recyclebin');
        set_config('categorybinenable', $recyclebincategory, 'tool_recyclebin');
        cli_writeln("Course removed.\n");
    }
    $seedusers = $DB->get_records_sql(
        "SELECT id FROM {user} WHERE username LIKE 'pgen_%' AND deleted = 0"
    );
    foreach ($seedusers as $u) {
        delete_user($DB->get_record('user', ['id' => $u->id]));
    }
    cli_writeln("Seed users removed.\n");
}

// 2. Course.
$course = $DB->get_record('course', ['shortname' => SEED_COURSE_SHORTNAME]);
if ($course) {
    cli_writeln("Demo course already exists (id={$course->id}). Use --reset to recreate.\n");
} else {
    $coursedata = (object) [
        'fullname'    => 'PlayerGroup Demo (EN)',
        'shortname'   => SEED_COURSE_SHORTNAME,
        'summary'     => 'Course created automatically by the PlayerGroup seed script for manual testing.',
        'format'      => 'topics',
        'numsections' => 1,
        'visible'     => 1,
        'category'    => 1,
    ];
    $course = create_course($coursedata);
    cli_writeln("Course created: id={$course->id}");
}

$coursecontext = context_course::instance($course->id);

// 3. Users.

/**
 * Creates a user if it does not already exist.
 *
 * @param string $username Username.
 * @param string $firstname First name.
 * @param string $lastname Last name.
 * @return stdClass User record.
 */
function pgen_ensure_user(string $username, string $firstname, string $lastname): stdClass {
    global $DB, $CFG;

    $existing = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    if ($existing) {
        return $existing;
    }

    $user = (object) [
        'auth'         => 'manual',
        'confirmed'    => 1,
        'policyagreed' => 1,
        'deleted'      => 0,
        'mnethostid'   => $CFG->mnet_localhost_id,
        'username'     => $username,
        'password'     => hash_internal_user_password(SEED_PASSWORD),
        'firstname'    => $firstname,
        'lastname'     => $lastname,
        'email'        => $username . '@playergroup.test',
        'lang'         => 'en',
        'timezone'     => '99',
        'timecreated'  => time(),
        'timemodified' => time(),
    ];
    $user->id = $DB->insert_record('user', $user);
    return $user;
}

$teacher = pgen_ensure_user('pgen_teacher', 'Captain', 'Dungeon');

$studentdata = [
    // Group 1 — The Invincibles (open, 5 members).
    ['pgen_adam', 'Adam', 'Smith'],
    ['pgen_beth', 'Beth', 'Johnson'],
    ['pgen_carl', 'Carl', 'Williams'],
    ['pgen_diana', 'Diana', 'Jones'],
    ['pgen_eric', 'Eric', 'Brown'],
    // Group 2 — Northern Hunters (open, 5 members).
    ['pgen_frank', 'Frank', 'Davis'],
    ['pgen_grace', 'Grace', 'Miller'],
    ['pgen_henry', 'Henry', 'Wilson'],
    ['pgen_isabel', 'Isabel', 'Moore'],
    ['pgen_jack', 'Jack', 'Taylor'],
    // Group 3 — Crystal Mages (protected/password, 5 members).
    ['pgen_kevin', 'Kevin', 'Anderson'],
    ['pgen_laura', 'Laura', 'Thomas'],
    ['pgen_mike', 'Mike', 'Jackson'],
    ['pgen_nancy', 'Nancy', 'White'],
    ['pgen_oliver', 'Oliver', 'Harris'],
    // Group 4 — Warriors of Light (closed/invite-only, 4 members).
    ['pgen_patricia', 'Patricia', 'Martin'],
    ['pgen_quinn', 'Quinn', 'Thompson'],
    ['pgen_rachel', 'Rachel', 'Garcia'],
    ['pgen_sam', 'Sam', 'Martinez'],
    // Group 5 — Eternal Shadows (open, 4 members).
    ['pgen_tyler', 'Tyler', 'Robinson'],
    ['pgen_uma', 'Uma', 'Clark'],
    ['pgen_victor', 'Victor', 'Rodriguez'],
    ['pgen_wendy', 'Wendy', 'Lewis'],
    // Group 6 — Sons of Thunder (open, 4 members).
    ['pgen_xavier', 'Xavier', 'Lee'],
    ['pgen_yvonne', 'Yvonne', 'Walker'],
    ['pgen_zach', 'Zach', 'Hall'],
    ['pgen_amy', 'Amy', 'Allen'],
    // Without a group — have pending invites.
    ['pgen_brian', 'Brian', 'Young'],
    ['pgen_crystal', 'Crystal', 'King'],
    ['pgen_derek', 'Derek', 'Wright'],
];

$students = [];
foreach ($studentdata as [$uname, $fname, $lname]) {
    $students[] = pgen_ensure_user($uname, $fname, $lname);
}
cli_writeln("Users created/found: 1 teacher + " . count($students) . " students.");

// 4. Enrolment.
$enrol = enrol_get_plugin('manual');
$instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
if (!$instance) {
    $instanceid = $enrol->add_default_instance($course);
    $instance = $DB->get_record('enrol', ['id' => $instanceid]);
}

$teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

$enrol->enrol_user($instance, $teacher->id, $teacherrole->id);
foreach ($students as $student) {
    $enrol->enrol_user($instance, $student->id, $studentrole->id);
}
cli_writeln("Enrolments complete.");

// 5. PlayerGroup activity module.
$origuser = $USER;
\core\session\manager::set_user(get_admin());

$moduleid = $DB->get_field('modules', 'id', ['name' => 'playergroup'], MUST_EXIST);

$existingcm = $DB->get_record_sql(
    "SELECT cm.* FROM {course_modules} cm
       JOIN {playergroup} pg ON pg.id = cm.instance
      WHERE cm.course = :course AND pg.name = :name",
    ['course' => $course->id, 'name' => 'Group Formation']
);

if ($existingcm) {
    $cm = get_coursemodule_from_id('playergroup', $existingcm->id, 0, false, MUST_EXIST);
    $pginstance = $DB->get_record('playergroup', ['id' => $cm->instance], '*', MUST_EXIST);
    cli_writeln("PlayerGroup activity already exists (cm id={$cm->id}).");
} else {
    $moduleinfo = (object) [
        'modulename'           => 'playergroup',
        'module'               => $moduleid,
        'course'               => $course->id,
        'section'              => 1,
        'visible'              => 1,
        'name'                 => 'Group Formation',
        'intro'                => '<p>Form your adventurer group!</p>',
        'introformat'          => FORMAT_HTML,
        'minmembers'           => 2,
        'maxmembers'           => 6,
        'canleave'             => 1,
        'deletegroups'         => 0,
        'grade'                => 0,
        'completionjoingroup'  => 0,
        'timeopen'             => 0,
        'timeclose'            => 0,
    ];
    $moduleinfo = add_moduleinfo($moduleinfo, $course, null);
    $cm = get_coursemodule_from_id('playergroup', $moduleinfo->coursemodule, 0, false, MUST_EXIST);
    $pginstance = $DB->get_record('playergroup', ['id' => $cm->instance], '*', MUST_EXIST);
    cli_writeln("PlayerGroup activity created (cm id={$cm->id}, instance id={$pginstance->id}).");
}

\core\session\manager::set_user($origuser);

// 6. Grouping.
if ($pginstance->groupingid > 0 && $DB->record_exists('groupings', ['id' => $pginstance->groupingid])) {
    $groupingid = (int) $pginstance->groupingid;
    cli_writeln("Grouping already exists (id={$groupingid}).");
} else {
    $grouping = (object) [
        'courseid'          => $course->id,
        'name'              => 'Group Formation',
        'description'       => '',
        'descriptionformat' => FORMAT_HTML,
        'timecreated'       => time(),
        'timemodified'      => time(),
    ];
    $groupingid = groups_create_grouping($grouping);
    $DB->set_field('playergroup', 'groupingid', $groupingid, ['id' => $pginstance->id]);
    $pginstance->groupingid = $groupingid;
    cli_writeln("Grouping created (id={$groupingid}).");
}

// 7. Groups.
$now = time();

/**
 * Creates or returns a Moodle native group and its playergroup_meta record.
 *
 * @param stdClass $pginstance PlayerGroup activity record.
 * @param int $groupingid Grouping ID to assign this group to.
 * @param int $courseid Course ID.
 * @param stdClass $creator Student who founds the group.
 * @param string $name Group name.
 * @param string $badge Emoji badge.
 * @param int $privacy 0=open, 1=protected, 2=closed.
 * @param string $description Optional group description.
 * @param string $rawpassword Plain-text password (only used when privacy=1).
 * @return stdClass Native Moodle group record.
 */
function pgen_ensure_group(
    stdClass $pginstance,
    int $groupingid,
    int $courseid,
    stdClass $creator,
    string $name,
    string $badge,
    int $privacy,
    string $description = '',
    string $rawpassword = ''
): stdClass {
    global $DB, $now;

    $nativegroup = $DB->get_record('groups', ['courseid' => $courseid, 'name' => $name]);
    if (!$nativegroup) {
        $g = (object) [
            'courseid'          => $courseid,
            'name'              => $name,
            'description'       => $description,
            'descriptionformat' => FORMAT_HTML,
            'timecreated'       => $now,
            'timemodified'      => $now,
        ];
        $g->id = groups_create_group($g);
        $nativegroup = $DB->get_record('groups', ['id' => $g->id]);
    }

    groups_assign_grouping($groupingid, $nativegroup->id);

    if (!$DB->record_exists('playergroup_meta', ['groupid' => $nativegroup->id, 'playergroupid' => $pginstance->id])) {
        $hashedpassword = '';
        if ($privacy === 1 && $rawpassword !== '') {
            $hashedpassword = password_hash($rawpassword, PASSWORD_DEFAULT);
        }
        $DB->insert_record('playergroup_meta', (object) [
            'groupid'       => $nativegroup->id,
            'playergroupid' => $pginstance->id,
            'creatorid'     => $creator->id,
            'badge'         => $badge,
            'privacy'       => $privacy,
            'password'      => $hashedpassword,
            'timecreated'   => $now,
            'timemodified'  => $now,
        ]);
    }

    return $nativegroup;
}

$group1 = pgen_ensure_group(
    $pginstance,
    $groupingid,
    $course->id,
    $students[0],
    'The Invincibles',
    '🐉',
    0,
    'A band of determined warriors who never back down in the face of danger.'
);
$group2 = pgen_ensure_group(
    $pginstance,
    $groupingid,
    $course->id,
    $students[5],
    'Northern Hunters',
    '⚔️',
    0,
    'Experts in tracking and combat across the frozen lands of the north.'
);
$group3 = pgen_ensure_group(
    $pginstance,
    $groupingid,
    $course->id,
    $students[10],
    'Crystal Mages',
    '🔮',
    1,
    'Scholars of the arcane arts who master the magic of ancient crystals.',
    SEED_GROUP_PASSWORD
);
$group4 = pgen_ensure_group(
    $pginstance,
    $groupingid,
    $course->id,
    $students[15],
    'Warriors of Light',
    '🛡️',
    2,
    'Defenders of order who fight for justice and the protection of the innocent.'
);
$group5 = pgen_ensure_group(
    $pginstance,
    $groupingid,
    $course->id,
    $students[19],
    'Eternal Shadows',
    '🗡️',
    0,
    'Agents of darkness who operate in the shadows with surgical precision.'
);
$group6 = pgen_ensure_group(
    $pginstance,
    $groupingid,
    $course->id,
    $students[23],
    'Sons of Thunder',
    '⚡',
    0,
    'Wild warriors who channel the power of the storm in battle.'
);

cli_writeln("Groups created/found: 6 groups.");

// 8. Group membership.
$groupmembers = [
    $group1->id => array_slice($students, 0, 5),
    $group2->id => array_slice($students, 5, 5),
    $group3->id => array_slice($students, 10, 5),
    $group4->id => array_slice($students, 15, 4),
    $group5->id => array_slice($students, 19, 4),
    $group6->id => array_slice($students, 23, 4),
];

foreach ($groupmembers as $groupid => $members) {
    foreach ($members as $member) {
        if (!$DB->record_exists('groups_members', ['groupid' => $groupid, 'userid' => $member->id])) {
            groups_add_member($groupid, $member->id);
        }
    }
}
cli_writeln("Members added: 27 students distributed across 6 groups.");
cli_writeln("Without a group: Brian Young, Crystal King, Derek Wright.");

// 9. Pending invites.

/**
 * Creates a pending invite if one does not already exist.
 *
 * @param stdClass $pginstance PlayerGroup activity record.
 * @param stdClass $group Native Moodle group record.
 * @param stdClass $sender Student sending the invite.
 * @param stdClass $receiver Student receiving the invite.
 * @return void
 */
function pgen_ensure_invite(
    stdClass $pginstance,
    stdClass $group,
    stdClass $sender,
    stdClass $receiver
): void {
    global $DB, $now;

    $exists = $DB->record_exists('playergroup_invites', [
        'playergroupid' => $pginstance->id,
        'groupid'       => $group->id,
        'receiverid'    => $receiver->id,
        'status'        => 0,
    ]);
    if ($exists) {
        return;
    }

    $DB->insert_record('playergroup_invites', (object) [
        'playergroupid' => $pginstance->id,
        'groupid'       => $group->id,
        'senderid'      => $sender->id,
        'receiverid'    => $receiver->id,
        'status'        => 0,
        'timecreated'   => $now,
        'timemodified'  => $now,
    ]);
}

// Brian (27): 2 pending invites — from The Invincibles and Warriors of Light.
pgen_ensure_invite($pginstance, $group1, $students[0], $students[27]);
pgen_ensure_invite($pginstance, $group4, $students[15], $students[27]);

// Crystal (28): pending invite from Northern Hunters.
pgen_ensure_invite($pginstance, $group2, $students[5], $students[28]);

// Derek (29): pending invite from Crystal Mages.
pgen_ensure_invite($pginstance, $group3, $students[10], $students[29]);

// Cross-group invite to exercise the "already in group" state in the modal.
// Grace (5, in Northern Hunters) invited by The Invincibles.
pgen_ensure_invite($pginstance, $group1, $students[0], $students[5]);

cli_writeln("Pending invites created.");

// 10. Audit log events.
$modulecontext = context_module::instance($cm->id);

$logexists = $DB->record_exists_select(
    'logstore_standard_log',
    'contextid = :contextid AND component = :component',
    ['contextid' => $modulecontext->id, 'component' => 'mod_playergroup']
);

if ($logexists) {
    cli_writeln("Log events already exist for this activity — skipping.");
} else {
    $eventpairs = [];
    $grouppairs = [
        [$group1, array_slice($students, 0, 5)],
        [$group2, array_slice($students, 5, 5)],
        [$group3, array_slice($students, 10, 5)],
        [$group4, array_slice($students, 15, 4)],
        [$group5, array_slice($students, 19, 4)],
        [$group6, array_slice($students, 23, 4)],
    ];
    foreach ($grouppairs as [$group, $members]) {
        $eventpairs[] = ['group_created', $group, $members[0]];
        foreach (array_slice($members, 1) as $member) {
            $eventpairs[] = ['member_joined', $group, $member];
        }
    }

    foreach ($eventpairs as [$eventtype, $group, $user]) {
        \core\session\manager::set_user($user);
        $eventclass = "\\mod_playergroup\\event\\{$eventtype}";
        $eventclass::create([
            'context'  => $modulecontext,
            'objectid' => $group->id,
        ])->trigger();
    }
    \core\session\manager::set_user($origuser);

    // Spread timecreated over the past 7 days — single SQL update, no loop.
    $minid = (int) $DB->get_field_sql(
        'SELECT MIN(id) FROM {logstore_standard_log} WHERE contextid = :ctx AND component = :comp',
        ['ctx' => $modulecontext->id, 'comp' => 'mod_playergroup']
    );
    $maxid = (int) $DB->get_field_sql(
        'SELECT MAX(id) FROM {logstore_standard_log} WHERE contextid = :ctx AND component = :comp',
        ['ctx' => $modulecontext->id, 'comp' => 'mod_playergroup']
    );
    $idrange = max(1, $maxid - $minid);
    $span = 7 * DAYSECS;

    $DB->execute(
        "UPDATE {logstore_standard_log}
            SET timecreated = :base + (id - :minid) * :span / :idrange
          WHERE contextid = :contextid AND component = :component",
        [
            'base'      => $now - $span,
            'minid'     => $minid,
            'span'      => $span,
            'idrange'   => $idrange,
            'contextid' => $modulecontext->id,
            'component' => 'mod_playergroup',
        ]
    );

    $logcount = $DB->count_records_select(
        'logstore_standard_log',
        'contextid = :contextid AND component = :component',
        ['contextid' => $modulecontext->id, 'component' => 'mod_playergroup']
    );
    cli_writeln("Log events created: {$logcount} entries spread over the last 7 days.");
}

// 11. Summary.
$wwwroot = $CFG->wwwroot;
$courseurl = "{$wwwroot}/course/view.php?id={$course->id}";
$activityurl = "{$wwwroot}/mod/playergroup/view.php?id={$cm->id}";

cli_writeln("\n" . str_repeat('=', 70));
cli_writeln("SEED COMPLETE");
cli_writeln(str_repeat('=', 70));
cli_writeln("Course:     {$courseurl}");
cli_writeln("Activity:   {$activityurl}");
cli_writeln("");
cli_writeln("TEACHER");
cli_writeln(str_repeat('-', 70));
cli_writeln(str_pad($teacher->username, 22) . str_pad('Captain Dungeon', 25) . SEED_PASSWORD);
cli_writeln("");
cli_writeln("GROUPS");
cli_writeln(str_repeat('-', 70));

$groupsummary = [
    [$group1, 'The Invincibles', '🐉', 'Open', 5, 'Adam, Beth, Carl, Diana, Eric'],
    [$group2, 'Northern Hunters', '⚔️', 'Open', 5, 'Frank, Grace, Henry, Isabel, Jack'],
    [$group3, 'Crystal Mages', '🔮', 'Password: ' . SEED_GROUP_PASSWORD, 5, 'Kevin, Laura, Mike, Nancy, Oliver'],
    [$group4, 'Warriors of Light', '🛡️', 'Closed', 4, 'Patricia, Quinn, Rachel, Sam'],
    [$group5, 'Eternal Shadows', '🗡️', 'Open', 4, 'Tyler, Uma, Victor, Wendy'],
    [$group6, 'Sons of Thunder', '⚡', 'Open', 4, 'Xavier, Yvonne, Zach, Amy'],
];

foreach ($groupsummary as [$g, $name, $badge, $privacy, $count, $members]) {
    cli_writeln("{$badge} {$name} ({$privacy}, {$count} members)");
    cli_writeln("   {$members}");
}

cli_writeln("");
cli_writeln("WITHOUT A GROUP (pending invites)");
cli_writeln(str_repeat('-', 70));
cli_writeln("pgen_brian    Brian Young   — 2 invites: The Invincibles, Warriors of Light");
cli_writeln("pgen_crystal  Crystal King  — 1 invite: Northern Hunters");
cli_writeln("pgen_derek    Derek Wright  — 1 invite: Crystal Mages");
cli_writeln("");
cli_writeln("All students: password = " . SEED_PASSWORD);
cli_writeln(str_repeat('=', 70));
cli_writeln("To recreate from scratch: php mod/playergroup/cli/seed.php --reset");
