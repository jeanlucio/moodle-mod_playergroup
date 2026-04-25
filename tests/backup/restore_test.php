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
     * Backup without userinfo: playergroup and playergroup_meta are restored;
     * playergroup_invites are NOT included.
     */
    public function test_backup_restore_content_only(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $activity, $groupid, , , ] = $this->create_fixture(withusers: true);

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

        // Meta always included (content data).
        $this->assertSame(1, $DB->count_records('playergroup_meta', ['playergroupid' => $newactivity->id]));

        $newmeta = $DB->get_record('playergroup_meta', ['playergroupid' => $newactivity->id], '*', MUST_EXIST);

        // Content field preserved.
        $this->assertSame('⚔', $newmeta->badge);
        $this->assertSame(0, (int) $newmeta->privacy);

        // Group ID remapped to a group in the new course (must be > 0 and differ from original).
        $this->assertGreaterThan(0, (int) $newmeta->groupid);
        $this->assertNotSame($groupid, (int) $newmeta->groupid);
        $this->assertTrue($DB->record_exists('groups', ['id' => $newmeta->groupid, 'courseid' => $newcourseid]));

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
     * @return int ID of the newly restored course.
     */
    private function backup_and_restore(stdClass $srccourse, bool $userinfo): int {
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

        $rc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value($userinfo);

        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }
}
