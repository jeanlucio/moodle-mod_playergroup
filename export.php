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
 * Data export entry point for the teacher activity log report.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id     = required_param('id', PARAM_INT);
$format = optional_param('format', 'csv', PARAM_ALPHANUMEXT);
$type   = optional_param('type', 'log', PARAM_ALPHA);

$cm = get_coursemodule_from_id('playergroup', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/playergroup:manage', $context);

// Note: require_sesskey() is intentionally omitted here because data export
// is a read-only GET request and does not modify database state.

if ($type === 'groups') {
    $controller = new \mod_playergroup\controller\export_groups();
    $controller->execute((int) $cm->instance, $format, $course->shortname);
} else {
    $controller = new \mod_playergroup\controller\export();
    $controller->execute($context->id, $format, $course->shortname);
}

// The controller only streams the file; this entry point owns ending the request.
exit;
