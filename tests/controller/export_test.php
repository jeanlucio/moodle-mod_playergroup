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
 * Unit tests for the activity log export controller.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\controller;

use advanced_testcase;
use context_module;

/**
 * Tests for \mod_playergroup\controller\export.
 *
 * @covers \mod_playergroup\controller\export
 */
final class export_test extends advanced_testcase {
    /**
     * Test that the CSV export contains a localized row for each logged event.
     */
    public function test_execute_streams_csv_with_logged_events(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback(); // Logging waits until the transaction is committed.

        // The standard logstore is not enabled by default in the PHPUnit test site, and even
        // when enabled it buffers writes; both must be set explicitly for the trigger() call
        // below to land synchronously in logstore_standard_log.
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        set_config('logguests', 1, 'logstore_standard');
        get_log_manager(true);

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $context = context_module::instance($cm->cmid);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        \mod_playergroup\event\group_created::create([
            'context'  => $context,
            'objectid' => $group->id,
        ])->trigger();

        $controller = new export();
        ob_start();
        $controller->execute($context->id, 'csv', $course->shortname);
        $csv = ob_get_clean();

        $this->assertStringContainsString(get_string('event_group_created', 'mod_playergroup'), $csv);
        $this->assertStringContainsString(fullname($student), $csv);
    }

    /**
     * Test that an activity with no logged events produces an (almost) empty export —
     * just the header row, no data rows.
     */
    public function test_execute_streams_csv_with_no_events(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $cm = $this->getDataGenerator()->create_module('playergroup', ['course' => $course->id]);
        $context = context_module::instance($cm->cmid);

        $controller = new export();
        ob_start();
        $controller->execute($context->id, 'csv', $course->shortname);
        $csv = ob_get_clean();

        $this->assertStringContainsString(get_string('report_action', 'mod_playergroup'), $csv);
        $this->assertStringNotContainsString(get_string('event_group_created', 'mod_playergroup'), $csv);
    }
}
