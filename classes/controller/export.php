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

namespace mod_playergroup\controller;

/**
 * Controller for exporting the teacher activity log report.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export {
    /**
     * Executes the export process using Moodle core dataformat.
     *
     * @param int $contextid The activity module context ID.
     * @param string $format The export format (csv, excel, etc).
     * @param string $courseshortname The course shortname for the filename.
     * @return void
     */
    public function execute(int $contextid, string $format, string $courseshortname): void {
        $rows = \mod_playergroup\local\activity_log::get_rows($contextid);
        if ($rows === null) {
            throw new \moodle_exception('activitylog_noreader', 'mod_playergroup');
        }

        $exportdata = array_map(fn(array $row): array => [$row['date'], $row['username'], $row['action']], $rows);

        // Define localized headers.
        $columns = [
            get_string('report_date', 'mod_playergroup'),
            get_string('report_user', 'mod_playergroup'),
            get_string('report_action', 'mod_playergroup'),
        ];

        $filename = 'playergroup_activitylog_' . \mod_playergroup\local\text::plain($courseshortname) . '_' . date('Ymd');

        // Trigger download using the Moodle native API.
        \core\dataformat::download_data($filename, $format, $columns, $exportdata);
    }
}
