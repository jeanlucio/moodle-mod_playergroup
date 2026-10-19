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
 * Tests for the upgrade step that introduces the minimum-group-size qualification.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup;

use advanced_testcase;
use xmldb_field;
use xmldb_table;

/**
 * Runs the real xmldb_playergroup_upgrade() on data shaped like a site before the upgrade.
 *
 * Before this step, a student earned the grade and the completion by being in any group, so the
 * step must give everyone who is in a group, or who already holds a grade, the qualification they
 * already had, and must not touch anything else.
 *
 * @covers ::xmldb_playergroup_upgrade
 */
final class upgrade_test extends advanced_testcase {
    /** @var int Version before the qualification table existed. */
    private const OLDVERSION = 2026100200;

    /**
     * Loads the upgrade code and restores the state of a site that has not been upgraded yet.
     */
    protected function setUp(): void {
        global $CFG, $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback(); // The test drops and creates a table.

        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/mod/playergroup/db/upgrade.php');
        require_once($CFG->libdir . '/gradelib.php');

        $dbman = $DB->get_manager();
        $table = new xmldb_table('playergroup_qualified');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // The savepoint refuses to move a plugin to the version it is already at.
        set_config('version', self::OLDVERSION, 'mod_playergroup');
    }

    /**
     * Creates an activity.
     *
     * @param \stdClass $course The course.
     * @param int $minmembers Minimum group size.
     * @return \stdClass The activity record.
     */
    private function create_activity(\stdClass $course, int $minmembers = 2): \stdClass {
        global $DB;

        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'minmembers' => $minmembers,
            'grade' => 10,
        ]);
        return $DB->get_record('playergroup', ['id' => $cm->id], '*', MUST_EXIST);
    }

    /**
     * Puts the students in a new group of the activity, as they were before the upgrade.
     *
     * @param \stdClass $activity The activity record.
     * @param \stdClass[] $students Members, joining in this order.
     * @param int $firstjoin Timestamp of the first join; each next one is a day later.
     * @return int Native group ID.
     */
    private function create_group_with(\stdClass $activity, array $students, int $firstjoin): int {
        global $DB;

        $group = $this->getDataGenerator()->create_group(['courseid' => $activity->course]);
        foreach ($students as $offset => $student) {
            $this->getDataGenerator()->enrol_user($student->id, $activity->course, 'student');
            groups_add_member($group->id, $student->id);
            $DB->set_field(
                'groups_members',
                'timeadded',
                $firstjoin + $offset * DAYSECS,
                ['groupid' => $group->id, 'userid' => $student->id]
            );
        }
        $DB->insert_record('playergroup_meta', (object) [
            'playergroupid' => $activity->id, 'groupid' => $group->id, 'creatorid' => $students[0]->id,
            'badge' => '⚔', 'privacy' => 0, 'password' => '', 'timecreated' => $firstjoin, 'timemodified' => $firstjoin,
        ]);
        return (int) $group->id;
    }

    /**
     * Records a grade the way the old code did when a student joined, through the gradebook API.
     *
     * @param \stdClass $activity The activity record.
     * @param \stdClass $student The graded student.
     * @param int $datesubmitted The submission date reported with the grade.
     */
    private function grade(\stdClass $activity, \stdClass $student, int $datesubmitted): void {
        $activity->courseid = $activity->course;
        playergroup_grade_item_update($activity, [$student->id => (object) [
            'userid' => $student->id,
            'rawgrade' => 10,
            'datesubmitted' => $datesubmitted,
        ]]);
    }

    /**
     * Returns the recorded qualification times keyed by "playergroupid:userid".
     *
     * @return int[] Time qualified per pair.
     */
    private function qualified(): array {
        global $DB;

        $times = [];
        foreach ($DB->get_records('playergroup_qualified') as $row) {
            $times[$row->playergroupid . ':' . $row->userid] = (int) $row->timequalified;
        }
        ksort($times);
        return $times;
    }

    /**
     * Test that every student in a group qualifies at their own join time, even in a group below the minimum.
     */
    public function test_members_of_any_group_keep_what_they_had(): void {
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->create_activity($course, 3);
        [$a, $b] = [$this->getDataGenerator()->create_user(), $this->getDataGenerator()->create_user()];
        $joined = 1700000000;
        $this->create_group_with($activity, [$a, $b], $joined);

        $this->assertTrue(xmldb_playergroup_upgrade(self::OLDVERSION));

        $this->assertEquals([
            "{$activity->id}:{$a->id}" => $joined,
            "{$activity->id}:{$b->id}" => $joined + DAYSECS,
        ], $this->qualified());
    }

    /**
     * Test that a student who left the group but holds a grade keeps it, dated as the grade says.
     */
    public function test_a_graded_student_without_a_group_qualifies_with_the_grade_date(): void {
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->create_activity($course);
        $left = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($left->id, $course->id, 'student');
        $this->grade($activity, $left, 1650000000);

        xmldb_playergroup_upgrade(self::OLDVERSION);

        $this->assertSame(["{$activity->id}:{$left->id}" => 1650000000], $this->qualified());
    }

    /**
     * Test that a grade from an older version, which has no creation time, is dated by its last modification.
     */
    public function test_a_grade_without_creation_time_uses_its_modification_time(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $activity = $this->create_activity($course);
        $left = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($left->id, $course->id, 'student');
        $this->grade($activity, $left, 1650000000);
        $DB->execute('UPDATE {grade_grades} SET timecreated = NULL, timemodified = :modified WHERE userid = :userid', [
            'modified' => 1660000000,
            'userid' => $left->id,
        ]);

        xmldb_playergroup_upgrade(self::OLDVERSION);

        $this->assertSame(["{$activity->id}:{$left->id}" => 1660000000], $this->qualified());
    }

    /**
     * Test that a graded member keeps the date of the grade rather than the join time.
     */
    public function test_a_grade_date_wins_over_the_join_time(): void {
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->create_activity($course, 1);
        $student = $this->getDataGenerator()->create_user();
        $this->create_group_with($activity, [$student], 1700000000);
        $this->grade($activity, $student, 1690000000);

        xmldb_playergroup_upgrade(self::OLDVERSION);

        $this->assertSame(["{$activity->id}:{$student->id}" => 1690000000], $this->qualified());
    }

    /**
     * Test that students without a group and without a grade are left out.
     */
    public function test_students_who_never_joined_are_not_qualified(): void {
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->create_activity($course);
        $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->create_group_with($activity, [$this->getDataGenerator()->create_user()], 1700000000);

        xmldb_playergroup_upgrade(self::OLDVERSION);

        $this->assertCount(1, $this->qualified());
    }

    /**
     * Test that the same student in two activities is recorded once per activity, and that each activity
     * only gets its own members.
     */
    public function test_activities_are_kept_apart(): void {
        $course = $this->getDataGenerator()->create_course();
        $first = $this->create_activity($course);
        $second = $this->create_activity($course);
        $shared = $this->getDataGenerator()->create_user();
        $onlysecond = $this->getDataGenerator()->create_user();
        $this->create_group_with($first, [$shared], 1700000000);
        $this->create_group_with($second, [$shared, $onlysecond], 1710000000);

        xmldb_playergroup_upgrade(self::OLDVERSION);

        $this->assertEquals([
            "{$first->id}:{$shared->id}" => 1700000000,
            "{$second->id}:{$shared->id}" => 1710000000,
            "{$second->id}:{$onlysecond->id}" => 1710000000 + DAYSECS,
        ], $this->qualified());
    }

    /**
     * Test that running the step again, or after a partial run, adds nothing twice and finishes the job.
     */
    public function test_the_step_can_run_again_after_an_interruption(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $activity = $this->create_activity($course);
        [$a, $b] = [$this->getDataGenerator()->create_user(), $this->getDataGenerator()->create_user()];
        $this->create_group_with($activity, [$a, $b], 1700000000);

        // A first run that stopped after the table was created and one row was written.
        $dbman = $DB->get_manager();
        $table = new xmldb_table('playergroup_qualified');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('playergroupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timequalified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('playergroupid_userid', XMLDB_KEY_UNIQUE, ['playergroupid', 'userid']);
        $dbman->create_table($table);
        $DB->insert_record('playergroup_qualified', (object) [
            'playergroupid' => $activity->id, 'userid' => $a->id, 'timequalified' => 5,
        ]);

        xmldb_playergroup_upgrade(self::OLDVERSION);
        $this->assertCount(2, $this->qualified());
        $this->assertSame(5, $this->qualified()["{$activity->id}:{$a->id}"], 'An existing row is left as it was.');

        // A second run starts from the same recorded version, since the first one may not have finished.
        set_config('version', self::OLDVERSION, 'mod_playergroup');
        xmldb_playergroup_upgrade(self::OLDVERSION);
        $this->assertCount(2, $this->qualified());
    }

    /**
     * Test that more students than one insert batch are all written.
     *
     * The members are plain membership rows: the step reads groups_members and playergroup_meta only,
     * so creating a thousand real users would add time and prove nothing.
     */
    public function test_more_rows_than_one_batch_are_all_written(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $activity = $this->create_activity($course);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $DB->insert_record('playergroup_meta', (object) [
            'playergroupid' => $activity->id, 'groupid' => $group->id, 'creatorid' => 2,
            'badge' => '⚔', 'privacy' => 0, 'password' => '', 'timecreated' => 1, 'timemodified' => 1,
        ]);
        $members = [];
        for ($userid = 1000; $userid < 2005; $userid++) {
            $members[] = (object) [
                'groupid' => $group->id, 'userid' => $userid, 'timeadded' => 1700000000, 'component' => '', 'itemid' => 0,
            ];
        }
        $DB->insert_records('groups_members', $members);

        xmldb_playergroup_upgrade(self::OLDVERSION);

        $this->assertSame(1005, $DB->count_records('playergroup_qualified', ['playergroupid' => $activity->id]));
    }

    /**
     * Test that the step ignores sites with nothing to migrate, and records the new version.
     */
    public function test_an_empty_site_upgrades_cleanly(): void {
        global $DB;

        $this->assertTrue(xmldb_playergroup_upgrade(self::OLDVERSION));

        $this->assertTrue($DB->get_manager()->table_exists('playergroup_qualified'));
        $this->assertSame(0, $DB->count_records('playergroup_qualified'));
    }

    /**
     * Test that a site older than the "delete empty groups" setting gets the column, on by default.
     */
    public function test_the_delete_empty_groups_column_is_added_with_its_default(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new xmldb_table('playergroup');
        $field = new xmldb_field('deleteemptygroups');
        $activity = $this->create_activity($this->getDataGenerator()->create_course());
        $dbman->drop_field($table, $field);
        $this->assertFalse($dbman->field_exists($table, $field));
        set_config('version', 2026050100, 'mod_playergroup');

        $this->assertTrue(xmldb_playergroup_upgrade(2026050100));

        $this->assertTrue($dbman->field_exists($table, $field));
        $this->assertSame(1, (int) $DB->get_field('playergroup', 'deleteemptygroups', ['id' => $activity->id]));
    }

    /**
     * Test that a site that already has the column keeps what its teachers chose.
     */
    public function test_an_existing_delete_empty_groups_column_is_left_alone(): void {
        global $DB;

        $activity = $this->create_activity($this->getDataGenerator()->create_course());
        $DB->set_field('playergroup', 'deleteemptygroups', 0, ['id' => $activity->id]);
        set_config('version', 2026050100, 'mod_playergroup');

        $this->assertTrue(xmldb_playergroup_upgrade(2026050100));

        $this->assertSame(0, (int) $DB->get_field('playergroup', 'deleteemptygroups', ['id' => $activity->id]));
    }

    /**
     * Test that a narrow password column is widened to hold a hash, keeping the stored hashes.
     */
    public function test_the_password_column_is_widened_to_hold_a_hash(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new xmldb_table('playergroup_meta');
        $narrow = new xmldb_field('password', XMLDB_TYPE_CHAR, '60', null, null, null, '', 'privacy');
        $dbman->change_field_precision($table, $narrow);
        $this->assertSame(60, (int) $DB->get_columns('playergroup_meta')['password']->max_length);

        $course = $this->getDataGenerator()->create_course();
        $activity = $this->create_activity($course);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $hash = password_hash('secret', PASSWORD_DEFAULT);
        $DB->insert_record('playergroup_meta', (object) [
            'playergroupid' => $activity->id, 'groupid' => $group->id, 'creatorid' => 2, 'badge' => 'x',
            'privacy' => 1, 'password' => $hash, 'timecreated' => 1, 'timemodified' => 1,
        ]);
        set_config('version', 2026061600, 'mod_playergroup');

        $this->assertTrue(xmldb_playergroup_upgrade(2026061600));

        $this->assertSame(255, (int) $DB->get_columns('playergroup_meta')['password']->max_length);
        $this->assertSame($hash, $DB->get_field('playergroup_meta', 'password', ['groupid' => $group->id]));
    }

    /**
     * Test that a site already on the latest version is left untouched.
     */
    public function test_a_current_site_runs_no_step(): void {
        global $DB;

        $this->assertTrue(xmldb_playergroup_upgrade(2026101600));

        $dbman = $DB->get_manager();
        $this->assertFalse($dbman->table_exists('playergroup_qualified'));

        // The table setUp() dropped goes back, so the test framework can reset the database afterwards.
        $dbman->install_one_table_from_xmldb_file(__DIR__ . '/../db/install.xml', 'playergroup_qualified');
    }

    /**
     * Test that the step that makes the calendar events gives them to the activities that already existed.
     *
     * The activities of a site before this version have their dates and no event, and the rest of the plugin
     * only makes events when an activity is saved.
     */
    public function test_existing_activities_get_their_calendar_events_and_the_step_can_run_again(): void {
        global $DB;

        $one = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $this->getDataGenerator()->create_course()->id, 'timeopen' => 1900000000, 'timeclose' => 1900500000,
        ]);
        $two = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $this->getDataGenerator()->create_course()->id, 'timeclose' => 1900500000,
        ]);
        $none = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $this->getDataGenerator()->create_course()->id,
        ]);
        // The site as it was before this version: dates in the table and no event for them.
        $DB->delete_records_select('event', "modulename = 'playergroup' AND eventtype IN ('open', 'close')");

        // The whole upgrade runs, which also brings back the table the setUp() of this class dropped.
        $this->assertTrue(xmldb_playergroup_upgrade(self::OLDVERSION));
        $count = fn(int $id): int => $DB->count_records_select(
            'event',
            "modulename = 'playergroup' AND instance = :id AND eventtype IN ('open', 'close')",
            ['id' => $id]
        );
        $this->assertSame(2, $count((int) $one->id));
        $this->assertSame(1, $count((int) $two->id));
        $this->assertSame(0, $count((int) $none->id));

        set_config('version', self::OLDVERSION, 'mod_playergroup');
        xmldb_playergroup_upgrade(self::OLDVERSION);
        $this->assertSame(2, $count((int) $one->id));
        $this->assertSame(1, $count((int) $two->id));
    }
}
