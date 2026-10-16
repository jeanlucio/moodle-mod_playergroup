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
 * Tests that the declarations in db/ point at things that exist.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup;

use advanced_testcase;

/**
 * The files in db/ are arrays the core reads at install and upgrade time. A misspelled class, method,
 * string or capability there fails only when the feature is used, often on the app or in an observer,
 * and no other test loads them.
 *
 * @coversNothing
 */
final class db_declarations_test extends advanced_testcase {
    /**
     * Loads db/services.php.
     *
     * @return array The declared external functions.
     */
    private function services(): array {
        global $CFG;

        $functions = [];
        require($CFG->dirroot . '/mod/playergroup/db/services.php');
        return $functions;
    }

    /**
     * Test that every declared external function points at a class and method that exist.
     */
    public function test_every_declared_service_has_its_class_and_method(): void {
        $functions = $this->services();

        $this->assertNotEmpty($functions);
        foreach ($functions as $name => $definition) {
            $this->assertTrue(class_exists($definition['classname']), "$name: class {$definition['classname']} is missing");
            $this->assertTrue(
                method_exists($definition['classname'], $definition['methodname']),
                "$name: method {$definition['methodname']} is missing"
            );
            $this->assertTrue(method_exists($definition['classname'], 'execute_parameters'), "$name: no execute_parameters()");
            $this->assertTrue(method_exists($definition['classname'], 'execute_returns'), "$name: no execute_returns()");
        }
    }

    /**
     * Test that the parameters and the returns of every service are valid external descriptions.
     */
    public function test_every_service_describes_its_parameters_and_returns(): void {
        foreach ($this->services() as $name => $definition) {
            $class = $definition['classname'];

            $this->assertInstanceOf(\core_external\external_function_parameters::class, $class::execute_parameters(), $name);
            $this->assertInstanceOf(\core_external\external_description::class, $class::execute_returns(), $name);
        }
    }

    /**
     * Test that every external class in the plugin is declared, so a new service is not forgotten.
     */
    public function test_every_external_class_is_declared(): void {
        global $CFG;

        $declared = array_map(fn(array $d): string => $d['classname'], $this->services());
        $files = glob($CFG->dirroot . '/mod/playergroup/classes/external/*.php');

        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $class = 'mod_playergroup\\external\\' . basename($file, '.php');
            $this->assertContains($class, $declared, "$class is not declared in db/services.php");
        }
    }

    /**
     * Test that a service that only reads is declared as read, and the others as write.
     *
     * The type decides, among other things, whether the call is allowed in a read-only session.
     */
    public function test_services_are_declared_with_the_right_type(): void {
        $functions = $this->services();

        $this->assertSame('read', $functions['mod_playergroup_get_activity_data']['type']);
        foreach ($functions as $name => $definition) {
            if ($name !== 'mod_playergroup_get_activity_data') {
                $this->assertSame('write', $definition['type'], $name);
            }
        }
    }

    /**
     * Test that every service is available to the AJAX interface the pages use and to the mobile app.
     */
    public function test_services_are_available_to_ajax_and_the_app(): void {
        foreach ($this->services() as $name => $definition) {
            $this->assertTrue($definition['ajax'], "$name is not available to AJAX");
            $this->assertContains(MOODLE_OFFICIAL_MOBILE_SERVICE, $definition['services'], "$name is not in the app service");
        }
    }

    /**
     * Loads db/mobile.php.
     *
     * @return array The addons declared for the app.
     */
    private function addons(): array {
        global $CFG;

        $addons = [];
        require($CFG->dirroot . '/mod/playergroup/db/mobile.php');
        return $addons;
    }

    /**
     * Test that the app handler calls methods that exist, and its stylesheet is there.
     */
    public function test_the_mobile_handler_points_at_real_methods_and_files(): void {
        global $CFG;

        $handler = $this->addons()['mod_playergroup']['handlers']['playergroup'];

        $this->assertTrue(method_exists(\mod_playergroup\output\mobile::class, $handler['method']));
        $this->assertTrue(method_exists(\mod_playergroup\output\mobile::class, $handler['init']));
        $this->assertSame('CoreCourseModuleDelegate', $handler['delegate']);
        $this->assertFileExists($CFG->dirroot . '/mod/playergroup/styles_app.css');
        $this->assertFileExists($CFG->dirroot . '/mod/playergroup/pix/icon.svg');
    }

    /**
     * Test that every language string the app is told to download exists.
     *
     * A missing one makes the app show the raw key.
     */
    public function test_every_mobile_language_string_exists(): void {
        $manager = get_string_manager();

        foreach ($this->addons()['mod_playergroup']['lang'] as [$identifier, $component]) {
            $this->assertTrue($manager->string_exists($identifier, 'mod_' . $component), "String $identifier is missing");
        }
    }

    /**
     * Test that the message provider has its capability and its name string.
     */
    public function test_the_message_provider_is_complete(): void {
        global $CFG, $DB;

        $messageproviders = [];
        require($CFG->dirroot . '/mod/playergroup/db/messages.php');

        $this->assertArrayHasKey('invite', $messageproviders);
        $this->assertTrue($DB->record_exists('capabilities', ['name' => $messageproviders['invite']['capability']]));
        $this->assertTrue(get_string_manager()->string_exists('messageprovider:invite', 'mod_playergroup'));
    }

    /**
     * Test that every observer calls a method that exists, for an event that exists.
     */
    public function test_every_observer_is_callable(): void {
        global $CFG;

        $observers = [];
        require($CFG->dirroot . '/mod/playergroup/db/events.php');

        $this->assertNotEmpty($observers);
        foreach ($observers as $observer) {
            $this->assertTrue(class_exists($observer['eventname']), "{$observer['eventname']} does not exist");
            $this->assertTrue(is_callable($observer['callback']), 'An observer callback is not callable');
        }
    }
}
