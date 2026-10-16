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
 * Unit tests for the get_activity_data external function.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\external;

use advanced_testcase;
use core_external\external_api;

/**
 * Tests for \mod_playergroup\external\get_activity_data.
 *
 * @covers \mod_playergroup\external\get_activity_data
 */
final class get_activity_data_test extends advanced_testcase {
    /**
     * Test that each returned group carries its member list, with the creator flagged as
     * leader, and that the response validates against execute_returns() — this is what the
     * mobile app actually consumes, so a PARAM type mismatch here would break it silently.
     */
    public function test_execute_includes_group_members(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);

        // Explicit, distinct names: the generator's own random pool is small enough that two
        // auto-named users could otherwise collide on fullname(), which the assertions below
        // key on.
        $creator = $this->getDataGenerator()->create_user(['firstname' => 'Creator', 'lastname' => 'One']);
        $this->getDataGenerator()->enrol_user($creator->id, $course->id, 'student');
        $this->setUser($creator);
        $created = create_group::execute($cm->cmid, 'Test Group', '', '🛡', 0, '');

        $joiner = $this->getDataGenerator()->create_user(['firstname' => 'Joiner', 'lastname' => 'Two']);
        $this->getDataGenerator()->enrol_user($joiner->id, $course->id, 'student');
        $this->setUser($joiner);
        join_group::execute($cm->cmid, $created['groupid'], '');

        $result = get_activity_data::execute($cm->cmid);
        $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), $result);

        $this->assertCount(1, $result['groups']);
        $members = $result['groups'][0]['members'];
        $this->assertCount(2, $members);

        $isleaderbyname = [];
        foreach ($members as $member) {
            $isleaderbyname[$member['fullname']] = $member['isleader'];
        }
        $this->assertTrue($isleaderbyname[fullname($creator)]);
        $this->assertFalse($isleaderbyname[fullname($joiner)]);
        $this->assertStringContainsString('Test Group', $result['groups'][0]['membersheading']);
    }

    /**
     * Test that the member list is ordered leader-first, then alphabetically - not by join
     * order, which would otherwise put the earliest joiner first regardless of who actually
     * leads the group today.
     */
    public function test_execute_orders_members_leader_first_then_alphabetically(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);

        $creator = $this->getDataGenerator()->create_user(['firstname' => 'Creator', 'lastname' => 'One']);
        $this->getDataGenerator()->enrol_user($creator->id, $course->id, 'student');
        $this->setUser($creator);
        $created = create_group::execute($cm->cmid, 'Ordered Group', '', '🛡', 0, '');

        // Joins in this order (Zulema before Alice), so join order and alphabetical order
        // disagree - only the fix sorts the non-leaders alphabetically instead of by join time.
        $zulema = $this->getDataGenerator()->create_user(['firstname' => 'Zulema', 'lastname' => 'Nunes']);
        $this->getDataGenerator()->enrol_user($zulema->id, $course->id, 'student');
        $this->setUser($zulema);
        join_group::execute($cm->cmid, $created['groupid'], '');

        $alice = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'Vale']);
        $this->getDataGenerator()->enrol_user($alice->id, $course->id, 'student');
        $this->setUser($alice);
        join_group::execute($cm->cmid, $created['groupid'], '');

        $result = get_activity_data::execute($cm->cmid);
        $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), $result);

        $names = array_column($result['groups'][0]['members'], 'fullname');
        $this->assertSame([fullname($creator), fullname($alice), fullname($zulema)], $names);
    }

    /**
     * Builds an activity with one group of two members and a student who has no group.
     *
     * @return array [course, cmid, creator, joiner, viewer]
     */
    private function build_group_with_outsider(): array {
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);

        $creator = $this->getDataGenerator()->create_user(['firstname' => 'Creator', 'lastname' => 'One']);
        $joiner = $this->getDataGenerator()->create_user(['firstname' => 'Joiner', 'lastname' => 'Two']);
        $viewer = $this->getDataGenerator()->create_user(['firstname' => 'Viewer', 'lastname' => 'Three']);
        foreach ([$creator, $joiner, $viewer] as $user) {
            $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        }

        $this->setUser($creator);
        $created = create_group::execute($cm->cmid, 'Test Group', '', '🛡', 0, '');
        $this->setUser($joiner);
        join_group::execute($cm->cmid, $created['groupid'], '');

        return [$course, $cm->cmid, $creator, $joiner, $viewer];
    }

    /**
     * Removes the right to see the course participant list from the student role in the course.
     *
     * @param \stdClass $course Course where the override applies.
     */
    private function hide_participants_from_students(\stdClass $course): void {
        global $DB;

        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability(
            'moodle/course:viewparticipants',
            CAP_PREVENT,
            $studentrole,
            \context_course::instance($course->id)->id,
            true
        );
    }

    /**
     * Test that names of other groups' members and leaders are withheld from a user who cannot
     * see the course participants, the same decision the invite picker already applies.
     *
     * The member count stays visible; only the names are withheld, and they must not appear
     * anywhere in the response.
     */
    public function test_execute_hides_names_without_viewparticipants(): void {
        $this->resetAfterTest();

        [$course, $cmid, $creator, $joiner, $viewer] = $this->build_group_with_outsider();
        $this->hide_participants_from_students($course);

        $this->setUser($viewer);
        $result = get_activity_data::execute($cmid);
        $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), $result);

        $group = $result['groups'][0];
        $this->assertFalse($group['canviewmembers']);
        $this->assertSame([], $group['members']);
        $this->assertSame('', $group['leaderbadge']);
        $this->assertSame(2, $group['membercount']);

        $encoded = json_encode($result);
        $this->assertStringNotContainsString($creator->lastname, $encoded);
        $this->assertStringNotContainsString($joiner->firstname, $encoded);
    }

    /**
     * Test that a member always sees the names in their own group, even without the capability.
     */
    public function test_execute_shows_names_in_own_group_without_viewparticipants(): void {
        $this->resetAfterTest();

        [$course, $cmid, $creator, $joiner] = $this->build_group_with_outsider();
        $this->hide_participants_from_students($course);

        $this->setUser($joiner);
        $result = get_activity_data::execute($cmid);
        $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), $result);

        $group = $result['groups'][0];
        $this->assertTrue($group['canviewmembers']);
        $this->assertEqualsCanonicalizing(
            [fullname($creator), fullname($joiner)],
            array_column($group['members'], 'fullname')
        );
        $this->assertNotSame('', $group['leaderbadge']);
    }

    /**
     * Test that a user with the capability keeps seeing every group's members.
     */
    public function test_execute_shows_names_with_viewparticipants(): void {
        $this->resetAfterTest();

        [, $cmid, , , $viewer] = $this->build_group_with_outsider();

        $this->setUser($viewer);
        $result = get_activity_data::execute($cmid);
        $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), $result);

        $this->assertTrue($result['groups'][0]['canviewmembers']);
        $this->assertCount(2, $result['groups'][0]['members']);
    }

    /**
     * Test that the app is told why the student's own group does not count yet, and only for that group.
     */
    public function test_execute_reports_missing_members_for_the_own_group_only(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'minmembers' => 3,
        ]);
        [$mine, $theirs] = [$this->getDataGenerator()->create_user(), $this->getDataGenerator()->create_user()];
        foreach ([$mine, $theirs] as $user) {
            $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        }
        $this->setUser($theirs);
        $theirgroup = create_group::execute($cm->cmid, 'Theirs', '', '🛡', 0, '')['groupid'];
        $this->setUser($mine);
        $mygroup = create_group::execute($cm->cmid, 'Mine', '', '🛡', 0, '')['groupid'];

        $result = get_activity_data::execute($cm->cmid);
        $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), $result);

        $messages = array_column($result['groups'], 'missingmessage', 'groupid');
        $this->assertSame(get_string('groupneedsmembers', 'mod_playergroup', 2), $messages[$mygroup]);
        $this->assertSame('', $messages[$theirgroup]);
    }

    /**
     * Test that names with an ampersand reach the app as typed, with no HTML escaping of their own.
     *
     * The app prints these fields as text, so an "&amp;" in them would be shown literally.
     */
    public function test_execute_returns_names_as_plain_text(): void {
        $this->resetAfterTest();

        foreach ([1, 0] as $striptags) {
            set_config('formatstringstriptags', $striptags);
            $course = $this->getDataGenerator()->create_course();
            $cm = $this->getDataGenerator()->create_module('playergroup', [
                'course' => $course->id,
                'name' => 'Q&A activity',
            ]);
            $student = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
            $this->setUser($student);
            create_group::execute($cm->cmid, 'Ana & Pedro', '', '🛡', 0, '');

            $result = get_activity_data::execute($cm->cmid);
            $result = external_api::clean_returnvalue(get_activity_data::execute_returns(), $result);

            $this->assertSame('Q&A activity', $result['name'], "striptags=$striptags");
            $this->assertSame('Ana & Pedro', $result['groups'][0]['name'], "striptags=$striptags");
            $this->assertStringContainsString('Ana & Pedro', $result['groups'][0]['membersheading']);
            $this->assertStringNotContainsString('&amp;', json_encode($result));
        }
    }
}
