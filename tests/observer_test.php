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
 * Unit tests for \mod_playergroup\observer.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup;

use advanced_testcase;
use mod_playergroup\external\create_group;
use mod_playergroup\external\join_group;

/**
 * Tests for \mod_playergroup\observer.
 *
 * @covers \mod_playergroup\observer
 */
final class observer_test extends advanced_testcase {
    /** @var \stdClass Course record. */
    private \stdClass $course;

    /**
     * Set up fixtures for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * A group emptied by a route OTHER than this plugin's own leave-group web service (e.g. a
     * teacher removing the last member from the course's native Groups admin page, or an
     * enrolment removal) must still be auto-deleted when the activity has "delete empty
     * groups" enabled. This is the actual bug: only leave_group::execute() used to run this
     * cleanup, so a group emptied any other way stayed behind, visible and joinable, forever.
     */
    public function test_group_member_removed_deletes_empty_group_when_removed_outside_leave_flow(): void {
        global $DB;

        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course'            => $this->course->id,
            'canleave'          => 1,
            'deleteemptygroups' => 1,
        ]);

        $leader = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($leader->id, $this->course->id, 'student');
        $this->setUser($leader);

        $result = create_group::execute($cm->cmid, 'Solo Group', '', '🛡', 0, '');
        $groupid = $result['groupid'];

        // Simulate removal via a path the plugin does not control at all (core's own Groups
        // admin page and enrolment removal both end up calling this same core function).
        groups_remove_member($groupid, $leader->id);

        $this->assertFalse($DB->record_exists('groups', ['id' => $groupid]));
        $this->assertFalse($DB->record_exists('playergroup_meta', ['groupid' => $groupid]));
    }

    /**
     * Same scenario, but with "delete empty groups" disabled: the group must be preserved,
     * matching the setting's documented behaviour regardless of which path emptied it.
     */
    public function test_group_member_removed_preserves_empty_group_when_setting_disabled(): void {
        global $DB;

        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course'            => $this->course->id,
            'canleave'          => 1,
            'deleteemptygroups' => 0,
        ]);

        $leader = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($leader->id, $this->course->id, 'student');
        $this->setUser($leader);

        $result = create_group::execute($cm->cmid, 'Solo Group', '', '🛡', 0, '');
        $groupid = $result['groupid'];

        groups_remove_member($groupid, $leader->id);

        $this->assertTrue($DB->record_exists('groups', ['id' => $groupid]));
        $this->assertTrue($DB->record_exists('playergroup_meta', ['groupid' => $groupid]));
    }

    /**
     * The observer must not touch a group that has nothing to do with this plugin (no
     * playergroup_meta row), even when it becomes empty.
     */
    public function test_group_member_removed_ignores_groups_not_managed_by_plugin(): void {
        global $DB;

        $member = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($member->id, $this->course->id, 'student');
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        groups_add_member($group->id, $member->id);

        groups_remove_member($group->id, $member->id);

        $this->assertTrue($DB->record_exists('groups', ['id' => $group->id]));
    }

    /**
     * Removing one member from a group that still has others left must not delete it.
     */
    public function test_group_member_removed_does_nothing_when_group_still_has_members(): void {
        global $DB;

        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course'            => $this->course->id,
            'canleave'          => 1,
            'deleteemptygroups' => 1,
        ]);

        $leader = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($leader->id, $this->course->id, 'student');
        $this->setUser($leader);
        $result = create_group::execute($cm->cmid, 'Duo Group', '', '🛡', 0, '');
        $groupid = $result['groupid'];

        $member = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($member->id, $this->course->id, 'student');
        $this->setUser($member);
        join_group::execute($cm->cmid, $groupid, '');

        groups_remove_member($groupid, $member->id);

        $this->assertTrue($DB->record_exists('groups', ['id' => $groupid]));
        $this->assertTrue($DB->record_exists('playergroup_meta', ['groupid' => $groupid]));
    }

    /**
     * Builds a group of three students through the real web services, led by the first.
     *
     * @param int $deleteemptygroups Value of the activity's "delete empty groups" setting.
     * @return array [cmid, groupid, leader, second, third]
     */
    private function create_group_of_three(int $deleteemptygroups = 1): array {
        global $DB;

        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course'            => $this->course->id,
            'canleave'          => 1,
            'deleteemptygroups' => $deleteemptygroups,
        ]);
        [$leader, $second, $third] = [
            $this->getDataGenerator()->create_user(),
            $this->getDataGenerator()->create_user(),
            $this->getDataGenerator()->create_user(),
        ];
        foreach ([$leader, $second, $third] as $user) {
            $this->getDataGenerator()->enrol_user($user->id, $this->course->id, 'student');
        }

        $this->setUser($leader);
        $groupid = create_group::execute($cm->cmid, 'Trio', '', '🛡', 0, '')['groupid'];
        $this->setUser($second);
        join_group::execute($cm->cmid, $groupid, '');
        $this->setUser($third);
        join_group::execute($cm->cmid, $groupid, '');

        // Make the order of joining unambiguous: the third student is the oldest remaining member.
        $DB->set_field('groups_members', 'timeadded', 1000, ['groupid' => $groupid, 'userid' => $leader->id]);
        $DB->set_field('groups_members', 'timeadded', 3000, ['groupid' => $groupid, 'userid' => $second->id]);
        $DB->set_field('groups_members', 'timeadded', 2000, ['groupid' => $groupid, 'userid' => $third->id]);

        return [$cm->cmid, $groupid, $leader, $second, $third];
    }

    /**
     * A leader removed by unenrolling from the course, a path that never goes through leave_group,
     * must hand the group to the oldest remaining member, or nobody can edit it again.
     */
    public function test_unenrolling_the_leader_hands_the_group_to_the_oldest_member(): void {
        global $DB;

        [, $groupid, $leader, , $third] = $this->create_group_of_three();

        $instance = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->unenrol_user($instance, $leader->id);

        $this->assertTrue($DB->record_exists('groups', ['id' => $groupid]));
        $this->assertEquals($third->id, $DB->get_field('playergroup_meta', 'creatorid', ['groupid' => $groupid]));
    }

    /**
     * A teacher removing the leader on the course's native Groups page has the same effect.
     */
    public function test_removing_the_leader_from_the_groups_page_hands_the_group_over(): void {
        global $DB;

        [, $groupid, $leader, , $third] = $this->create_group_of_three();

        $this->setAdminUser();
        groups_remove_member($groupid, $leader->id);

        $this->assertEquals($third->id, $DB->get_field('playergroup_meta', 'creatorid', ['groupid' => $groupid]));
    }

    /**
     * The hand-over does not depend on the "delete empty groups" setting.
     */
    public function test_the_leadership_passes_on_even_when_empty_groups_are_kept(): void {
        global $DB;

        [, $groupid, $leader, , $third] = $this->create_group_of_three(0);

        groups_remove_member($groupid, $leader->id);

        $this->assertEquals($third->id, $DB->get_field('playergroup_meta', 'creatorid', ['groupid' => $groupid]));
    }

    /**
     * Removing a member who does not lead the group leaves the leader as is.
     */
    public function test_removing_another_member_keeps_the_leader(): void {
        global $DB;

        [, $groupid, $leader, $second] = $this->create_group_of_three();

        groups_remove_member($groupid, $second->id);

        $this->assertEquals($leader->id, $DB->get_field('playergroup_meta', 'creatorid', ['groupid' => $groupid]));
    }

    /**
     * The new leader can really edit the group, which is the point of handing it over.
     */
    public function test_the_new_leader_can_edit_the_group(): void {
        [$cmid, $groupid, $leader, , $third] = $this->create_group_of_three();

        groups_remove_member($groupid, $leader->id);

        $this->setUser($third);
        $result = \mod_playergroup\external\edit_group::execute($cmid, $groupid, 'Renamed', '', '⚔', 0, '');
        $this->assertTrue($result['success']);
    }

    /**
     * Removing the only member, with empty groups kept, leaves the group as it was and raises nothing.
     */
    public function test_removing_the_only_member_of_a_kept_group_changes_nothing(): void {
        global $DB;

        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $this->course->id,
            'deleteemptygroups' => 0,
        ]);
        $leader = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($leader->id, $this->course->id, 'student');
        $this->setUser($leader);
        $groupid = create_group::execute($cm->cmid, 'Solo', '', '🛡', 0, '')['groupid'];

        groups_remove_member($groupid, $leader->id);

        $this->assertEquals($leader->id, $DB->get_field('playergroup_meta', 'creatorid', ['groupid' => $groupid]));
    }
}
