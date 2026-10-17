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
                'canviewmembers'      => true,
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
     * Test that the group card's "view members" button carries the HTML-formatted name, not the
     * raw one, in its data-groupname attribute.
     *
     * amd/src/main.js reads data-groupname and passes it straight into Str.get_string() as
     * the $a for the members-modal title, which core/modal renders as HTML rather than text.
     * The raw (unescaped) name is safe for data-name, used only to prefill a plain-text
     * <input value="...">, but must never be the source for something rendered as HTML.
     */
    public function test_render_student_view_group_card_data_groupname_uses_formatted_name(): void {
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
                'name'                => 'Plain Name',
                'namehtml'            => 'Formatted Safe Name',
                'rawname'             => 'Raw <b>Unsafe</b> Name',
                'description'         => '',
                'rawdescription'      => '',
                'badge'               => '🐉',
                'membercount'         => 1,
                'maxmembers'          => 5,
                'membersjson'         => '[]',
                'canviewmembers'      => true,
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

        $this->assertStringContainsString('data-groupname="Formatted Safe Name"', $html);
        $this->assertStringNotContainsString('data-groupname="Raw', $html);
    }

    /**
     * Test that a group whose members the user may not see renders only the member count: no
     * "view members" button and no data-members attribute that could carry names.
     */
    public function test_render_student_view_group_card_without_member_names(): void {
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
                'name'                => 'Hidden Roster',
                'rawname'             => 'Hidden Roster',
                'description'         => '',
                'rawdescription'      => '',
                'badge'               => '🐉',
                'membercount'         => 3,
                'maxmembers'          => 5,
                'membersjson'         => '[]',
                'canviewmembers'      => false,
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

        $this->assertStringContainsString('Hidden Roster', $html);
        $this->assertStringContainsString('3 / 5', $html);
        $this->assertStringNotContainsString('data-action="viewmembers"', $html);
        $this->assertStringNotContainsString('data-members', $html);
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

    /**
     * Test that the join-group password field disables browser autofill.
     *
     * Regression: without an autocomplete hint, the browser silently offered the
     * student's own saved site password in this field.
     */
    public function test_render_from_template_join_password_field_disables_autofill(): void {
        $html = $this->renderer->render_from_template('mod_playergroup/modal_joingroup', []);

        $this->assertStringContainsString('id="pg-join-password"', $html);
        $this->assertStringContainsString('autocomplete="new-password"', $html);
    }

    /**
     * Test that the create-group password field disables browser autofill.
     *
     * Regression: without an autocomplete hint, the browser silently offered the
     * student's own saved site password in this field.
     */
    public function test_render_from_template_create_group_password_field_disables_autofill(): void {
        $html = $this->renderer->render_from_template('mod_playergroup/modal_creategroup', []);

        $this->assertStringContainsString('id="pg-grouppassword"', $html);
        $this->assertStringContainsString('autocomplete="new-password"', $html);
    }

    /**
     * Test that the edit-group password field disables browser autofill.
     *
     * Regression: without an autocomplete hint, the browser silently offered the
     * student's own saved site password in this field.
     */
    public function test_render_from_template_edit_group_password_field_disables_autofill(): void {
        $html = $this->renderer->render_from_template('mod_playergroup/modal_editgroup', [
            'name'               => 'Test',
            'description'        => '',
            'badge'              => '',
            'isprivacyopen'      => false,
            'isprivacyprotected' => true,
            'isprivacyclosed'    => false,
        ]);

        $this->assertStringContainsString('id="pg-edit-grouppassword"', $html);
        $this->assertStringContainsString('autocomplete="new-password"', $html);
    }

    /**
     * Test that the report explains why it is empty when no log store can be queried, rather than
     * showing the normal "no records" message, and offers no export.
     */
    public function test_render_activity_report_explains_missing_log_store(): void {
        $reportdata = (object) [
            'rows'      => [],
            'hasrows'   => false,
            'noreader'  => true,
            'backurl'   => '',
            'exporturl' => 'https://example.invalid/export.php?id=1',
        ];

        $html = $this->renderer->render_activity_report($reportdata);

        $this->assertStringContainsString(get_string('activitylog_noreader', 'mod_playergroup'), $html);
        $this->assertStringNotContainsString(get_string('activitylog_empty', 'mod_playergroup'), $html);
        $this->assertStringNotContainsString('export.php', $html);
    }

    /**
     * Test that the card of the student's own group explains why it does not count yet.
     */
    public function test_render_student_view_group_card_shows_missing_members_message(): void {
        $templatedata = (object) [
            'cmid'               => 1,
            'hasgroup'           => true,
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
                'name'                => 'Small Team',
                'rawname'             => 'Small Team',
                'description'         => '',
                'rawdescription'      => '',
                'badge'               => '🐉',
                'membercount'         => 1,
                'maxmembers'          => 5,
                'membersjson'         => '[]',
                'canviewmembers'      => true,
                'missingmessage'      => get_string('groupneedsmembers', 'mod_playergroup', 2),
                'privacy'             => 0,
                'isprivacyopen'       => true,
                'isprivacyprotected'  => false,
                'isprivacyclosed'     => false,
                'isfull'              => false,
                'ismygroup'           => true,
                'leaderbadge'         => '',
                'joinarialabel'       => '',
                'editarialabel'       => '',
                'leavearialabel'      => '',
                'viewmembersarialabel' => '',
                'canjoin'             => false,
                'caninvite'           => false,
                'canedit'             => false,
                'canleave'            => false,
            ]],
        ];

        $html = $this->renderer->render_student_view($templatedata);

        $this->assertStringContainsString(get_string('groupneedsmembers', 'mod_playergroup', 2), $html);
    }

    /**
     * Test that a group name with an ampersand is escaped once in the card, not twice.
     *
     * The view hands the template plain text; the template escapes it, so "P&D" must reach the page
     * as "P&amp;D" (shown as P&D), never as "P&amp;amp;D" (shown as P&amp;D).
     */
    public function test_render_student_view_group_card_escapes_the_name_once(): void {
        $name = \mod_playergroup\local\text::plain('P&D');
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
                'name'                => $name,
                'namehtml'            => format_string('P&D'),
                'rawname'             => 'P&D',
                'description'         => '',
                'rawdescription'      => '',
                'badge'               => '🐉',
                'membercount'         => 1,
                'maxmembers'          => 5,
                'membersjson'         => '[]',
                'canviewmembers'      => true,
                'privacy'             => 0,
                'isprivacyopen'       => true,
                'isprivacyprotected'  => false,
                'isprivacyclosed'     => false,
                'isfull'              => false,
                'ismygroup'           => false,
                'leaderbadge'         => '',
                'joinarialabel'       => get_string('joingroup_named', 'mod_playergroup', $name),
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

        $this->assertStringContainsString('>P&amp;D</h4>', $html);
        $this->assertStringContainsString('aria-label="Join group P&amp;D"', $html);
        // The attribute holds HTML on purpose: the browser decodes it once, and the members modal
        // renders that string as its title, so it must carry the escaped form.
        $this->assertStringContainsString('data-groupname="P&amp;amp;D"', $html);
    }

    /**
     * Builds the minimum data the student view needs, for a user without a group in an open activity.
     *
     * @param array $overrides Values replacing the defaults.
     * @return \stdClass Template data.
     */
    private function empty_view_data(array $overrides = []): \stdClass {
        return (object) array_merge([
            'cmid'               => 1,
            'hasgroup'           => false,
            'activityopen'       => true,
            'cancreate'          => true,
            'caninvite'          => false,
            'hasinviteableusers' => false,
            'inviteableusers'    => [],
            'hasinvites'         => false,
            'receivedinvites'    => [],
            'isteacher'          => false,
            'reporturl'          => '',
            'groupsreporturl'    => '',
            'groups'             => [],
        ], $overrides);
    }

    /**
     * Test that the Create group button is shown to someone who may create groups.
     */
    public function test_render_student_view_shows_create_button_when_allowed(): void {
        $html = $this->renderer->render_student_view($this->empty_view_data(['cancreate' => true]));

        $this->assertStringContainsString('data-action="creategroup"', $html);
    }

    /**
     * Test that the Create group button is hidden from someone the server would refuse.
     */
    public function test_render_student_view_hides_create_button_when_not_allowed(): void {
        $html = $this->renderer->render_student_view($this->empty_view_data(['cancreate' => false]));

        $this->assertStringNotContainsString('data-action="creategroup"', $html);
    }

    /**
     * Test that the edit modal labels the name field as the name of the group, not of the activity.
     */
    public function test_the_edit_modal_labels_the_field_with_the_group_name(): void {
        global $PAGE;

        $html = $PAGE->get_renderer('core')->render_from_template('mod_playergroup/modal_editgroup', [
            'name' => 'Dragons', 'description' => '', 'badge' => '', 'canclose' => true, 'isprivacyopen' => true,
        ]);

        $label = preg_quote(get_string('groupname', 'mod_playergroup'), '~');
        $this->assertMatchesRegularExpression('~<label for="pg-edit-groupname"[^>]*>\s*' . $label . '\s*</label>~', $html);
        $this->assertStringNotContainsString(get_string('name', 'mod_playergroup'), $html);
    }
}
