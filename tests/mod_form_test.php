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
 * Unit tests for the activity settings form validation.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup;

use advanced_testcase;
use mod_playergroup_mod_form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/playergroup/mod_form.php');

/**
 * Tests for mod_playergroup_mod_form::validation().
 *
 * @covers \mod_playergroup_mod_form
 */
final class mod_form_test extends advanced_testcase {
    /** @var mod_playergroup_mod_form The form under test. */
    private mod_playergroup_mod_form $form;

    /**
     * Builds the form for an existing activity, as when a teacher edits its settings.
     */
    protected function setUp(): void {
        global $PAGE;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('playergroup', $instance->id);
        $PAGE->set_course($course);

        $current = (object) ['instance' => $instance->id, 'id' => $cm->id, 'course' => $course->id];
        $this->form = new mod_playergroup_mod_form($current, 0, $cm, $course);
    }

    /**
     * Returns valid submitted data, overridden by the given values.
     *
     * Carries the keys the parent moodleform_mod::validation() reads, so it runs without notices.
     *
     * @param array $overrides Values replacing the defaults.
     * @return array Form data accepted by validation().
     */
    private function data(array $overrides = []): array {
        return array_merge([
            'name' => 'Teams',
            'modulename' => 'playergroup',
            'instance' => 0,
            'coursemodule' => 0,
            'cmidnumber' => '',
            'minmembers' => 2,
            'maxmembers' => 5,
            'timeopen' => 0,
            'timeclose' => 0,
            'grade' => 0,
            'completion' => 0,
            'availabilityconditionsjson' => '',
        ], $overrides);
    }

    /**
     * Test that the default settings pass.
     */
    public function test_valid_defaults_have_no_errors(): void {
        $this->assertSame([], $this->form->validation($this->data(), []));
    }

    /**
     * Test that a maximum below 1, including the blank-field case that becomes 0, is refused.
     */
    public function test_maxmembers_below_one_is_refused(): void {
        foreach ([0, -3] as $max) {
            $errors = $this->form->validation($this->data(['maxmembers' => $max, 'minmembers' => 1]), []);
            $this->assertSame(get_string('errmaxmembersmin', 'mod_playergroup'), $errors['maxmembers']);
        }
    }

    /**
     * Test that a minimum below 1 is refused.
     */
    public function test_minmembers_below_one_is_refused(): void {
        $errors = $this->form->validation($this->data(['minmembers' => 0]), []);
        $this->assertSame(get_string('errminmembersmin', 'mod_playergroup'), $errors['minmembers']);
    }

    /**
     * Test that a minimum greater than the maximum is refused, and that equal values are not.
     */
    public function test_minmembers_over_maxmembers_is_refused(): void {
        $errors = $this->form->validation($this->data(['minmembers' => 6, 'maxmembers' => 5]), []);
        $this->assertSame(get_string('errminovermax', 'mod_playergroup'), $errors['minmembers']);

        $this->assertSame([], $this->form->validation($this->data(['minmembers' => 5, 'maxmembers' => 5]), []));
    }

    /**
     * Test that a closing date at or before the opening date is refused.
     */
    public function test_timeclose_not_after_timeopen_is_refused(): void {
        $open = 1800000000;
        foreach ([$open - 3600, $open] as $close) {
            $errors = $this->form->validation($this->data(['timeopen' => $open, 'timeclose' => $close]), []);
            $this->assertSame(get_string('errtimeclosebeforeopen', 'mod_playergroup'), $errors['timeclose']);
        }

        $this->assertSame([], $this->form->validation(
            $this->data(['timeopen' => $open, 'timeclose' => $open + 3600]),
            []
        ));
    }

    /**
     * Test that either date alone is accepted, since both are optional.
     */
    public function test_a_single_date_is_accepted(): void {
        $this->assertSame([], $this->form->validation($this->data(['timeopen' => 1800000000]), []));
        $this->assertSame([], $this->form->validation($this->data(['timeclose' => 1800000000]), []));
    }

    /**
     * Test that a scale (stored as a negative scale ID) is refused, while points and none pass.
     */
    public function test_scale_grade_type_is_refused(): void {
        $errors = $this->form->validation($this->data(['grade' => -1]), []);
        $this->assertSame(get_string('errnogradescale', 'mod_playergroup'), $errors['grade']);

        $this->assertSame([], $this->form->validation($this->data(['grade' => 10]), []));
        $this->assertSame([], $this->form->validation($this->data(['grade' => 0]), []));
    }

    /**
     * Test that unticking the completion rule is stored as 0.
     *
     * A browser does not send an unticked checkbox, so the rule is simply missing from the submitted data
     * and, unless the form fills it in, the update leaves the old 1 in the database: the box comes back
     * ticked and the activity keeps the rule the teacher just removed.
     */
    public function test_an_unticked_completion_rule_is_submitted_as_zero(): void {
        $data = (object) ['completionunlocked' => 1, 'completion' => COMPLETION_TRACKING_AUTOMATIC];

        $this->form->data_postprocessing($data);

        $this->assertObjectHasProperty('completionjoingroup', $data);
        $this->assertSame(0, (int) $data->completionjoingroup);
    }

    /**
     * Test that a ticked rule stays on while the tracking is automatic, and goes off when it is not.
     */
    public function test_the_completion_rule_only_stays_on_with_automatic_tracking(): void {
        $ticked = (object) [
            'completionunlocked' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionjoingroup' => 1,
        ];
        $this->form->data_postprocessing($ticked);
        $this->assertSame(1, (int) $ticked->completionjoingroup);

        $manual = (object) [
            'completionunlocked' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
            'completionjoingroup' => 1,
        ];
        $this->form->data_postprocessing($manual);
        $this->assertSame(0, (int) $manual->completionjoingroup);
    }

    /**
     * Test the whole save: the stored rule really goes to 0 when the teacher unticks it.
     */
    public function test_saving_with_the_rule_unticked_clears_it_in_the_database(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $cm = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionjoingroup' => 1,
        ]);
        $this->assertSame(1, (int) $DB->get_field('playergroup', 'completionjoingroup', ['id' => $cm->id]));

        // What the form returns when the box was unticked: the key is absent.
        $data = $DB->get_record('playergroup', ['id' => $cm->id], '*', MUST_EXIST);
        unset($data->completionjoingroup);
        $data->instance = $cm->id;
        $data->groupingmode = 'none';
        $data->completionunlocked = 1;
        $data->completion = COMPLETION_TRACKING_AUTOMATIC;

        $this->form->data_postprocessing($data);
        playergroup_update_instance($data, $this->form);

        $this->assertSame(0, (int) $DB->get_field('playergroup', 'completionjoingroup', ['id' => $cm->id]));
    }

    /**
     * Builds the form the core shows on the "Default activity completion" page for this activity.
     *
     * @return \MoodleQuickForm The form with the activity's completion rules added to it.
     */
    private function default_completion_form(): \MoodleQuickForm {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $module = $DB->get_record('modules', ['name' => 'playergroup'], '*', MUST_EXIST);
        $module->formattedname = get_string('modulename', 'mod_playergroup');

        $defaultform = new \core_completion_defaultedit_form(null, [
            'course' => $course,
            'modules' => [$module->id => $module],
        ]);

        $property = new \ReflectionProperty($defaultform, '_form');
        return $property->getValue($defaultform);
    }

    /**
     * Test that the completion rule survives on the default completion page.
     *
     * The core renders that page with the activity's own form and a suffix ("_playergroup") on every
     * element name, and silently removes any custom rule whose name lacks it. With the rule gone, saving
     * the page drops it from the defaults of every new activity, with no message.
     */
    public function test_the_completion_rule_is_kept_on_the_default_completion_page(): void {
        $mform = $this->default_completion_form();

        // The core checks the group, the name add_completion_rules() returns; the checkbox sits inside it.
        $this->assertTrue($mform->elementExists('completionjoingroupgroup_playergroup'));
        $names = array_map(
            fn($element) => $element->getName(),
            $mform->getElement('completionjoingroupgroup_playergroup')->getElements()
        );
        $this->assertSame(['completionjoingroup_playergroup'], $names);
    }

    /**
     * Test that the rule is read under the suffixed name when the form carries a suffix.
     */
    public function test_the_completion_rule_is_read_with_the_suffix(): void {
        $this->form->set_suffix('_playergroup');

        $this->assertTrue($this->form->completion_rule_enabled(['completionjoingroup_playergroup' => 1]));
        $this->assertFalse($this->form->completion_rule_enabled(['completionjoingroup' => 1]));
    }

    /**
     * Test that the rule keeps its plain name outside the default completion page.
     */
    public function test_the_completion_rule_is_read_without_suffix_on_the_activity_form(): void {
        $this->assertTrue($this->form->completion_rule_enabled(['completionjoingroup' => 1]));
        $this->assertFalse($this->form->completion_rule_enabled([]));
    }

    /**
     * Test that editing an activity tied to a grouping shows that grouping as the existing one.
     */
    public function test_editing_an_activity_with_a_grouping_selects_it(): void {
        $values = ['id' => 7, 'groupingid' => 42];

        $this->form->data_preprocessing($values);

        $this->assertSame('existing', $values['groupingmode']);
        $this->assertSame(42, $values['existinggroupingid']);
    }

    /**
     * Test that editing an activity without a grouping shows the mode "none".
     */
    public function test_editing_an_activity_without_a_grouping_selects_none(): void {
        $values = ['id' => 7, 'groupingid' => 0];

        $this->form->data_preprocessing($values);

        $this->assertSame('none', $values['groupingmode']);
        $this->assertArrayNotHasKey('existinggroupingid', $values);
    }

    /**
     * Test that a new activity keeps the defaults of the form.
     */
    public function test_a_new_activity_keeps_the_grouping_defaults(): void {
        $values = [];

        $this->form->data_preprocessing($values);

        $this->assertArrayNotHasKey('groupingmode', $values);
    }

    /**
     * Test that the groupings of the course are offered, shown as plain text.
     */
    public function test_the_groupings_of_the_course_are_offered_as_plain_text(): void {
        $course = $this->form->get_course();
        $this->getDataGenerator()->create_grouping(['courseid' => $course->id, 'name' => 'Teams <b>A</b> &amp; B']);
        $current = $this->form->get_current();
        $cm = get_coursemodule_from_id('playergroup', $current->id);

        $form = new mod_playergroup_mod_form($current, 0, $cm, $course);
        $property = new \ReflectionProperty($form, '_form');
        $options = $property->getValue($form)->getElement('existinggroupingid')->_options;

        $texts = array_column($options, 'text');
        $this->assertContains('Teams A & B', $texts);
        $this->assertNotContains('Teams <b>A</b> &amp; B', $texts);
    }

    /**
     * Test that a size the column cannot hold is refused instead of failing when the activity is saved.
     *
     * @dataProvider oversized_member_limits
     * @param string $field The field with the large value.
     * @param array $values Submitted values.
     */
    public function test_a_size_over_the_limit_is_refused(string $field, array $values): void {
        $errors = $this->form->validation($this->data($values), []);

        $this->assertArrayHasKey($field, $errors);
        $this->assertSame(get_string('errmembersmax', 'mod_playergroup', 999), $errors[$field]);
    }

    /**
     * Values over the limit, for the maximum and for the minimum.
     *
     * @return array[] Field and submitted values.
     */
    public static function oversized_member_limits(): array {
        return [
            'maximum' => ['maxmembers', ['maxmembers' => 50000]],
            'minimum' => ['minmembers', ['minmembers' => 1000, 'maxmembers' => 2000]],
        ];
    }

    /**
     * Test that the largest size the form accepts is stored.
     */
    public function test_the_limit_itself_is_accepted_and_saved(): void {
        global $DB;

        $this->assertSame([], $this->form->validation($this->data(['minmembers' => 999, 'maxmembers' => 999]), []));

        $activity = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $this->form->get_course()->id, 'minmembers' => 999, 'maxmembers' => 999,
        ]);
        $this->assertSame(999, (int) $DB->get_field('playergroup', 'maxmembers', ['id' => $activity->id]));
    }
}
