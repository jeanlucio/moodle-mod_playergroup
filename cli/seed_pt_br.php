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
 * Seed script for manual testing of mod_playergroup (PT-BR).
 *
 * Creates a demo course with 30 students distributed across 6 groups,
 * 3 students without a group, and pending invites for the ungrouped students.
 * Run with --reset to wipe and recreate everything.
 *
 * Usage:
 *   php mod/playergroup/cli/seed_pt_br.php --password=MinhaDevSenha1!
 *   php mod/playergroup/cli/seed_pt_br.php --password=MinhaDevSenha1! --reset
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
    cli_writeln("  --password=<value>   Senha para todas as contas seed (obrigatório).");
    cli_writeln("  --reset              Remove e recria tudo do zero.");
    cli_writeln("  --force              Ignora o guard de ambiente de desenvolvimento.");
    cli_writeln("  --help               Exibe esta mensagem.");
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
        'Este script deve ser executado apenas em ambientes de desenvolvimento. ' .
        '$CFG->wwwroot deve conter localhost, 127.0.0.1, .local ou .test. ' .
        'Use --force para ignorar esta verificação em sites de desenvolvimento com domínio público.'
    );
}

if ($options['password'] === false) {
    cli_error('--password=<valor> é obrigatório. Exemplo: --password=MinhaDevSenha1!');
}

/** @var string Shortname of the demo course created by this seed script. */
const SEED_COURSE_SHORTNAME = 'playergroup-demo-ptbr';

/** @var string Password for all seed users (set via --password flag). */
define('SEED_PASSWORD', (string) $options['password']);

/** @var string Password for the protected group "Magos do Cristal". */
const SEED_GROUP_PASSWORD = 'magico123';

cli_writeln("=== mod_playergroup seed ===\n");

// 1. Reset.
if ($options['reset']) {
    $existing = $DB->get_record('course', ['shortname' => SEED_COURSE_SHORTNAME]);
    if ($existing) {
        cli_writeln("Removendo curso demo existente (id={$existing->id})...");
        // Disable recycle bin temporarily so course deletion skips the backup
        // step — the playergroup module has no backup class yet.
        $recyclebincourse = get_config('tool_recyclebin', 'coursebinenable');
        $recyclebincategory = get_config('tool_recyclebin', 'categorybinenable');
        set_config('coursebinenable', 0, 'tool_recyclebin');
        set_config('categorybinenable', 0, 'tool_recyclebin');
        delete_course($existing, false);
        set_config('coursebinenable', $recyclebincourse, 'tool_recyclebin');
        set_config('categorybinenable', $recyclebincategory, 'tool_recyclebin');
        cli_writeln("Curso removido.\n");
    }
    $seedusers = $DB->get_records_sql(
        "SELECT id FROM {user} WHERE username LIKE 'pgdemo_%' AND deleted = 0"
    );
    foreach ($seedusers as $u) {
        delete_user($DB->get_record('user', ['id' => $u->id]));
    }
    cli_writeln("Usuários seed removidos.\n");
}

// 2. Course.
$course = $DB->get_record('course', ['shortname' => SEED_COURSE_SHORTNAME]);
if ($course) {
    cli_writeln("Curso demo já existe (id={$course->id}). Use --reset para recriar.\n");
} else {
    $coursedata = (object) [
        'fullname'    => 'PlayerGroup Demo (PT-BR)',
        'shortname'   => SEED_COURSE_SHORTNAME,
        'summary'     => 'Curso criado automaticamente pelo seed do PlayerGroup para testes manuais.',
        'format'      => 'topics',
        'numsections' => 1,
        'visible'     => 1,
        'category'    => 1,
    ];
    $course = create_course($coursedata);
    cli_writeln("Curso criado: id={$course->id}");
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
function pgdemo_ensure_user(string $username, string $firstname, string $lastname): stdClass {
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
        'lang'         => 'pt_br',
        'timezone'     => '99',
        'timecreated'  => time(),
        'timemodified' => time(),
    ];
    $user->id = $DB->insert_record('user', $user);
    return $user;
}

$teacher = pgdemo_ensure_user('pgdemo_teacher', 'Mestre', 'Dungeon');

$studentdata = [
    // Group 1 — Os Invencíveis (open, 5 members).
    ['pgdemo_ana', 'Ana', 'Silva'],
    ['pgdemo_bruno', 'Bruno', 'Costa'],
    ['pgdemo_carlos', 'Carlos', 'Mendes'],
    ['pgdemo_daniela', 'Daniela', 'Rocha'],
    ['pgdemo_eduardo', 'Eduardo', 'Lima'],
    // Group 2 — Caçadores do Norte (open, 5 members).
    ['pgdemo_fernanda', 'Fernanda', 'Oliveira'],
    ['pgdemo_gabriel', 'Gabriel', 'Santos'],
    ['pgdemo_helena', 'Helena', 'Cruz'],
    ['pgdemo_igor', 'Igor', 'Ferreira'],
    ['pgdemo_juliana', 'Juliana', 'Gomes'],
    // Group 3 — Magos do Cristal (protected/password, 5 members).
    ['pgdemo_kevin', 'Kevin', 'Alves'],
    ['pgdemo_larissa', 'Larissa', 'Pereira'],
    ['pgdemo_marcos', 'Marcos', 'Souza'],
    ['pgdemo_natalia', 'Natalia', 'Ribeiro'],
    ['pgdemo_otavio', 'Otavio', 'Cardoso'],
    // Group 4 — Guerreiros da Luz (closed/invite-only, 4 members).
    ['pgdemo_paula', 'Paula', 'Martins'],
    ['pgdemo_rafael', 'Rafael', 'Araujo'],
    ['pgdemo_sabrina', 'Sabrina', 'Nunes'],
    ['pgdemo_tiago', 'Tiago', 'Barbosa'],
    // Group 5 — Sombras Eternas (open, 4 members).
    ['pgdemo_ursula', 'Ursula', 'Campos'],
    ['pgdemo_vitor', 'Vitor', 'Freitas'],
    ['pgdemo_wanda', 'Wanda', 'Carvalho'],
    ['pgdemo_lucas', 'Lucas', 'Moreira'],
    // Group 6 — Filhos do Trovão (open, 4 members).
    ['pgdemo_yasmin', 'Yasmin', 'Cavalcanti'],
    ['pgdemo_pedro', 'Pedro', 'Monteiro'],
    ['pgdemo_alexandre', 'Alexandre', 'Pinto'],
    ['pgdemo_beatriz', 'Beatriz', 'Leite'],
    // Without a group — have pending invites.
    ['pgdemo_caio', 'Caio', 'Figueiredo'],
    ['pgdemo_diana', 'Diana', 'Teixeira'],
    ['pgdemo_estevao', 'Estevao', 'Borges'],
];

$students = [];
foreach ($studentdata as [$uname, $fname, $lname]) {
    $students[] = pgdemo_ensure_user($uname, $fname, $lname);
}
cli_writeln("Usuários criados/encontrados: 1 professor + " . count($students) . " alunos.");

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
cli_writeln("Matrículas concluídas.");

// 5. PlayerGroup activity module.
$origuser = $USER;
\core\session\manager::set_user(get_admin());

$moduleid = $DB->get_field('modules', 'id', ['name' => 'playergroup'], MUST_EXIST);

$existingcm = $DB->get_record_sql(
    "SELECT cm.* FROM {course_modules} cm
       JOIN {playergroup} pg ON pg.id = cm.instance
      WHERE cm.course = :course AND pg.name = :name",
    ['course' => $course->id, 'name' => 'Formação de Grupos']
);

if ($existingcm) {
    $cm = get_coursemodule_from_id('playergroup', $existingcm->id, 0, false, MUST_EXIST);
    $pginstance = $DB->get_record('playergroup', ['id' => $cm->instance], '*', MUST_EXIST);
    cli_writeln("Atividade PlayerGroup já existe (cm id={$cm->id}).");
} else {
    $moduleinfo = (object) [
        'modulename'           => 'playergroup',
        'module'               => $moduleid,
        'course'               => $course->id,
        'section'              => 1,
        'visible'              => 1,
        'name'                 => 'Formação de Grupos',
        'intro'                => '<p>Forme seu grupo de aventureiros!</p>',
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
    cli_writeln("Atividade PlayerGroup criada (cm id={$cm->id}, instance id={$pginstance->id}).");
}

\core\session\manager::set_user($origuser);

// 6. Grouping.
if ($pginstance->groupingid > 0 && $DB->record_exists('groupings', ['id' => $pginstance->groupingid])) {
    $groupingid = (int) $pginstance->groupingid;
    cli_writeln("Grouping já existe (id={$groupingid}).");
} else {
    $grouping = (object) [
        'courseid'          => $course->id,
        'name'              => 'Formação de Grupos',
        'description'       => '',
        'descriptionformat' => FORMAT_HTML,
        'timecreated'       => time(),
        'timemodified'      => time(),
    ];
    $groupingid = groups_create_grouping($grouping);
    $DB->set_field('playergroup', 'groupingid', $groupingid, ['id' => $pginstance->id]);
    $pginstance->groupingid = $groupingid;
    cli_writeln("Grouping criado (id={$groupingid}).");
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
function pgdemo_ensure_group(
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
            'groupid'        => $nativegroup->id,
            'playergroupid'  => $pginstance->id,
            'creatorid'      => $creator->id,
            'badge'          => $badge,
            'privacy'        => $privacy,
            'password'       => $hashedpassword,
            'timecreated'    => $now,
            'timemodified'   => $now,
        ]);
    }

    return $nativegroup;
}

$group1 = pgdemo_ensure_group(
    $pginstance,
    $groupingid,
    $course->id,
    $students[0],
    'Os Invencíveis',
    '🐉',
    0,
    'Um grupo de guerreiros determinados que nunca recuam diante do perigo.'
);
$group2 = pgdemo_ensure_group(
    $pginstance,
    $groupingid,
    $course->id,
    $students[5],
    'Caçadores do Norte',
    '⚔️',
    0,
    'Especialistas em rastreamento e combate nas terras geladas do norte.'
);
$group3 = pgdemo_ensure_group(
    $pginstance,
    $groupingid,
    $course->id,
    $students[10],
    'Magos do Cristal',
    '🔮',
    1,
    'Estudiosos das artes arcanas que dominam a magia dos cristais ancestrais.',
    SEED_GROUP_PASSWORD
);
$group4 = pgdemo_ensure_group(
    $pginstance,
    $groupingid,
    $course->id,
    $students[15],
    'Guerreiros da Luz',
    '🛡️',
    2,
    'Defensores da ordem que lutam pela justiça e proteção dos inocentes.'
);
$group5 = pgdemo_ensure_group(
    $pginstance,
    $groupingid,
    $course->id,
    $students[19],
    'Sombras Eternas',
    '🗡️',
    0,
    'Agentes das trevas que operam nas sombras com precisão cirúrgica.'
);
$group6 = pgdemo_ensure_group(
    $pginstance,
    $groupingid,
    $course->id,
    $students[23],
    'Filhos do Trovão',
    '⚡',
    0,
    'Guerreiros selvagens que canalizam o poder da tempestade em combate.'
);

cli_writeln("Grupos criados/encontrados: 6 grupos.");

// 8. Group membership.
$groupmembers = [
    $group1->id => array_slice($students, 0, 5),
    $group2->id => array_slice($students, 5, 5),
    $group3->id => array_slice($students, 10, 5),
    $group4->id => array_slice($students, 15, 4),
    $group5->id => array_slice($students, 19, 4),
    $group6->id => array_slice($students, 23, 4),
];

[$groupidsql, $groupidparams] = $DB->get_in_or_equal(array_keys($groupmembers), SQL_PARAMS_NAMED);
$existingmembers = $DB->get_records_sql(
    "SELECT id, groupid, userid FROM {groups_members} WHERE groupid $groupidsql",
    $groupidparams
);
$existingpairs = [];
foreach ($existingmembers as $existingmember) {
    $existingpairs[$existingmember->groupid . '_' . $existingmember->userid] = true;
}

foreach ($groupmembers as $groupid => $members) {
    foreach ($members as $member) {
        if (!isset($existingpairs[$groupid . '_' . $member->id])) {
            groups_add_member($groupid, $member->id);
        }
    }
}
cli_writeln("Membros adicionados: 27 alunos distribuídos nos 6 grupos.");
cli_writeln("Sem grupo: Caio Figueiredo, Diana Teixeira, Estevao Borges.");

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
function pgdemo_ensure_invite(
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

// Caio (27): 2 pending invites — from Os Invencíveis and Guerreiros da Luz.
pgdemo_ensure_invite($pginstance, $group1, $students[0], $students[27]);
pgdemo_ensure_invite($pginstance, $group4, $students[15], $students[27]);

// Diana (28): pending invite from Caçadores do Norte.
pgdemo_ensure_invite($pginstance, $group2, $students[5], $students[28]);

// Estevao (29): pending invite from Magos do Cristal.
pgdemo_ensure_invite($pginstance, $group3, $students[10], $students[29]);

// Also add a few cross-group invites to exercise the "already in group" state in the modal.
// Fernanda (5, in Caçadores) invited by Os Invencíveis — she has a group so the modal shows her tag.
pgdemo_ensure_invite($pginstance, $group1, $students[0], $students[5]);

cli_writeln("Convites pendentes criados.");

// 10. Audit log events.
$modulecontext = context_module::instance($cm->id);

$logexists = $DB->record_exists_select(
    'logstore_standard_log',
    'contextid = :contextid AND component = :component',
    ['contextid' => $modulecontext->id, 'component' => 'mod_playergroup']
);

if ($logexists) {
    cli_writeln("Eventos de log já existem para esta atividade — ignorando.");
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
    cli_writeln("Eventos de log criados: {$logcount} entradas distribuídas nos últimos 7 dias.");
}

// 11. Summary.
$wwwroot = $CFG->wwwroot;
$courseurl = "{$wwwroot}/course/view.php?id={$course->id}";
$activityurl = "{$wwwroot}/mod/playergroup/view.php?id={$cm->id}";

cli_writeln("\n" . str_repeat('=', 70));
cli_writeln("SEED CONCLUÍDO");
cli_writeln(str_repeat('=', 70));
cli_writeln("Curso:      {$courseurl}");
cli_writeln("Atividade:  {$activityurl}");
cli_writeln("");
cli_writeln("PROFESSOR");
cli_writeln(str_repeat('-', 70));
cli_writeln(str_pad($teacher->username, 22) . str_pad('Mestre Dungeon', 25) . SEED_PASSWORD);
cli_writeln("");
cli_writeln("GRUPOS");
cli_writeln(str_repeat('-', 70));

$groupsummary = [
    [$group1, 'Os Invencíveis', '🐉', 'Aberto', 5, 'Ana, Bruno, Carlos, Daniela, Eduardo'],
    [$group2, 'Caçadores do Norte', '⚔️', 'Aberto', 5, 'Fernanda, Gabriel, Helena, Igor, Juliana'],
    [$group3, 'Magos do Cristal', '🔮', 'Senha: ' . SEED_GROUP_PASSWORD, 5, 'Kevin, Larissa, Marcos, Natalia, Otavio'],
    [$group4, 'Guerreiros da Luz', '🛡️', 'Fechado', 4, 'Paula, Rafael, Sabrina, Tiago'],
    [$group5, 'Sombras Eternas', '🗡️', 'Aberto', 4, 'Ursula, Vitor, Wanda, Lucas'],
    [$group6, 'Filhos do Trovão', '⚡', 'Aberto', 4, 'Yasmin, Pedro, Alexandre, Beatriz'],
];

foreach ($groupsummary as [$g, $name, $badge, $privacy, $count, $members]) {
    cli_writeln("{$badge} {$name} ({$privacy}, {$count} membros)");
    cli_writeln("   {$members}");
}

cli_writeln("");
cli_writeln("SEM GRUPO (convites pendentes)");
cli_writeln(str_repeat('-', 70));
cli_writeln("pgdemo_caio      Caio Figueiredo  — 2 convites: Os Invencíveis, Guerreiros da Luz");
cli_writeln("pgdemo_diana     Diana Teixeira   — 1 convite: Caçadores do Norte");
cli_writeln("pgdemo_estevao   Estevao Borges   — 1 convite: Magos do Cristal");
cli_writeln("");
cli_writeln("Todos os alunos: senha = " . SEED_PASSWORD);
cli_writeln(str_repeat('=', 70));
cli_writeln("Para recriar tudo do zero: php mod/playergroup/cli/seed_pt_br.php --reset");
