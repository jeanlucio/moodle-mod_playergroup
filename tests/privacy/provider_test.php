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
 * Unit tests for the mod_playergroup Privacy Provider.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\tests\provider_testcase;

/**
 * Tests for \mod_playergroup\privacy\provider.
 *
 * @covers \mod_playergroup\privacy\provider
 */
final class provider_test extends provider_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Course-module row. */
    private \stdClass $cm;

    /** @var \stdClass Student who creates a group (creator). */
    private \stdClass $creator;

    /** @var \stdClass Student who receives an invite. */
    private \stdClass $receiver;

    /** @var int Native Moodle group id. */
    private int $groupid;

    /**
     * Builds a minimal but complete fixture for every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        global $DB, $CFG;
        require_once($CFG->dirroot . '/group/lib.php');

        $this->course  = $this->getDataGenerator()->create_course();
        $this->cm      = $this->getDataGenerator()->create_module('playergroup', ['course' => $this->course->id]);
        $this->creator = $this->getDataGenerator()->create_user();
        $this->receiver = $this->getDataGenerator()->create_user();

        // Create a native Moodle group and add creator as member.
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->groupid = $group->id;
        groups_add_member($this->groupid, $this->creator->id);

        // Insert playergroup_meta.
        $DB->insert_record('playergroup_meta', (object) [
            'playergroupid' => $this->cm->id,
            'groupid'       => $this->groupid,
            'creatorid'     => $this->creator->id,
            'badge'         => '⚔',
            'privacy'       => 0,
            'password'      => '',
            'timecreated'   => time(),
            'timemodified'  => time(),
        ]);

        // Insert playergroup_invites (creator → receiver).
        $DB->insert_record('playergroup_invites', (object) [
            'playergroupid' => $this->cm->id,
            'groupid'       => $this->groupid,
            'senderid'      => $this->creator->id,
            'receiverid'    => $this->receiver->id,
            'status'        => 0,
            'timecreated'   => time(),
            'timemodified'  => time(),
        ]);
    }

    /**
     * Test that get_metadata declares both plugin tables.
     */
    public function test_get_metadata_declares_tables(): void {
        $collection = new collection('mod_playergroup');
        $result = provider::get_metadata($collection);

        $this->assertInstanceOf(collection::class, $result);

        $items = $result->get_collection();
        $tablenames = array_map(fn($item) => $item->get_name(), $items);

        $this->assertContains('playergroup_meta', $tablenames);
        $this->assertContains('playergroup_invites', $tablenames);
    }

    /**
     * Test that the creator's context is returned.
     */
    public function test_get_contexts_for_userid_creator(): void {
        $contextlist = provider::get_contexts_for_userid($this->creator->id);

        $contextids = array_map(fn($c) => $c->id, $contextlist->get_contexts());
        $expected = \context_module::instance($this->cm->cmid)->id;

        $this->assertContains($expected, $contextids);
    }

    /**
     * Test that the receiver (invite only) context is also returned.
     */
    public function test_get_contexts_for_userid_receiver(): void {
        $contextlist = provider::get_contexts_for_userid($this->receiver->id);

        $contextids = array_map(fn($c) => $c->id, $contextlist->get_contexts());
        $expected = \context_module::instance($this->cm->cmid)->id;

        $this->assertContains($expected, $contextids);
    }

    /**
     * Test that a user with no data returns an empty contextlist.
     */
    public function test_get_contexts_for_userid_no_data(): void {
        $stranger = $this->getDataGenerator()->create_user();
        $contextlist = provider::get_contexts_for_userid($stranger->id);

        $this->assertCount(0, $contextlist->get_contexts());
    }

    /**
     * Test that both creator and receiver are listed in the context.
     */
    public function test_get_users_in_context(): void {
        $context = \context_module::instance($this->cm->cmid);
        $userlist = new userlist($context, 'mod_playergroup');
        provider::get_users_in_context($userlist);

        $userids = array_map('intval', $userlist->get_userids());
        $this->assertContains((int) $this->creator->id, $userids);
        $this->assertContains((int) $this->receiver->id, $userids);
    }

    /**
     * Test that export_user_data writes data for the creator.
     */
    public function test_export_user_data_creator(): void {
        $context = \context_module::instance($this->cm->cmid);

        $approvedlist = new approved_contextlist($this->creator, 'mod_playergroup', [$context->id]);
        provider::export_user_data($approvedlist);

        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
    }

    /**
     * Test that export_user_data writes data for the receiver (invite only).
     */
    public function test_export_user_data_receiver(): void {
        $context = \context_module::instance($this->cm->cmid);

        $approvedlist = new approved_contextlist($this->receiver, 'mod_playergroup', [$context->id]);
        provider::export_user_data($approvedlist);

        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
    }

    /**
     * Test that deleting all data in context removes meta and invites.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $context = \context_module::instance($this->cm->cmid);
        provider::delete_data_for_all_users_in_context($context);

        $this->assertFalse($DB->record_exists('playergroup_meta', ['playergroupid' => $this->cm->id]));
        $this->assertFalse($DB->record_exists('playergroup_invites', ['playergroupid' => $this->cm->id]));
    }

    /**
     * Test that deleting data for a specific user removes only their rows.
     */
    public function test_delete_data_for_user_removes_creator_data(): void {
        global $DB;

        $context = \context_module::instance($this->cm->cmid);
        $approvedlist = new approved_contextlist($this->creator, 'mod_playergroup', [$context->id]);
        provider::delete_data_for_user($approvedlist);

        $this->assertFalse($DB->record_exists('playergroup_meta', [
            'playergroupid' => $this->cm->id,
            'creatorid'     => $this->creator->id,
        ]));
        $this->assertFalse($DB->record_exists('playergroup_invites', [
            'playergroupid' => $this->cm->id,
            'senderid'      => $this->creator->id,
        ]));
    }

    /**
     * Test that deleting a user's data leaves other users' data intact.
     */
    public function test_delete_data_for_user_leaves_other_users(): void {
        global $DB;

        // Delete only the receiver's data.
        $context = \context_module::instance($this->cm->cmid);
        $approvedlist = new approved_contextlist($this->receiver, 'mod_playergroup', [$context->id]);
        provider::delete_data_for_user($approvedlist);

        // Receiver's invite row should be gone.
        $this->assertFalse($DB->record_exists('playergroup_invites', [
            'playergroupid' => $this->cm->id,
            'receiverid'    => $this->receiver->id,
        ]));

        // Creator's meta row should still exist.
        $this->assertTrue($DB->record_exists('playergroup_meta', [
            'playergroupid' => $this->cm->id,
            'creatorid'     => $this->creator->id,
        ]));
    }

    /**
     * Test bulk user deletion within a context.
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $context = \context_module::instance($this->cm->cmid);
        $userlist = new approved_userlist($context, 'mod_playergroup', [
            $this->creator->id,
            $this->receiver->id,
        ]);
        provider::delete_data_for_users($userlist);

        $this->assertFalse($DB->record_exists('playergroup_meta', ['playergroupid' => $this->cm->id]));
        $this->assertFalse($DB->record_exists('playergroup_invites', ['playergroupid' => $this->cm->id]));
    }
}
