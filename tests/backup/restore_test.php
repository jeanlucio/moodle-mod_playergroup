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
 * Backup and restore tests for mod_playergroup.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\backup;

use advanced_testcase;
use backup;
use backup_controller;
use backup_setting;
use restore_controller;
use restore_dbops;
use stdClass;

/**
 * Tests for the backup and restore cycle of mod_playergroup.
 *
 * Covers: playergroup instance fields, playergroup_meta (content — always),
 * and playergroup_invites (user data — only when userinfo is enabled).
 *
 * @covers \backup_playergroup_activity_structure_step
 * @covers \restore_playergroup_activity_structure_step
 * @covers \restore_playergroup_activity_task
 */
final class restore_test extends advanced_testcase {
    /**
     * Load backup/restore API before any test in this class.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
    }

    /**
     * Backup without userinfo: only the activity itself is restored. The groups the students created
     * (playergroup_meta, with their leader and password) and the invites are user data and stay out.
     */
    public function test_backup_restore_content_only(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $activity] = $this->create_fixture(withusers: true);

        $newcourseid = $this->backup_and_restore($course, false);

        // Exactly one playergroup in the new course.
        $this->assertSame(1, $DB->count_records('playergroup', ['course' => $newcourseid]));

        $newactivity = $DB->get_record('playergroup', ['course' => $newcourseid], '*', MUST_EXIST);

        // Activity fields preserved.
        $this->assertSame($activity->name, $newactivity->name);
        $this->assertSame((int) $activity->minmembers, (int) $newactivity->minmembers);
        $this->assertSame((int) $activity->maxmembers, (int) $newactivity->maxmembers);
        $this->assertSame((int) $activity->canleave, (int) $newactivity->canleave);
        $this->assertSame((int) $activity->deletegroups, (int) $newactivity->deletegroups);

        // The students' groups are user data: restoring them without the students would bring back
        // groups nobody leads, locked behind a password nobody knows.
        $this->assertSame(0, $DB->count_records('playergroup_meta', ['playergroupid' => $newactivity->id]));

        // Invites must NOT be present (no userinfo).
        $this->assertSame(0, $DB->count_records('playergroup_invites', ['playergroupid' => $newactivity->id]));
    }

    /**
     * Backup with userinfo: playergroup, playergroup_meta AND playergroup_invites
     * are all restored with correctly remapped IDs.
     */
    public function test_backup_restore_with_userinfo(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $activity, $groupid, , $sender, $receiver] = $this->create_fixture(withusers: true);

        $newcourseid = $this->backup_and_restore($course, true);

        $this->assertSame(1, $DB->count_records('playergroup', ['course' => $newcourseid]));

        $newactivity = $DB->get_record('playergroup', ['course' => $newcourseid], '*', MUST_EXIST);

        // Meta present.
        $this->assertSame(1, $DB->count_records('playergroup_meta', ['playergroupid' => $newactivity->id]));

        $newmeta = $DB->get_record('playergroup_meta', ['playergroupid' => $newactivity->id], '*', MUST_EXIST);
        $this->assertSame('⚔', $newmeta->badge);
        $this->assertGreaterThan(0, (int) $newmeta->groupid);
        $this->assertTrue($DB->record_exists('groups', ['id' => $newmeta->groupid, 'courseid' => $newcourseid]));

        // Invite present.
        $this->assertSame(1, $DB->count_records('playergroup_invites', ['playergroupid' => $newactivity->id]));

        $newinvite = $DB->get_record(
            'playergroup_invites',
            ['playergroupid' => $newactivity->id],
            '*',
            MUST_EXIST
        );

        // Status (non-ID field) preserved.
        $this->assertSame(0, (int) $newinvite->status);

        // Group ID remapped to a group in the new course.
        $this->assertGreaterThan(0, (int) $newinvite->groupid);
        $this->assertNotSame($groupid, (int) $newinvite->groupid);
        $this->assertTrue($DB->record_exists('groups', ['id' => $newinvite->groupid, 'courseid' => $newcourseid]));

        // User IDs mapped: same users exist in DB so they map to themselves.
        $this->assertSame((int) $sender->id, (int) $newinvite->senderid);
        $this->assertSame((int) $receiver->id, (int) $newinvite->receiverid);
    }

    /**
     * Backup with userinfo=false: original course is unaffected after restore.
     */
    public function test_original_course_unaffected(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $activity] = $this->create_fixture(withusers: false);

        $originalmetacount   = $DB->count_records('playergroup_meta', ['playergroupid' => $activity->id]);
        $originalinvitecount = $DB->count_records('playergroup_invites', ['playergroupid' => $activity->id]);

        $this->backup_and_restore($course, false);

        // Original records untouched.
        $this->assertSame(
            $originalmetacount,
            $DB->count_records('playergroup_meta', ['playergroupid' => $activity->id])
        );
        $this->assertSame(
            $originalinvitecount,
            $DB->count_records('playergroup_invites', ['playergroupid' => $activity->id])
        );
        $this->assertTrue($DB->record_exists('playergroup', ['id' => $activity->id, 'course' => $course->id]));
    }

    /**
     * Backup with userinfo carries who qualified for the grade and completion, with the user remapped.
     *
     * Without it a restored course would lose the completion of students who left their group
     * after reaching the minimum size.
     */
    public function test_backup_restore_with_userinfo_keeps_qualified_students(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $activity, , , $sender] = $this->create_fixture(withusers: true);
        $DB->insert_record('playergroup_qualified', (object) [
            'playergroupid' => $activity->id, 'userid' => $sender->id, 'timequalified' => 1700000000,
        ]);

        $newcourseid = $this->backup_and_restore($course, true);

        $newactivity = $DB->get_record('playergroup', ['course' => $newcourseid], '*', MUST_EXIST);
        $restored = $DB->get_records('playergroup_qualified', ['playergroupid' => $newactivity->id]);
        $this->assertCount(1, $restored);
        $row = reset($restored);
        $this->assertSame((int) $sender->id, (int) $row->userid);
        $this->assertSame(1700000000, (int) $row->timequalified);
    }

    /**
     * Backup without userinfo carries no qualification, since it is user data.
     */
    public function test_backup_restore_content_only_has_no_qualified_students(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $activity, , , $sender] = $this->create_fixture(withusers: true);
        $DB->insert_record('playergroup_qualified', (object) [
            'playergroupid' => $activity->id, 'userid' => $sender->id, 'timequalified' => 1700000000,
        ]);

        $newcourseid = $this->backup_and_restore($course, false);

        $newactivity = $DB->get_record('playergroup', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame(0, $DB->count_records('playergroup_qualified', ['playergroupid' => $newactivity->id]));
    }

    /**
     * Points one XML element of the backed-up activity at an id that is not part of the restore.
     *
     * @param string $element Element name, such as creatorid.
     * @return callable Rewrites the activity XML.
     */
    private function orphan(string $element): callable {
        return function (string $path) use ($element): void {
            $xml = file_get_contents($path);
            $pattern = '~<' . $element . '>\d+</' . $element . '>~';
            $changed = preg_replace($pattern, '<' . $element . '>999999</' . $element . '>', $xml);
            $this->assertNotSame($xml, $changed, "The backup has no <$element> to rewrite");
            file_put_contents($path, $changed);
        };
    }

    /**
     * A group whose leader is not in the restore is left out: it would have nobody able to manage it.
     */
    public function test_a_group_whose_leader_is_missing_is_not_restored(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course] = $this->create_fixture(withusers: true);
        $newcourseid = $this->backup_and_restore($course, true, $this->orphan('creatorid'));

        $newactivity = $DB->get_record('playergroup', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame(0, $DB->count_records('playergroup_meta', ['playergroupid' => $newactivity->id]));
    }

    /**
     * A group row and an invite that point at a group the restore did not create are left out.
     */
    public function test_rows_for_a_missing_group_are_not_restored(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course] = $this->create_fixture(withusers: true);
        $newcourseid = $this->backup_and_restore($course, true, $this->orphan('groupid'));

        $newactivity = $DB->get_record('playergroup', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame(0, $DB->count_records('playergroup_meta', ['playergroupid' => $newactivity->id]));
        $this->assertSame(0, $DB->count_records('playergroup_invites', ['playergroupid' => $newactivity->id]));
    }

    /**
     * An invite whose sender or receiver is not in the restore is left out, and the group stays.
     *
     * @dataProvider missing_person_provider
     * @param string $element The element that points at a missing person.
     */
    public function test_an_invite_with_a_missing_person_is_not_restored(string $element): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course] = $this->create_fixture(withusers: true);
        $newcourseid = $this->backup_and_restore($course, true, $this->orphan($element));

        $newactivity = $DB->get_record('playergroup', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame(0, $DB->count_records('playergroup_invites', ['playergroupid' => $newactivity->id]));
        $this->assertSame(1, $DB->count_records('playergroup_meta', ['playergroupid' => $newactivity->id]));
    }

    /**
     * People an invite can be missing.
     *
     * @return array[] Element names.
     */
    public static function missing_person_provider(): array {
        return ['sender' => ['senderid'], 'receiver' => ['receiverid']];
    }

    /**
     * A qualification for a user who is not in the restore credits nobody.
     */
    public function test_a_qualification_for_a_missing_user_is_not_restored(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $activity, , , $sender] = $this->create_fixture(withusers: true);
        $DB->insert_record('playergroup_qualified', (object) [
            'playergroupid' => $activity->id, 'userid' => $sender->id, 'timequalified' => 1700000000,
        ]);
        $newcourseid = $this->backup_and_restore($course, true, $this->orphan('userid'));

        $newactivity = $DB->get_record('playergroup', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertSame(0, $DB->count_records('playergroup_qualified', ['playergroupid' => $newactivity->id]));
    }

    /**
     * The restore task declares how links in the intro are decoded and no log rules.
     */
    public function test_the_restore_task_declares_its_decoding(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/playergroup/backup/moodle2/restore_playergroup_activity_task.class.php');

        $this->assertCount(1, \restore_playergroup_activity_task::define_decode_contents());
        $this->assertCount(2, \restore_playergroup_activity_task::define_decode_rules());
        $this->assertSame([], \restore_playergroup_activity_task::define_restore_log_rules());
    }

    /**
     * Creates a course with one playergroup activity, one group with meta,
     * and one invite between two enrolled students.
     *
     * @param bool $withusers Whether to create and enrol sender/receiver students.
     * @return array{0: stdClass, 1: stdClass, 2: int, 3: int, 4: stdClass|null, 5: stdClass|null}
     *   [course, activity, groupid, metaid, sender, receiver]
     */
    private function create_fixture(bool $withusers): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $course   = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('playergroup', [
            'course'      => $course->id,
            'name'        => 'Taverna do Curso',
            'minmembers'  => 2,
            'maxmembers'  => 5,
            'canleave'    => 1,
            'deletegroups' => 0,
        ]);

        $sender   = null;
        $receiver = null;

        if ($withusers) {
            $sender   = $this->getDataGenerator()->create_and_enrol($course, 'student');
            $receiver = $this->getDataGenerator()->create_and_enrol($course, 'student');
        }

        // Create a native Moodle group for this course.
        $group   = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupid = $group->id;

        if ($sender) {
            groups_add_member($groupid, $sender->id);
        }

        // Insert playergroup_meta (content — backed up regardless of userinfo).
        $metaid = $DB->insert_record('playergroup_meta', (object) [
            'playergroupid' => $activity->id,
            'groupid'       => $groupid,
            'creatorid'     => $sender ? $sender->id : 2,
            'badge'         => '⚔',
            'privacy'       => 0,
            'password'      => '',
            'timecreated'   => time(),
            'timemodified'  => time(),
        ]);

        // Insert a pending invite (user data — only with userinfo).
        if ($sender && $receiver) {
            $DB->insert_record('playergroup_invites', (object) [
                'playergroupid' => $activity->id,
                'groupid'       => $groupid,
                'senderid'      => $sender->id,
                'receiverid'    => $receiver->id,
                'status'        => 0,
                'timecreated'   => time(),
                'timemodified'  => time(),
            ]);
        }

        return [$course, $activity, $groupid, $metaid, $sender, $receiver];
    }

    /**
     * Backs up a course and restores it to a new course.
     *
     * Uses MODE_IMPORT (no ZIP) for speed.
     *
     * @param stdClass $srccourse Source course.
     * @param bool $userinfo Whether to include user data in the backup.
     * @param callable|null $tamper Receives the path of the activity XML once the backup is written, to
     *                              rewrite it before the restore reads it.
     * @param int $newstartdate Start date of the course restored into, or 0 to keep the default.
     * @return int ID of the newly restored course.
     */
    private function backup_and_restore(
        stdClass $srccourse,
        bool $userinfo,
        ?callable $tamper = null,
        int $newstartdate = 0
    ): int {
        global $USER, $CFG;

        $CFG->backup_file_logger_level = backup::LOG_NONE;

        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $srccourse->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );

        $bc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value($userinfo);

        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        if ($tamper !== null) {
            $files = glob($CFG->backuptempdir . '/' . $backupid . '/activities/playergroup_*/playergroup.xml');
            $this->assertCount(1, $files);
            $tamper($files[0]);
        }

        $newcourseid = restore_dbops::create_new_course(
            $srccourse->fullname,
            $srccourse->shortname . '_restore',
            $srccourse->category
        );

        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );

        if ($newstartdate) {
            $rc->get_plan()->get_setting('course_startdate')->set_value($newstartdate);
        }

        $rc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value($userinfo);

        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }

    /**
     * Test that the opening and closing dates follow the start date of the course restored into.
     *
     * Restored as they were, a closing date of the previous term closes the activity the day it arrives.
     */
    public function test_the_opening_and_closing_dates_follow_the_new_course_start(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $start = strtotime('2026-02-01 00:00:00 UTC');
        $course = $this->getDataGenerator()->create_course(['startdate' => $start]);
        $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id, 'timeopen' => $start + DAYSECS, 'timeclose' => $start + 150 * DAYSECS,
        ]);
        $newstart = strtotime('2026-08-01 00:00:00 UTC');

        $newcourseid = $this->backup_and_restore($course, false, null, $newstart);

        $restored = $DB->get_record('playergroup', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertEquals($newstart + DAYSECS, (int) $restored->timeopen);
        $this->assertEquals($newstart + 150 * DAYSECS, (int) $restored->timeclose);
    }

    /**
     * Test that a date left empty stays empty when the dates are shifted.
     */
    public function test_an_empty_window_stays_empty_in_the_restore(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['startdate' => strtotime('2026-02-01 00:00:00 UTC')]);
        $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);

        $newcourseid = $this->backup_and_restore($course, false, null, strtotime('2026-08-01 00:00:00 UTC'));

        $restored = $DB->get_record('playergroup', ['course' => $newcourseid], '*', MUST_EXIST);
        $this->assertEquals(0, (int) $restored->timeopen);
        $this->assertEquals(0, (int) $restored->timeclose);
    }
}
