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
 * Unit tests for the grades sent to the gradebook.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

use advanced_testcase;

/**
 * Tests for \mod_playergroup\local\grades.
 *
 * @covers \mod_playergroup\local\grades
 */
final class grades_test extends advanced_testcase {
    /**
     * Creates an activity and records the given students as qualified, one minute apart.
     *
     * @param float $grade Maximum grade of the activity (0 = no grade).
     * @param int $students How many students qualify.
     * @return array [activity record, student ids in qualification order]
     */
    private function build_activity(float $grade, int $students): array {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id, 'grade' => $grade]);
        $playergroup = $DB->get_record('playergroup', ['id' => $cm->id], '*', MUST_EXIST);

        $userids = [];
        for ($i = 0; $i < $students; $i++) {
            $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
            $DB->insert_record('playergroup_qualified', (object) [
                'playergroupid' => $playergroup->id,
                'userid'        => $user->id,
                'timequalified' => 1700000000 + $i * MINSECS,
            ]);
            $userids[] = (int) $user->id;
        }

        return [$playergroup, $userids];
    }

    /**
     * Test that, without a list, every student who qualified is graded with the date they qualified.
     */
    public function test_for_students_grades_everyone_without_a_list(): void {
        [$playergroup, $userids] = $this->build_activity(10.0, 3);

        $grades = grades::for_students($playergroup);

        $this->assertEqualsCanonicalizing($userids, array_keys($grades));
        foreach ($userids as $position => $userid) {
            $this->assertEquals($userid, $grades[$userid]->userid);
            $this->assertSame(10.0, $grades[$userid]->rawgrade);
            $this->assertSame(1700000000 + $position * MINSECS, $grades[$userid]->datesubmitted);
        }
    }

    /**
     * Test that a list limits the grades to those students, whatever their number.
     */
    public function test_for_students_keeps_to_the_students_asked_for(): void {
        [$playergroup, $userids] = $this->build_activity(10.0, 4);

        $grades = grades::for_students($playergroup, [$userids[1], $userids[3]]);

        $this->assertEqualsCanonicalizing([$userids[1], $userids[3]], array_keys($grades));
    }

    /**
     * Test that a student who has not qualified is left out even when asked for.
     */
    public function test_for_students_skips_who_never_qualified(): void {
        [$playergroup, $userids] = $this->build_activity(10.0, 1);
        $outsider = $this->getDataGenerator()->create_user();

        $grades = grades::for_students($playergroup, [$userids[0], (int) $outsider->id]);

        $this->assertSame([$userids[0]], array_keys($grades));
    }

    /**
     * Test that an activity without a grade has nothing to send.
     */
    public function test_for_students_is_empty_when_the_activity_has_no_grade(): void {
        [$playergroup] = $this->build_activity(0.0, 2);

        $this->assertSame([], grades::for_students($playergroup));
    }
}
