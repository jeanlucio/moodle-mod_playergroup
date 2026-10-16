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
 * Unit tests for the plain-text helper.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

use advanced_testcase;

/**
 * Tests for \mod_playergroup\local\text.
 *
 * @covers \mod_playergroup\local\text
 */
final class text_test extends advanced_testcase {
    /**
     * Test that special characters come back as typed, under both values of the site setting that
     * changes what format_string() returns.
     */
    public function test_plain_returns_the_text_as_typed(): void {
        $this->resetAfterTest();

        foreach ([1, 0] as $striptags) {
            set_config('formatstringstriptags', $striptags);
            $this->assertSame('P&D', text::plain('P&D'), "striptags=$striptags");
            $this->assertSame('Ana & Pedro', text::plain('Ana & Pedro'), "striptags=$striptags");
            $this->assertSame("O'Neil \"x\" < 3", text::plain("O'Neil \"x\" < 3"), "striptags=$striptags");
        }
    }

    /**
     * Test that an already-escaped entity is decoded once, not left for the destination to show.
     */
    public function test_plain_decodes_entities(): void {
        $this->resetAfterTest();

        foreach ([1, 0] as $striptags) {
            set_config('formatstringstriptags', $striptags);
            $this->assertSame('P&D', text::plain('P&amp;D'), "striptags=$striptags");
        }
    }

    /**
     * Test that markup is removed, so the result can never be taken for HTML.
     */
    public function test_plain_strips_markup(): void {
        $this->resetAfterTest();

        foreach ([1, 0] as $striptags) {
            set_config('formatstringstriptags', $striptags);
            $this->assertSame('bold', text::plain('<b>bold</b>'), "striptags=$striptags");
            $this->assertStringNotContainsString('<', text::plain('<script>alert(1)</script>x'), "striptags=$striptags");
        }
    }

    /**
     * Test that empty input stays empty.
     */
    public function test_plain_handles_empty_input(): void {
        $this->assertSame('', text::plain(null));
        $this->assertSame('', text::plain(''));
    }

    /**
     * Test that a destination that escapes once, as a double-mustache variable does, shows the typed text.
     *
     * This is the defect itself: with format_string() the same name reaches the screen as "P&amp;D".
     */
    public function test_escaping_the_result_once_shows_the_typed_name(): void {
        $this->resetAfterTest();

        foreach ([1, 0] as $striptags) {
            set_config('formatstringstriptags', $striptags);
            $this->assertSame('P&amp;D', s(text::plain('P&D')), "striptags=$striptags");
            $this->assertSame('P&amp;amp;D', s(format_string('P&D')), "format_string is the double escape, striptags=$striptags");
        }
    }
}
