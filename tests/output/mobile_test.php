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
 * Unit tests for the mobile app output class.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\output;

use advanced_testcase;
use mod_playergroup\external\create_group;
use mod_playergroup\external\join_group;

/**
 * Tests for \mod_playergroup\output\mobile.
 *
 * @covers \mod_playergroup\output\mobile
 */
final class mobile_test extends advanced_testcase {
    /**
     * Test that mobile_init returns the init.js script verbatim and no templates.
     */
    public function test_mobile_init_returns_init_script(): void {
        global $CFG;

        $result = mobile::mobile_init([]);

        $this->assertSame([], $result['templates']);
        $this->assertSame(
            file_get_contents($CFG->dirroot . '/mod/playergroup/js/mobileapp/init.js'),
            $result['javascript']
        );
    }

    /**
     * Test that mobile_course_view returns the rendered page and the group/member data the
     * app consumes, for a student with an existing group.
     */
    public function test_mobile_course_view_returns_group_and_member_data(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);

        // Explicit, distinct names: the generator's own random pool is small enough that two
        // auto-named users could otherwise collide on fullname(), which the assertions below
        // key on.
        $creator = $this->getDataGenerator()->create_user(['firstname' => 'Creator', 'lastname' => 'One']);
        $this->getDataGenerator()->enrol_user($creator->id, $course->id, 'student');
        $this->setUser($creator);
        $created = create_group::execute($cm->cmid, 'Mobile Group', '', '🛡', 0, '');

        $joiner = $this->getDataGenerator()->create_user(['firstname' => 'Joiner', 'lastname' => 'Two']);
        $this->getDataGenerator()->enrol_user($joiner->id, $course->id, 'student');
        $this->setUser($joiner);
        join_group::execute($cm->cmid, $created['groupid'], '');

        $result = mobile::mobile_course_view([
            'cmid'            => $cm->cmid,
            'courseid'        => $course->id,
            'appversioncode'  => 40100,
        ]);

        $this->assertSame('main', $result['templates'][0]['id']);
        $this->assertStringContainsString('core-loading', $result['templates'][0]['html']);
        $this->assertSame($cm->cmid, $result['otherdata']['cmid']);

        $groups = json_decode($result['otherdata']['groups'], true);
        $this->assertCount(1, $groups);
        $this->assertCount(2, $groups[0]['members']);

        $isleaderbyname = [];
        foreach ($groups[0]['members'] as $member) {
            $isleaderbyname[$member['fullname']] = $member['isleader'];
        }
        $this->assertTrue($isleaderbyname[fullname($creator)]);
        $this->assertFalse($isleaderbyname[fullname($joiner)]);
    }

    /**
     * Test that the rendered mobile page binds the group card's description to the sanitised
     * field, not the raw one.
     *
     * get_activity_data::execute() returns both 'description' (through format_text(), safe to
     * render as HTML) and 'rawdescription' (the raw DB value, kept only so courseview.js can
     * prefill the plain-text edit form). The mobile_view_page template must bind
     * core-format-text's [text] to 'description' — binding it to 'rawdescription' would render
     * whatever HTML a group's description holds directly in the app webview.
     */
    public function test_mobile_course_view_binds_sanitised_description(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);

        $creator = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($creator->id, $course->id, 'student');
        $this->setUser($creator);
        create_group::execute($cm->cmid, 'Mobile Group', '', '🛡', 0, '');

        $result = mobile::mobile_course_view([
            'cmid'            => $cm->cmid,
            'courseid'        => $course->id,
            'appversioncode'  => 40100,
        ]);

        $html = $result['templates'][0]['html'];
        $this->assertStringContainsString('[text]="group.description"', $html);
        $this->assertStringNotContainsString('[text]="group.rawdescription"', $html);
    }

    /**
     * Test that a user without mod/playergroup:view cannot fetch the mobile page data.
     *
     * Prohibiting the activity's own view capability makes the module inaccessible to
     * require_login() itself (it is what makes the cm "uservisible"), so the denial surfaces
     * as a moodle_exception before the explicit require_capability() call in mobile_course_view
     * is even reached. Either way, access is correctly denied.
     */
    public function test_mobile_course_view_requires_capability(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $context = \context_module::instance($cm->cmid);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $studentrole = $this->getDataGenerator()->create_role();
        assign_capability('mod/playergroup:view', CAP_PROHIBIT, $studentrole, $context);
        role_assign($studentrole, $student->id, $context);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($student);

        $this->expectException(\moodle_exception::class);
        mobile::mobile_course_view(['cmid' => $cm->cmid, 'courseid' => $course->id, 'appversioncode' => 40100]);
    }

    /**
     * Test that the app gets the right to create groups and only shows the button to who has it.
     */
    public function test_mobile_course_view_offers_creating_a_group_only_with_the_capability(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $args = ['cmid' => $cm->cmid, 'courseid' => $course->id, 'appversioncode' => 40100];

        $result = mobile::mobile_course_view($args);
        $this->assertArrayHasKey('cancreate', $result['otherdata']);
        $this->assertSame(1, $result['otherdata']['cancreate']);

        $studentrole = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        assign_capability(
            'mod/playergroup:creategroup',
            CAP_PROHIBIT,
            $studentrole,
            \context_course::instance($course->id)->id,
            true
        );

        $result = mobile::mobile_course_view($args);
        $this->assertArrayHasKey('cancreate', $result['otherdata']);
        $this->assertSame(0, $result['otherdata']['cancreate']);
    }

    /**
     * Test that the app's Create group button depends on that right.
     *
     * The Angular condition cannot be run in PHPUnit, so the rendered markup is checked.
     */
    public function test_mobile_course_view_binds_the_create_button_to_the_capability(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $result = mobile::mobile_course_view(['cmid' => $cm->cmid, 'courseid' => $course->id, 'appversioncode' => 40100]);

        $this->assertMatchesRegularExpression(
            '/class="pg-create-btn"\s+\*ngIf="[^"]*CONTENT_OTHERDATA\.cancreate/',
            $result['templates'][0]['html']
        );
    }

    /**
     * Test that the page the app renders asks for the visit to be recorded, for this activity.
     *
     * The app never loads view.php, so this call is the only way its visit reaches the completion rule.
     */
    public function test_mobile_course_view_records_the_visit(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $result = mobile::mobile_course_view(['cmid' => $cm->cmid, 'courseid' => $course->id]);

        $html = $result['templates'][0]['html'];
        $this->assertStringContainsString('core-site-plugins-call-ws-on-load', $html);
        $this->assertStringContainsString('name="mod_playergroup_view_playergroup"', $html);
        $this->assertStringContainsString('{cmid: ' . $cm->cmid . '}', $html);
    }

    /**
     * Test that the app is told whether closing a group is offered.
     */
    public function test_mobile_course_view_tells_whether_a_group_can_be_closed(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $result = mobile::mobile_course_view(['cmid' => $cm->cmid, 'courseid' => $course->id]);

        $this->assertSame(1, $result['otherdata']['canclose']);
        $this->assertStringContainsString('CONTENT_OTHERDATA.canclose', $result['templates'][0]['html']);
    }

    /**
     * Test that the app lets the name of a group be as long as the server accepts, like the web page.
     */
    public function test_mobile_course_view_allows_the_same_name_length_as_the_server(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $html = mobile::mobile_course_view(['cmid' => $cm->cmid, 'courseid' => $course->id])['templates'][0]['html'];

        $limit = \mod_playergroup\local\text::MAX_GROUP_NAME;
        $this->assertSame(2, substr_count($html, 'Data.name" type="text" maxlength="' . $limit . '"'));
        $this->assertStringNotContainsString('maxlength="80"', $html);
    }
}
