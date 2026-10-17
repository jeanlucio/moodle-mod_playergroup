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
 * Unit tests for the custom completion rules.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\completion;

use advanced_testcase;

/**
 * Tests for \mod_playergroup\completion\custom_completion.
 *
 * @covers \mod_playergroup\completion\custom_completion
 */
final class custom_completion_test extends advanced_testcase {
    /**
     * Creates a course and a playergroup activity with the join-group completion rule enabled.
     *
     * @return array{0: \stdClass, 1: \stdClass} [course, cm]
     */
    private function create_fixture(): array {
        global $CFG;
        $CFG->enablecompletion = true;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course'              => $course->id,
            'minmembers'          => 1,
            'completion'          => COMPLETION_TRACKING_AUTOMATIC,
            'completionjoingroup' => 1,
        ]);

        return [$course, $cm];
    }

    /**
     * The rule is incomplete while the student belongs to no group in the activity.
     */
    public function test_get_state_incomplete_without_group(): void {
        $this->resetAfterTest();

        [$course, $cm] = $this->create_fixture();
        $user = $this->getDataGenerator()->create_user();

        $cminfo = get_fast_modinfo($course)->get_cm($cm->cmid);
        $completion = new custom_completion($cminfo, (int) $user->id);

        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_state('completionjoingroup'));
    }

    /**
     * The rule is complete once the student belongs to a group registered for the activity.
     */
    public function test_get_state_complete_with_group(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, $cm] = $this->create_fixture();
        $user = $this->getDataGenerator()->create_user();

        // Add the user directly to a native group (bypasses enrolment checks).
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $DB->insert_record('groups_members', (object) [
            'groupid'   => $group->id,
            'userid'    => $user->id,
            'timeadded' => time(),
            'component' => '',
            'itemid'    => 0,
        ]);

        $DB->insert_record('playergroup_meta', (object) [
            'playergroupid' => $cm->id,
            'groupid'       => $group->id,
            'creatorid'     => $user->id,
            'badge'         => '🛡',
            'privacy'       => 0,
            'password'      => '',
            'timecreated'   => time(),
            'timemodified'  => time(),
        ]);

        // The activity has a minimum of one member, so being in the group is enough to qualify.
        $playergroup = $DB->get_record('playergroup', ['id' => $cm->id], '*', MUST_EXIST);
        \mod_playergroup\local\qualification::record_group($playergroup, (int) $group->id);

        $cminfo = get_fast_modinfo($course)->get_cm($cm->cmid);
        $completion = new custom_completion($cminfo, (int) $user->id);

        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_state('completionjoingroup'));
    }

    /**
     * Being in a group that has not reached the minimum size is not enough, and the rule stays
     * complete once earned even when the student is no longer in any group.
     */
    public function test_get_state_follows_the_qualification_not_the_current_membership(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, $cm] = $this->create_fixture();
        $DB->set_field('playergroup', 'minmembers', 2, ['id' => $cm->id]);
        $user = $this->getDataGenerator()->create_user();

        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $DB->insert_record('groups_members', (object) [
            'groupid' => $group->id, 'userid' => $user->id, 'timeadded' => time(), 'component' => '', 'itemid' => 0,
        ]);
        $DB->insert_record('playergroup_meta', (object) [
            'playergroupid' => $cm->id, 'groupid' => $group->id, 'creatorid' => $user->id, 'badge' => '🛡',
            'privacy' => 0, 'password' => '', 'timecreated' => time(), 'timemodified' => time(),
        ]);

        $cminfo = get_fast_modinfo($course)->get_cm($cm->cmid);
        $completion = new custom_completion($cminfo, (int) $user->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_state('completionjoingroup'));

        $DB->insert_record('playergroup_qualified', (object) [
            'playergroupid' => $cm->id, 'userid' => $user->id, 'timequalified' => time(),
        ]);
        $DB->delete_records('groups_members', ['groupid' => $group->id, 'userid' => $user->id]);

        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_state('completionjoingroup'));
    }

    /**
     * The rule describes itself for the activity page and the completion report.
     */
    public function test_get_custom_rule_descriptions(): void {
        $this->resetAfterTest();

        [$course, $cm] = $this->create_fixture();
        $user = $this->getDataGenerator()->create_user();
        $cminfo = get_fast_modinfo($course)->get_cm($cm->cmid);

        $descriptions = (new custom_completion($cminfo, (int) $user->id))->get_custom_rule_descriptions();

        $this->assertSame(
            ['completionjoingroup' => get_string('completionjoingroup_desc', 'mod_playergroup')],
            $descriptions
        );
    }

    /**
     * The custom rule is listed after the core rules, in the order the activity page shows them.
     */
    public function test_get_sort_order_ends_with_the_custom_rule(): void {
        $this->resetAfterTest();

        [$course, $cm] = $this->create_fixture();
        $user = $this->getDataGenerator()->create_user();
        $cminfo = get_fast_modinfo($course)->get_cm($cm->cmid);

        $order = (new custom_completion($cminfo, (int) $user->id))->get_sort_order();

        $this->assertSame('completionjoingroup', end($order));
        $this->assertContains('completionview', $order);
        $this->assertContains('completionusegrade', $order);
    }

    /**
     * The defined rules are exactly the ones the activity declares.
     */
    public function test_get_defined_custom_rules(): void {
        $this->assertSame(['completionjoingroup'], custom_completion::get_defined_custom_rules());
    }

    /**
     * Every rule the activity can have is listed in the display order.
     *
     * The core compares the order with the conditions that apply and throws a coding exception for a
     * missing one, which takes down the course page. The activity declares grading, so the rule "receive a
     * passing grade" can be on together with the rule of this plugin.
     */
    public function test_the_course_page_lists_all_rules_in_use(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->enablecompletion = true;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course'              => $course->id,
            'grade'               => 10,
            'gradepass'           => 6,
            'completion'          => COMPLETION_TRACKING_AUTOMATIC,
            'completionview'      => 1,
            'completionusegrade'  => 1,
            'completionpassgrade' => 1,
            'completionjoingroup' => 1,
        ]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $cminfo = get_fast_modinfo($course, $user->id)->get_cm($cm->cmid);
        $details = \core_completion\cm_completion_details::get_instance($cminfo, (int) $user->id)->get_details();

        $this->assertEqualsCanonicalizing(
            ['completionview', 'completionusegrade', 'completionpassgrade', 'completionjoingroup'],
            array_keys($details)
        );
    }

    /**
     * The display order names every rule the activity defines.
     */
    public function test_the_sort_order_covers_the_custom_rules(): void {
        [$course, $cm] = $this->create_fixture_for_order();
        $completion = new custom_completion(get_fast_modinfo($course)->get_cm($cm->cmid), 0);

        $this->assertSame([], array_diff($completion->get_defined_custom_rules(), $completion->get_sort_order()));
        $this->assertContains('completionpassgrade', $completion->get_sort_order());
    }

    /**
     * Creates a course with the activity, for the tests of the display order.
     *
     * @return array{0: \stdClass, 1: \stdClass} [course, cm]
     */
    private function create_fixture_for_order(): array {
        $this->resetAfterTest();
        return $this->create_fixture();
    }
}
