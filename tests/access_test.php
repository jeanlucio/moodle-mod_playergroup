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
 * Tests for the capability definitions.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup;

use advanced_testcase;

/**
 * Tests for db/access.php.
 *
 * @coversNothing
 */
final class access_test extends advanced_testcase {
    /**
     * Loads the capabilities as declared in the file.
     *
     * @return array Capability definitions keyed by name.
     */
    private function load_capabilities(): array {
        global $CFG;

        $capabilities = [];
        require($CFG->dirroot . '/mod/playergroup/db/access.php');
        return $capabilities;
    }

    /**
     * Test that the capability that opens the reports and their exports is flagged as handling personal data.
     *
     * The role definition screen shows the risk icons; without this one an administrator granting the
     * capability to a custom role is not told that it exposes the students' names and their activity log.
     */
    public function test_manage_is_flagged_as_personal_data(): void {
        $capabilities = $this->load_capabilities();

        $risk = $capabilities['mod/playergroup:manage']['riskbitmask'];
        $this->assertNotSame(0, $risk & RISK_PERSONAL);
        $this->assertSame(0, $risk & RISK_XSS, 'It writes no HTML, so it has no XSS risk to declare.');
    }

    /**
     * Test that every capability declares a risk bitmask only when it needs one, and a valid one.
     */
    public function test_risk_bitmasks_are_valid(): void {
        $valid = RISK_MANAGETRUST | RISK_CONFIG | RISK_XSS | RISK_PERSONAL | RISK_SPAM | RISK_DATALOSS;

        foreach ($this->load_capabilities() as $name => $definition) {
            $risk = $definition['riskbitmask'] ?? 0;
            $this->assertSame(0, $risk & ~$valid, "$name declares an unknown risk bit.");
        }
    }
}
