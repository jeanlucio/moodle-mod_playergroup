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
 * Tests for playergroup_grade_item_update() in lib.php.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup;

/**
 * Tests for playergroup_grade_item_update().
 *
 * @covers ::playergroup_grade_item_update
 */
final class lib_grade_item_update_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->libdir . '/gradelib.php');
    }

    /**
     * Fetches the grade_item for the given instance, requiring it to exist.
     *
     * @param \stdClass $instance Activity instance.
     * @return \grade_item
     */
    private function fetch_grade_item(\stdClass $instance): \grade_item {
        return \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'playergroup',
            'iteminstance' => $instance->id,
            'itemnumber' => 0,
            'courseid' => $instance->course,
        ]);
    }

    /**
     * Regression test: grade_update() (lib/gradelib.php) silently drops any
     * 'gradepass' key in the $itemdetails array it is given — its own internal
     * allow-list does not include it — so a configured pass grade must be applied
     * directly on the grade_item instead. Before the fix, this assertion failed with
     * gradepass staying at 0.0 no matter what the instance configured.
     *
     * @return void
     */
    public function test_gradepass_is_applied_to_the_grade_item(): void {
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playergroup', [
            'course' => $course->id,
            'grade' => 10,
        ]);
        $instance->gradepass = 6;

        $result = playergroup_grade_item_update($instance);

        $this->assertSame(GRADE_UPDATE_OK, $result);
        $gradeitem = $this->fetch_grade_item($instance);
        $this->assertEqualsWithDelta(6.0, (float) $gradeitem->gradepass, 0.001);
    }

    /**
     * Test that a reset removes the recorded grades and keeps the grade item.
     *
     * The core asks for it when a teacher resets the course gradebook; the item and its settings must
     * stay so the next qualification has somewhere to be recorded.
     */
    public function test_reset_clears_the_grades_and_keeps_the_item(): void {
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $instance = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id, 'grade' => 10]);
        playergroup_grade_item_update($instance, [$student->id => (object) ['userid' => $student->id, 'rawgrade' => 10]]);
        $gradeitem = $this->fetch_grade_item($instance);
        $this->assertEquals(10.0, (float) $gradeitem->get_grade($student->id, false)->finalgrade);

        $result = playergroup_grade_item_update($instance, 'reset');

        $this->assertSame(GRADE_UPDATE_OK, $result);
        $gradeitem = $this->fetch_grade_item($instance);
        $this->assertNotEmpty($gradeitem, 'The item is kept.');
        $this->assertNull($gradeitem->get_grade($student->id, false)->finalgrade);
        $this->assertEqualsWithDelta(10.0, (float) $gradeitem->grademax, 0.001);
    }

    /**
     * Test that an activity without a grade leaves nothing in the gradebook.
     */
    public function test_an_activity_without_a_grade_creates_no_grade_item(): void {
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id, 'grade' => 0]);

        $gradeitem = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'playergroup',
            'iteminstance' => $instance->id,
            'itemnumber' => 0,
            'courseid' => $course->id,
        ]);

        $this->assertFalse($gradeitem);
    }
}
