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
 * External web services for the PlayerGroup module.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_playergroup_create_group' => [
        'classname'   => 'mod_playergroup\external\create_group',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Creates a new group and assigns the creator as the first member.',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'mod_playergroup_join_group' => [
        'classname'   => 'mod_playergroup\external\join_group',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Adds the current student to an existing group.',
        'type'        => 'write',
        'ajax'        => true,
    ],
    'mod_playergroup_leave_group' => [
        'classname'   => 'mod_playergroup\external\leave_group',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Removes the current student from their group.',
        'type'        => 'write',
        'ajax'        => true,
    ],
];
