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

namespace mod_playergroup\event;

/**
 * Event fired when a student accepts a group invitation.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class invite_accepted extends \core\event\base {
    /**
     * Initialise the event data.
     */
    protected function init(): void {
        $this->data['crud']        = 'u';
        $this->data['edulevel']    = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'playergroup_invites';
    }

    /**
     * Return localised name of the event.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_invite_accepted', 'mod_playergroup');
    }

    /**
     * Return non-localised description of the event.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '$this->userid' accepted the invitation with id '$this->objectid' " .
               "in the course module with id '$this->contextinstanceid'.";
    }

    /**
     * Return URL to the activity.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/playergroup/view.php', ['id' => $this->contextinstanceid]);
    }
}
