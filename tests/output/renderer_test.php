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
 * Unit tests for the PlayerGroup renderer.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\output;

use advanced_testcase;

/**
 * Tests for \mod_playergroup\output\renderer.
 *
 * @covers \mod_playergroup\output\renderer
 */
final class renderer_test extends advanced_testcase {
    /** @var renderer The plugin renderer under test. */
    private renderer $renderer;

    /**
     * Set up fixtures for each test.
     */
    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest();

        /** @var renderer $renderer */
        $renderer = $PAGE->get_renderer('mod_playergroup');
        $this->renderer = $renderer;
    }

    /**
     * Test that the student view renders the empty-groups message when there are no groups.
     */
    public function test_render_student_view_shows_empty_state(): void {
        $templatedata = (object) [
            'cmid'               => 1,
            'hasgroup'           => false,
            'activityopen'       => true,
            'caninvite'          => false,
            'hasinviteableusers' => false,
            'inviteableusers'    => [],
            'hasinvites'         => false,
            'receivedinvites'    => [],
            'groups'             => [],
            'isteacher'          => false,
            'reporturl'          => '',
            'groupsreporturl'    => '',
        ];

        $html = $this->renderer->render_student_view($templatedata);

        $this->assertStringContainsString(get_string('nogroups', 'mod_playergroup'), $html);
    }

    /**
     * Test that the student view renders a group card's name when groups are present.
     */
    public function test_render_student_view_shows_group_card(): void {
        $templatedata = (object) [
            'cmid'               => 1,
            'hasgroup'           => false,
            'activityopen'       => true,
            'caninvite'          => false,
            'hasinviteableusers' => false,
            'inviteableusers'    => [],
            'hasinvites'         => false,
            'receivedinvites'    => [],
            'isteacher'          => false,
            'reporturl'          => '',
            'groupsreporturl'    => '',
            'groups'             => [[
                'groupid'             => 42,
                'name'                => 'Dragões do Norte',
                'rawname'             => 'Dragões do Norte',
                'description'         => '',
                'rawdescription'      => '',
                'badge'               => '🐉',
                'membercount'         => 1,
                'maxmembers'          => 5,
                'membersjson'         => '[]',
                'privacy'             => 0,
                'isprivacyopen'       => true,
                'isprivacyprotected'  => false,
                'isprivacyclosed'     => false,
                'isfull'              => false,
                'ismygroup'           => false,
                'leaderbadge'         => '',
                'joinarialabel'       => '',
                'editarialabel'       => '',
                'leavearialabel'      => '',
                'viewmembersarialabel' => '',
                'canjoin'             => true,
                'caninvite'           => false,
                'canedit'             => false,
                'canleave'            => false,
            ]],
        ];

        $html = $this->renderer->render_student_view($templatedata);

        $this->assertStringContainsString('Dragões do Norte', $html);
    }

    /**
     * Test that the activity log report renders the empty-log message when there are no rows.
     */
    public function test_render_activity_report_shows_empty_state(): void {
        $reportdata = (object) [
            'rows'      => [],
            'hasrows'   => false,
            'backurl'   => '',
            'exporturl' => '',
        ];

        $html = $this->renderer->render_activity_report($reportdata);

        $this->assertStringContainsString(get_string('activitylog_empty', 'mod_playergroup'), $html);
    }

    /**
     * Test that the activity log report renders a logged event row.
     */
    public function test_render_activity_report_shows_rows(): void {
        $reportdata = (object) [
            'rows' => [[
                'date'     => '24/04/2026 14:32',
                'username' => 'Ana Silva',
                'action'   => 'Group created',
            ]],
            'hasrows'   => true,
            'backurl'   => '',
            'exporturl' => '',
        ];

        $html = $this->renderer->render_activity_report($reportdata);

        $this->assertStringContainsString('Ana Silva', $html);
        $this->assertStringContainsString('Group created', $html);
    }

    /**
     * Test that the groups-and-members report renders the empty-state message.
     */
    public function test_render_groups_report_shows_empty_state(): void {
        $reportdata = (object) [
            'rows'      => [],
            'hasrows'   => false,
            'backurl'   => '',
            'exporturl' => '',
        ];

        $html = $this->renderer->render_groups_report($reportdata);

        $this->assertStringContainsString(get_string('groupsreport_empty', 'mod_playergroup'), $html);
    }

    /**
     * Test that the groups-and-members report renders a member row.
     */
    public function test_render_groups_report_shows_rows(): void {
        $reportdata = (object) [
            'rows' => [[
                'groupname'  => 'Dragões do Norte',
                'privacy'    => 'Open',
                'role'       => 'Leader',
                'membername' => 'Ana Silva',
            ]],
            'hasrows'   => true,
            'backurl'   => '',
            'exporturl' => '',
        ];

        $html = $this->renderer->render_groups_report($reportdata);

        $this->assertStringContainsString('Dragões do Norte', $html);
        $this->assertStringContainsString('Ana Silva', $html);
    }
}
