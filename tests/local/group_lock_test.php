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
 * Unit tests for the group_lock helper.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\local;

use advanced_testcase;
use moodle_database;
use moodle_exception;

/**
 * Tests for \mod_playergroup\local\group_lock.
 *
 * Advisory locks (both Postgres and MySQL backends) are reentrant per connection: a session
 * can "reacquire" a resource it already holds without ever blocking. Simulating a genuinely
 * concurrent request therefore requires a second, independently-connected database session,
 * not just a second lock_factory instance on the same connection.
 *
 * @covers \mod_playergroup\local\group_lock
 */
final class group_lock_test extends advanced_testcase {
    /** @var ?moodle_database Second, independent connection used to simulate a concurrent request. */
    private ?moodle_database $seconddb = null;

    /**
     * Reset the Moodle environment between tests.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Dispose of the second connection, if one was opened.
     */
    protected function tearDown(): void {
        if ($this->seconddb !== null) {
            $this->seconddb->dispose();
            $this->seconddb = null;
        }
        parent::tearDown();
    }

    /**
     * Test that acquire() returns a releasable lock when the resource is free.
     */
    public function test_acquire_returns_lock_when_free(): void {
        $lock = group_lock::acquire(123456789);

        $this->assertInstanceOf(\core\lock\lock::class, $lock);
        $this->assertTrue($lock->release());
    }

    /**
     * Test that acquire() throws grouplockbusy when another connection already holds the
     * lock for the same activity instance.
     */
    public function test_acquire_throws_when_locked_by_another_connection(): void {
        $playergroupid = 987654321;
        $otherlock = $this->acquire_on_second_connection('group_' . $playergroupid);

        try {
            group_lock::acquire($playergroupid);
            $this->fail('Expected a moodle_exception because the resource is held by another connection.');
        } catch (moodle_exception $e) {
            $this->assertEquals('grouplockbusy', $e->errorcode);
        } finally {
            $otherlock->release();
        }
    }

    /**
     * Test that locking one activity instance does not block a different instance.
     */
    public function test_acquire_does_not_block_a_different_instance(): void {
        $otherlock = $this->acquire_on_second_connection('group_111222333');

        try {
            $lock = group_lock::acquire(444555666);
            $this->assertInstanceOf(\core\lock\lock::class, $lock);
            $lock->release();
        } finally {
            $otherlock->release();
        }
    }

    /**
     * Acquires the mod_playergroup lock factory's lock for the given resource key from a
     * second, independently-connected database session, so contention against it reflects a
     * genuinely different session rather than this test's own (self-reentrant) connection.
     *
     * @param string $resourcekey The bare resource key (without the factory's type prefix).
     * @return \core\lock\lock The lock, held on the second connection; the caller must release it.
     */
    private function acquire_on_second_connection(string $resourcekey): \core\lock\lock {
        global $DB;

        $cfg = $DB->export_dbconfig();
        if (!isset($cfg->dboptions)) {
            $cfg->dboptions = [];
        }

        $this->seconddb = moodle_database::get_driver_instance($cfg->dbtype, $cfg->dblibrary);
        $this->seconddb->connect($cfg->dbhost, $cfg->dbuser, $cfg->dbpass, $cfg->dbname, $cfg->prefix, $cfg->dboptions);

        $original = $GLOBALS['DB'];
        $GLOBALS['DB'] = $this->seconddb;
        try {
            $factory = \core\lock\lock_config::get_lock_factory('mod_playergroup');
            $lock = $factory->get_lock($resourcekey, 1);
        } finally {
            $GLOBALS['DB'] = $original;
        }

        if (!$lock) {
            $this->fail('Precondition: the second connection must be able to acquire the lock.');
        }

        return $lock;
    }
}
