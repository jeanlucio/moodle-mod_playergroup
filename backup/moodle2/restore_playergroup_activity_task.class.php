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
 * The task that provides a complete restore of mod_playergroup is defined here.
 *
 * @package    mod_playergroup
 * @category   backup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/playergroup/backup/moodle2/restore_playergroup_stepslib.php');

/**
 * Restore task for mod_playergroup.
 */
class restore_playergroup_activity_task extends restore_activity_task {
    /**
     * Defines particular settings that this activity can have.
     */
    protected function define_my_settings(): void {
        return;
    }

    /**
     * Defines the steps that will be executed during restore.
     */
    protected function define_my_steps(): void {
        $this->add_step(
            new restore_playergroup_activity_structure_step('playergroup_structure', 'playergroup.xml')
        );
    }

    /**
     * Defines the content areas in the activity that must be decoded by the link decoder.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents(): array {
        return [
            new restore_decode_content('playergroup', ['intro'], 'playergroup'),
        ];
    }

    /**
     * Defines the decoding rules for links belonging to the activity.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules(): array {
        return [
            new restore_decode_rule('PLAYERGROUPVIEWBYID', '/mod/playergroup/view.php?id=$1', 'course_module'),
            new restore_decode_rule('PLAYERGROUPINDEX', '/mod/playergroup/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Defines the restore log rules that will be applied by the log processor.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules(): array {
        return [];
    }
}
