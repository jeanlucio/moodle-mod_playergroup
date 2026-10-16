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
}
