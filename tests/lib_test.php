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
 * Unit tests for mod_playergroup lib.php functions.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup;

use advanced_testcase;

/**
 * PHPUnit test case for playergroup lib functions.
 *
 * @covers ::playergroup_add_instance
 * @covers ::playergroup_delete_instance
 * @covers ::playergroup_update_instance
 * @covers ::mod_playergroup_core_calendar_provide_event_action
 * @covers \mod_playergroup\instance_manager
 */
final class lib_test extends advanced_testcase {
    /**
     * Reset the Moodle environment between tests.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test that playergroup_add_instance inserts a record and returns a valid ID.
     */
    public function test_add_instance_returns_id(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();

        $playergroup = (object) [
            'course'              => $course->id,
            'name'                => 'Test Activity',
            'intro'               => '',
            'introformat'         => FORMAT_HTML,
            'groupingmode'        => 'none',
            'minmembers'          => 2,
            'maxmembers'          => 5,
            'canleave'            => 1,
            'deletegroups'        => 0,
            'grade'               => 0,
            'completionjoingroup' => 0,
            'timeopen'            => 0,
            'timeclose'           => 0,
        ];

        $id = playergroup_add_instance($playergroup);

        $this->assertGreaterThan(0, $id);
        $this->assertTrue($DB->record_exists('playergroup', ['id' => $id]));
    }

    /**
     * Test that add_instance stores timemodified.
     */
    public function test_add_instance_stores_timemodified(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $before = time();

        $playergroup = (object) [
            'course'              => $course->id,
            'name'                => 'Time Test',
            'intro'               => '',
            'introformat'         => FORMAT_HTML,
            'groupingmode'        => 'none',
            'minmembers'          => 2,
            'maxmembers'          => 5,
            'canleave'            => 1,
            'deletegroups'        => 0,
            'grade'               => 0,
            'completionjoingroup' => 0,
            'timeopen'            => 0,
            'timeclose'           => 0,
        ];

        $id = playergroup_add_instance($playergroup);
        $record = $DB->get_record('playergroup', ['id' => $id]);

        $this->assertGreaterThanOrEqual($before, $record->timemodified);
    }

    /**
     * Helper: create a minimal playergroup activity via generator.
     *
     * @param \stdClass $course
     * @return \stdClass cm record
     */
    private function create_activity(\stdClass $course): \stdClass {
        return $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
    }

    /**
     * Test that delete_instance removes the record and associated data.
     */
    public function test_delete_instance_removes_record(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->create_activity($course);

        $this->assertTrue(playergroup_delete_instance($cm->id));
        $this->assertFalse($DB->record_exists('playergroup', ['id' => $cm->id]));
    }

    /**
     * Test that delete_instance cleans playergroup_meta rows.
     */
    public function test_delete_instance_removes_meta(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->create_activity($course);

        // Insert a fake meta row.
        $DB->insert_record('playergroup_meta', (object) [
            'playergroupid' => $cm->id,
            'groupid'       => 0,
            'creatorid'     => 2,
            'badge'         => '⚔',
            'privacy'       => 0,
            'password'      => '',
            'timecreated'   => time(),
            'timemodified'  => time(),
        ]);

        playergroup_delete_instance($cm->id);

        $this->assertFalse($DB->record_exists('playergroup_meta', ['playergroupid' => $cm->id]));
    }

    /**
     * Test that delete_instance cleans playergroup_invites rows.
     */
    public function test_delete_instance_removes_invites(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->create_activity($course);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $DB->insert_record('playergroup_invites', (object) [
            'playergroupid' => $cm->id,
            'groupid'       => 0,
            'senderid'      => $user1->id,
            'receiverid'    => $user2->id,
            'status'        => 0,
            'timecreated'   => time(),
            'timemodified'  => time(),
        ]);

        playergroup_delete_instance($cm->id);

        $this->assertFalse($DB->record_exists('playergroup_invites', ['playergroupid' => $cm->id]));
    }

    /**
     * Test that delete_instance returns false for a non-existent ID.
     */
    public function test_delete_instance_returns_false_for_missing_id(): void {
        $this->assertFalse(playergroup_delete_instance(999999));
    }

    /**
     * Test that deleting an activity pointed at a pre-existing grouping ("existing" mode)
     * only removes the groups this instance registered, leaving a foreign group (and the
     * grouping itself, since it is not left empty) untouched.
     */
    public function test_delete_instance_preserves_foreign_groups_in_existing_grouping(): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/group/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $grouping = $this->getDataGenerator()->create_grouping(['courseid' => $course->id]);

        // A group the teacher already had in this grouping for another purpose.
        $foreigngroup = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_grouping_group([
            'groupingid' => $grouping->id,
            'groupid'    => $foreigngroup->id,
        ]);

        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course'             => $course->id,
            'groupingmode'       => 'existing',
            'existinggroupingid' => $grouping->id,
            'deletegroups'       => 1,
        ]);

        // A group the plugin itself created and registered for this instance.
        $ownedgroup = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_grouping_group([
            'groupingid' => $grouping->id,
            'groupid'    => $ownedgroup->id,
        ]);
        $DB->insert_record('playergroup_meta', (object) [
            'playergroupid' => $cm->id,
            'groupid'       => $ownedgroup->id,
            'creatorid'     => 2,
            'badge'         => '⚔',
            'privacy'       => 0,
            'password'      => '',
            'timecreated'   => time(),
            'timemodified'  => time(),
        ]);

        playergroup_delete_instance($cm->id);

        $this->assertFalse($DB->record_exists('groups', ['id' => $ownedgroup->id]));
        $this->assertTrue($DB->record_exists('groups', ['id' => $foreigngroup->id]));
        $this->assertTrue($DB->record_exists('groupings', ['id' => $grouping->id]));
    }

    /**
     * Test that deleting an activity that auto-created its own grouping ("new" mode) still
     * removes the now-empty grouping, preserving the pre-existing behaviour for that mode.
     */
    public function test_delete_instance_removes_emptied_grouping(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();

        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course'       => $course->id,
            'groupingmode' => 'new',
            'deletegroups' => 1,
        ]);
        $playergroup = $DB->get_record('playergroup', ['id' => $cm->id], '*', MUST_EXIST);
        $groupingid = (int) $playergroup->groupingid;
        $this->assertGreaterThan(0, $groupingid);

        $ownedgroup = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_grouping_group([
            'groupingid' => $groupingid,
            'groupid'    => $ownedgroup->id,
        ]);
        $DB->insert_record('playergroup_meta', (object) [
            'playergroupid' => $cm->id,
            'groupid'       => $ownedgroup->id,
            'creatorid'     => 2,
            'badge'         => '⚔',
            'privacy'       => 0,
            'password'      => '',
            'timecreated'   => time(),
            'timemodified'  => time(),
        ]);

        playergroup_delete_instance($cm->id);

        $this->assertFalse($DB->record_exists('groups', ['id' => $ownedgroup->id]));
        $this->assertFalse($DB->record_exists('groupings', ['id' => $groupingid]));
    }

    /**
     * Creates a group registered to the activity, as a student creating it through the activity would.
     *
     * @param \stdClass $cm Activity as returned by the generator.
     * @return int The native group ID.
     */
    private function create_owned_group(\stdClass $cm): int {
        global $DB;

        $group = $this->getDataGenerator()->create_group(['courseid' => $cm->course]);
        $DB->insert_record('playergroup_meta', (object) [
            'playergroupid' => $cm->id,
            'groupid'       => $group->id,
            'creatorid'     => 2,
            'badge'         => '⚔',
            'privacy'       => 0,
            'password'      => '',
            'timecreated'   => time(),
            'timemodified'  => time(),
        ]);
        return (int) $group->id;
    }

    /**
     * Test that "delete groups" removes the groups the students created even when the activity has no
     * grouping, which is the mode where the option used to do nothing.
     */
    public function test_delete_instance_removes_groups_when_there_is_no_grouping(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course'       => $course->id,
            'groupingmode' => 'none',
            'deletegroups' => 1,
        ]);
        $owned = $this->create_owned_group($cm);
        $foreign = $this->getDataGenerator()->create_group(['courseid' => $course->id]);

        playergroup_delete_instance($cm->id);

        $this->assertFalse($DB->record_exists('groups', ['id' => $owned]));
        $this->assertTrue($DB->record_exists('groups', ['id' => $foreign->id]), 'A group the activity never registered stays.');
    }

    /**
     * Test that the groups are still removed when the grouping was deleted by hand beforehand.
     */
    public function test_delete_instance_removes_groups_when_the_grouping_is_gone(): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/group/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course'       => $course->id,
            'groupingmode' => 'new',
            'deletegroups' => 1,
        ]);
        $owned = $this->create_owned_group($cm);
        $groupingid = (int) $DB->get_field('playergroup', 'groupingid', ['id' => $cm->id], MUST_EXIST);
        groups_delete_grouping($groupingid);

        playergroup_delete_instance($cm->id);

        $this->assertFalse($DB->record_exists('groups', ['id' => $owned]));
    }

    /**
     * Test that without the option the groups are kept, whatever the grouping mode.
     */
    public function test_delete_instance_keeps_groups_when_the_option_is_off(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course'       => $course->id,
            'groupingmode' => 'none',
            'deletegroups' => 0,
        ]);
        $owned = $this->create_owned_group($cm);

        playergroup_delete_instance($cm->id);

        $this->assertTrue($DB->record_exists('groups', ['id' => $owned]));
    }

    /**
     * Fetches the raw course module row, which is what the core hands to get_coursemodule_info().
     *
     * @param \stdClass $cm Activity as returned by the generator.
     * @return \stdClass The course_modules record.
     */
    private function cm_row(\stdClass $cm): \stdClass {
        global $DB;

        return $DB->get_record('course_modules', ['id' => $cm->cmid], '*', MUST_EXIST);
    }

    /**
     * Test that the description is offered to the course page when the teacher asks for it.
     *
     * The activity declares the "Display description on course page" option, but the core leaves it to
     * each module to fill in the content; without it the box does nothing.
     */
    public function test_get_coursemodule_info_shows_the_description_when_asked(): void {
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'intro' => '<p>Form a team of up to five</p>',
            'introformat' => FORMAT_HTML,
            'showdescription' => 1,
        ]);

        $info = playergroup_get_coursemodule_info($this->cm_row($cm));

        $this->assertStringContainsString('Form a team of up to five', (string) $info->content);
    }

    /**
     * Test that the description stays off the course page when the option is not ticked.
     */
    public function test_get_coursemodule_info_hides_the_description_when_not_asked(): void {
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'intro' => '<p>Form a team of up to five</p>',
            'introformat' => FORMAT_HTML,
            'showdescription' => 0,
        ]);

        $info = playergroup_get_coursemodule_info($this->cm_row($cm));

        $this->assertEmpty($info->content);
    }

    /**
     * Test that the course page, which reads the cached module info, shows the description too.
     */
    public function test_the_description_reaches_the_course_page_through_modinfo(): void {
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'intro' => '<p>Form a team of up to five</p>',
            'introformat' => FORMAT_HTML,
            'showdescription' => 1,
        ]);

        $cminfo = get_fast_modinfo($course)->get_cm($cm->cmid);

        $this->assertStringContainsString('Form a team of up to five', $cminfo->get_formatted_content());
    }

    /**
     * Test that the completion rule is published for the course page only while the tracking is automatic.
     *
     * A value left over from before the teacher switched to manual tracking must not keep the rule alive.
     */
    public function test_get_coursemodule_info_ignores_the_rule_without_automatic_tracking(): void {
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionjoingroup' => 1,
        ]);

        $info = playergroup_get_coursemodule_info($this->cm_row($cm));

        $this->assertArrayNotHasKey('completionjoingroup', $info->customdata['customcompletionrules'] ?? []);
    }

    /**
     * Test that the rule is published when the tracking is automatic and the rule is on.
     */
    public function test_get_coursemodule_info_publishes_the_rule_with_automatic_tracking(): void {
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionjoingroup' => 1,
        ]);

        $info = playergroup_get_coursemodule_info($this->cm_row($cm));

        $this->assertArrayHasKey('completionjoingroup', $info->customdata['customcompletionrules']);
    }

    /**
     * Test that playergroup_supports returns expected values.
     */
    public function test_supports_known_features(): void {
        $this->assertTrue(playergroup_supports(FEATURE_MOD_INTRO));
        $this->assertTrue(playergroup_supports(FEATURE_BACKUP_MOODLE2));
        $this->assertTrue(playergroup_supports(FEATURE_COMPLETION_HAS_RULES));
        $this->assertTrue(playergroup_supports(FEATURE_GRADE_HAS_GRADE));
        $this->assertNull(playergroup_supports('unknown_feature'));
    }

    /**
     * Test that the activity declares its own icon, so the theme does not recolour it.
     */
    public function test_the_activity_declares_a_branded_icon(): void {
        $this->assertTrue(playergroup_is_branded());
        $this->assertTrue(component_callback('mod_playergroup', 'is_branded', [], false));
    }

    /**
     * Test that the completion page describes the rule only when it is active.
     */
    public function test_completion_rule_descriptions_follow_the_active_rules(): void {
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $active = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionjoingroup' => 1,
        ]);
        $inactive = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionjoingroup' => 0,
        ]);
        $modinfo = get_fast_modinfo($course);

        $this->assertSame(
            [get_string('completionjoingroup_desc', 'mod_playergroup')],
            playergroup_get_completion_active_rule_descriptions($course, $modinfo->get_cm($active->cmid))
        );
        $this->assertSame([], playergroup_get_completion_active_rule_descriptions($course, $modinfo->get_cm($inactive->cmid)));
    }

    /**
     * Test the hint under the activity link on the course page, for each stage of the window.
     */
    public function test_cm_info_view_hints_at_the_availability_window(): void {
        $course = $this->getDataGenerator()->create_course();
        $notyet = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'timeopen' => time() + DAYSECS,
        ]);
        $closed = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'timeclose' => time() - HOURSECS,
        ]);
        $open = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $modinfo = get_fast_modinfo($course);

        $this->assertStringContainsString(
            userdate((int) $modinfo->get_cm($notyet->cmid)->customdata['timeopen']),
            $modinfo->get_cm($notyet->cmid)->afterlink
        );
        $this->assertStringContainsString(
            get_string('activityclosedmsg', 'mod_playergroup'),
            $modinfo->get_cm($closed->cmid)->afterlink
        );
        $this->assertEmpty($modinfo->get_cm($open->cmid)->afterlink);
    }

    /**
     * Returns the calendar events of the completion date of an activity.
     *
     * @param int $instanceid The activity.
     * @return \stdClass[] The events.
     */
    private function expected_completion_events(int $instanceid): array {
        global $DB;

        return array_values($DB->get_records('event', [
            'modulename' => 'playergroup',
            'instance'   => $instanceid,
            'eventtype'  => \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED,
        ]));
    }

    /**
     * Test that the date the teacher expects the activity to be completed by reaches the calendar.
     *
     * The form offers the date, but only the module can turn it into an event of the calendar and of the
     * timeline of the students.
     */
    public function test_the_expected_completion_date_becomes_a_calendar_event(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enablecompletion = true;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $expected = time() + 5 * DAYSECS;
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
            'completionexpected' => $expected,
        ]);

        $events = $this->expected_completion_events((int) $cm->id);
        $this->assertCount(1, $events);
        $this->assertEquals($expected, (int) $events[0]->timestart);
    }

    /**
     * Test that changing and then clearing the date updates and removes the event.
     */
    public function test_the_expected_completion_event_follows_later_edits(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enablecompletion = true;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
            'completionexpected' => time() + DAYSECS,
        ]);
        $later = time() + 9 * DAYSECS;

        $update = (object) ['instance' => $cm->id, 'coursemodule' => $cm->cmid, 'course' => $course->id, 'name' => 'Teams',
            'minmembers' => 2, 'maxmembers' => 5, 'completionexpected' => $later, 'groupingmode' => 'none'];
        playergroup_update_instance($update);
        $events = $this->expected_completion_events((int) $cm->id);
        $this->assertCount(1, $events);
        $this->assertEquals($later, (int) $events[0]->timestart);

        $update->completionexpected = 0;
        playergroup_update_instance($update);
        $this->assertSame([], $this->expected_completion_events((int) $cm->id));
    }

    /**
     * Test that the timeline offers the action of opening the activity for the completion event.
     */
    public function test_the_calendar_event_offers_the_action_of_opening_the_activity(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enablecompletion = true;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
            'completionexpected' => time() + DAYSECS,
        ]);
        $this->setUser($student);
        $eventrecord = $this->expected_completion_events((int) $cm->id)[0];
        $event = \calendar_event::load($eventrecord->id);

        $factory = new \core_calendar\action_factory();
        $action = mod_playergroup_core_calendar_provide_event_action($event, $factory, (int) $student->id);

        $this->assertInstanceOf(\core_calendar\local\event\value_objects\action::class, $action);
        $this->assertEquals($cm->cmid, (int) $action->get_url()->get_param('id'));
        $this->assertTrue($action->is_actionable());
    }

    /**
     * Test that no action is offered once the student completed the activity.
     */
    public function test_the_calendar_event_offers_no_action_to_who_completed_the_activity(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enablecompletion = true;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
            'completionexpected' => time() + DAYSECS,
        ]);
        $this->setUser($student);
        $cminfo = get_fast_modinfo($course, $student->id)->get_cm($cm->cmid);
        (new \completion_info($course))->set_module_viewed($cminfo, $student->id);
        $event = \calendar_event::load($this->expected_completion_events((int) $cm->id)[0]->id);

        $factory = new \core_calendar\action_factory();
        $this->assertNull(mod_playergroup_core_calendar_provide_event_action($event, $factory, (int) $student->id));
    }

    /**
     * Creates the grouping an activity would get, by the same code the settings form uses.
     *
     * @param int $courseid The course.
     * @param string $activityname Name of the activity.
     * @param string $custom Name typed by the teacher, or empty.
     * @return \stdClass The grouping record.
     */
    private function create_activity_grouping(int $courseid, string $activityname, string $custom = ''): \stdClass {
        global $DB;

        $manager = new \mod_playergroup\instance_manager();
        $id = $manager->process_activity_grouping((object) ['course' => $courseid, 'name' => $activityname], $custom);

        return $DB->get_record('groupings', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Test that the name made for the grouping fits the column however long the activity name is.
     */
    public function test_the_default_grouping_name_fits_the_column(): void {
        $course = $this->getDataGenerator()->create_course();

        $grouping = $this->create_activity_grouping($course->id, str_repeat('é', 255));

        $this->assertLessThanOrEqual(255, \core_text::strlen($grouping->name));
        $this->assertStringStartsWith(get_string('defaultgroupingname', 'mod_playergroup'), $grouping->name);
    }

    /**
     * Test that two activities with the same name get two groupings, not two with the same name.
     *
     * The restore of the course would otherwise join them into one.
     */
    public function test_activities_with_the_same_name_get_different_groupings(): void {
        $course = $this->getDataGenerator()->create_course();

        $first = $this->create_activity_grouping($course->id, 'Teams');
        $second = $this->create_activity_grouping($course->id, 'Teams');
        $third = $this->create_activity_grouping($course->id, 'Teams');

        $this->assertNotSame($first->name, $second->name);
        $this->assertNotSame($second->name, $third->name);
        $this->assertNotSame($first->name, $third->name);
        $this->assertStringStartsWith($first->name, $second->name);
    }

    /**
     * Test that a repeated default name stays within the column even when the first already used all of it.
     */
    public function test_a_repeated_long_default_name_still_fits(): void {
        $course = $this->getDataGenerator()->create_course();
        $name = str_repeat('x', 255);

        $first = $this->create_activity_grouping($course->id, $name);
        $second = $this->create_activity_grouping($course->id, $name);

        $this->assertLessThanOrEqual(255, \core_text::strlen($second->name));
        $this->assertNotSame($first->name, $second->name);
    }
}
