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
 * Renderer for the PlayerGroup module.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\output;

use plugin_renderer_base;

/**
 * Main renderer class for PlayerGroup.
 */
class renderer extends plugin_renderer_base {
    /**
     * Renders the student view (The Tavern).
     *
     * @param \stdClass $templatedata Data to pass to the mustache template.
     * @return string HTML rendered output.
     */
    public function render_student_view(\stdClass $templatedata): string {
        return $this->render_from_template('mod_playergroup/view_student', $templatedata);
    }

    /**
     * Renders the teacher activity log report.
     *
     * @param \stdClass $templatedata Data to pass to the mustache template.
     * @return string HTML rendered output.
     */
    public function render_activity_report(\stdClass $templatedata): string {
        return $this->render_from_template('mod_playergroup/view_report', $templatedata);
    }
}
