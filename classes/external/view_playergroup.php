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
 * External function to reject a group invitation.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;

/**
 * Records that the activity was viewed from the mobile app.
 *
 * The page does this in view.php; the app never loads that page, so without this the "view" completion rule
 * could not be met by someone who only uses the app, and the visit would not reach the logs.
 */
class view_playergroup extends external_api {
    /**
     * Defines the parameters the call must send.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    /**
     * Marks the activity as viewed and logs it, as view.php does.
     *
     * @param int $cmid Course module ID.
     * @return array Status and warnings.
     * @throws \moodle_exception
     */
    public static function execute(int $cmid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        $cm = get_coursemodule_from_id('playergroup', $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/playergroup:view', $context);

        $playergroup = $DB->get_record('playergroup', ['id' => $cm->instance], '*', MUST_EXIST);
        $course = get_course($cm->course);

        $completion = new \completion_info($course);
        $completion->set_module_viewed($cm);

        $event = \mod_playergroup\event\course_module_viewed::create([
            'objectid' => $playergroup->id,
            'context'  => $context,
        ]);
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('playergroup', $playergroup);
        $event->trigger();

        return ['status' => true, 'warnings' => []];
    }

    /**
     * Defines the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status'   => new external_value(PARAM_BOOL, 'True when the view was recorded'),
            'warnings' => new external_warnings(),
        ]);
    }
}
