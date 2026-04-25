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

// NOTE: no MOODLE_INTERNAL check here — Behat loads this file before config.php.

/**
 * Step definitions for mod_playergroup Behat tests.
 *
 * @package    mod_playergroup
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Step definitions and page-name resolver for mod_playergroup.
 *
 * Supported page types for "I am on the '[name]' 'mod_playergroup > [type]' page":
 *   view   — The student/teacher activity view (view.php?id=<cmid>).
 */
class behat_mod_playergroup extends behat_base {
    /**
     * Resolves named page types to Moodle URLs.
     *
     * Supported identifiers:
     * | pagetype | identifier meaning   | URL                           |
     * | view     | Activity name        | /mod/playergroup/view.php?id= |
     *
     * @param string $type     Page type (e.g. 'view').
     * @param string $identifier Activity name as it appears in the database.
     * @return moodle_url
     * @throws Exception When the page type is unknown.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        switch (strtolower($type)) {
            case 'view':
                return new moodle_url(
                    '/mod/playergroup/view.php',
                    ['id' => $this->get_cm_by_playergroup_name($identifier)->id]
                );

            default:
                throw new Exception('Unrecognised mod_playergroup page type "' . $type . '".');
        }
    }

    /**
     * Returns the course-module row for a playergroup activity identified by name.
     *
     * @param string $name Activity name.
     * @return stdClass Course-module record.
     */
    protected function get_cm_by_playergroup_name(string $name): stdClass {
        global $DB;

        $sql = 'SELECT cm.*
                  FROM {course_modules} cm
                  JOIN {playergroup} pg ON cm.instance = pg.id
                  JOIN {modules} m      ON cm.module   = m.id
                 WHERE pg.name = :name
                   AND m.name  = :modname';

        return $DB->get_record_sql($sql, ['name' => $name, 'modname' => 'playergroup'], MUST_EXIST);
    }
}
