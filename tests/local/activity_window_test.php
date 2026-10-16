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
 * Unit tests for the activity availability window helper.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

use advanced_testcase;
use moodle_exception;

/**
 * Tests for \mod_playergroup\local\activity_window.
 *
 * @covers \mod_playergroup\local\activity_window
 */
final class activity_window_test extends advanced_testcase {
    /**
     * Builds an activity record with the given bounds.
     *
     * @param int $timeopen Opening timestamp (0 = no limit).
     * @param int $timeclose Closing timestamp (0 = no limit).
     * @return \stdClass Record carrying only the window fields.
     */
    private function activity(int $timeopen, int $timeclose): \stdClass {
        return (object) ['timeopen' => $timeopen, 'timeclose' => $timeclose];
    }

    /**
     * Test that an activity without bounds is always open.
     */
    public function test_unbounded_activity_is_open(): void {
        $this->assertTrue(activity_window::is_open($this->activity(0, 0), 1000));
    }

    /**
     * Test the boundaries: both limits are inclusive, anything outside is closed.
     */
    public function test_window_boundaries(): void {
        $activity = $this->activity(1000, 2000);

        $this->assertFalse(activity_window::is_open($activity, 999));
        $this->assertTrue(activity_window::is_open($activity, 1000));
        $this->assertTrue(activity_window::is_open($activity, 2000));
        $this->assertFalse(activity_window::is_open($activity, 2001));
    }

    /**
     * Test that a single bound applies on its own side only.
     */
    public function test_single_bound(): void {
        $this->assertFalse(activity_window::is_open($this->activity(1000, 0), 999));
        $this->assertTrue(activity_window::is_open($this->activity(1000, 0), PHP_INT_MAX));
        $this->assertTrue(activity_window::is_open($this->activity(0, 2000), 0));
        $this->assertFalse(activity_window::is_open($this->activity(0, 2000), 2001));
    }

    /**
     * Test that a missing property counts as no limit, as when only some columns were selected.
     */
    public function test_missing_properties_mean_no_limit(): void {
        $this->assertTrue(activity_window::is_open(new \stdClass(), 1000));
    }

    /**
     * Test that require_open does nothing inside the window.
     */
    public function test_require_open_passes_inside_window(): void {
        activity_window::require_open($this->activity(time() - HOURSECS, time() + HOURSECS));
        $this->assertTrue(true);
    }

    /**
     * Test that require_open tells "not open yet" apart from "closed".
     */
    public function test_require_open_error_codes(): void {
        try {
            activity_window::require_open($this->activity(time() + HOURSECS, 0));
            $this->fail('Before the window must be refused.');
        } catch (moodle_exception $e) {
            $this->assertSame('activitynotopen', $e->errorcode);
        }

        try {
            activity_window::require_open($this->activity(0, time() - HOURSECS));
            $this->fail('After the window must be refused.');
        } catch (moodle_exception $e) {
            $this->assertSame('activityclosed', $e->errorcode);
        }
    }
}
