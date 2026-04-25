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
 * The task that provides all the steps to perform a complete backup of mod_playergroup.
 *
 * @package    mod_playergroup
 * @category   backup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/playergroup/backup/moodle2/backup_playergroup_stepslib.php');

/**
 * Provides all the settings and steps to perform one complete backup of mod_playergroup.
 */
class backup_playergroup_activity_task extends backup_activity_task {
    /**
     * Defines particular settings for the plugin.
     */
    protected function define_my_settings(): void {
        return;
    }

    /**
     * Defines particular steps for the backup process.
     */
    protected function define_my_steps(): void {
        $this->add_step(new backup_playergroup_activity_structure_step('playergroup_structure', 'playergroup.xml'));
    }

    /**
     * Encodes URLs to make them transportable between Moodle installations.
     *
     * @param string $content Content to encode.
     * @return string Encoded content.
     */
    public static function encode_content_links($content): string {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        $search = '/(' . $base . '\/mod\/playergroup\/index\.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@PLAYERGROUPINDEX*$2@$', $content);

        $search = '/(' . $base . '\/mod\/playergroup\/view\.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@PLAYERGROUPVIEWBYID*$2@$', $content);

        return $content;
    }
}
