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
 * Mobile app addon definition for PlayerGroup.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'mod_playergroup' => [
        'handlers' => [
            'playergroup' => [
                'displaydata' => [
                    'title' => 'pluginname',
                    'icon'  => $CFG->wwwroot . '/mod/playergroup/pix/icon.svg',
                    'class' => '',
                ],
                'delegate'         => 'CoreCourseModuleDelegate',
                'method'           => 'mobile_course_view',
                'init'             => 'mobile_init',
                'offlinefunctions' => [],
                'styles'           => [
                    'url'     => $CFG->wwwroot . '/mod/playergroup/styles_app.css',
                    'version' => '1',
                ],
            ],
        ],
        'lang' => [
            ['pluginname', 'playergroup'],
            ['description', 'playergroup'],
            ['editgroup', 'playergroup'],
            ['groupbadge', 'playergroup'],
            ['groupname', 'playergroup'],
            ['invitepending', 'playergroup'],
            ['leavegroup', 'playergroup'],
            ['mobile_back', 'playergroup'],
            ['mobile_cancel', 'playergroup'],
            ['mobile_creategroup', 'playergroup'],
            ['mobile_decline', 'playergroup'],
            ['mobile_editgroup', 'playergroup'],
            ['mobile_enterpassword', 'playergroup'],
            ['mobile_error', 'playergroup'],
            ['mobile_groupname_required', 'playergroup'],
            ['mobile_inviteaccept', 'playergroup'],
            ['mobile_invitedecline', 'playergroup'],
            ['mobile_inviteusers', 'playergroup'],
            ['mobile_joinopen', 'playergroup'],
            ['mobile_joinprotected', 'playergroup'],
            ['mobile_leaveconfirm', 'playergroup'],
            ['mobile_nogroups', 'playergroup'],
            ['mobile_noinvites', 'playergroup'],
            ['mobile_passwordkeep', 'playergroup'],
            ['mobile_privacyclosed', 'playergroup'],
            ['mobile_privacyopen', 'playergroup'],
            ['mobile_privacyprotected', 'playergroup'],
            ['mobile_save', 'playergroup'],
            ['privacy', 'playergroup'],
            ['receivedinvites', 'playergroup'],
            ['sendinvite', 'playergroup'],
        ],
    ],
];
