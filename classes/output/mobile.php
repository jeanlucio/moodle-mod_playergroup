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
 * Mobile app renderer for PlayerGroup.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playergroup\output;

use context_module;
use mod_playergroup\external\get_activity_data;

/**
 * Provides data and templates for the Moodle mobile app remote addon.
 */
class mobile {
    /**
     * Returns the JavaScript providers needed by the mobile app.
     *
     * Called once per session by the mobile app to register providers and link handlers.
     *
     * @param array $args Arguments passed by the mobile app (not used in init).
     * @return array Templates and JavaScript to register.
     */
    public static function mobile_init($args): array {
        global $CFG;

        return [
            'templates'  => [],
            'javascript' => file_get_contents($CFG->dirroot . '/mod/playergroup/mobile/js/init.js'),
        ];
    }

    /**
     * Returns the rendered template and data for the main activity view.
     *
     * @param array $args Arguments passed by the mobile app (cmid, courseid, appversioncode).
     * @return array Templates, JavaScript, and otherdata for the mobile app.
     */
    public static function mobile_course_view($args): array {
        global $OUTPUT, $CFG;

        $args = (object) $args;
        $cmid = (int) $args->cmid;

        $cm = get_coursemodule_from_id('playergroup', $cmid, 0, false, MUST_EXIST);

        require_login($args->courseid, false, $cm, true, true);
        $context = context_module::instance($cmid);
        require_capability('mod/playergroup:view', $context);

        $data = get_activity_data::execute($cmid);

        $templatecontext = (object) [
            'cmid'                => $cmid,
            'activityopen'        => $data['activityopen'],
            'availabilitymessage' => $data['availabilitymessage'],
            'hasgroup'            => $data['hasgroup'],
            'isteacher'           => $data['isteacher'],
            'mygroupid'           => $data['mygroupid'],
        ];

        $html = $OUTPUT->render_from_template('mod_playergroup/mobile_view_page', $templatecontext);

        $js = file_get_contents($CFG->dirroot . '/mod/playergroup/mobile/js/courseview.js');

        return [
            'templates' => [
                [
                    'id'   => 'main',
                    'html' => $html,
                ],
            ],
            'javascript' => $js,
            'otherdata'  => [
                'cmid'                => $cmid,
                'activityopen'        => (int) $data['activityopen'],
                'availabilitymessage' => $data['availabilitymessage'],
                'hasgroup'            => (int) $data['hasgroup'],
                'mygroupid'           => (int) $data['mygroupid'],
                'isteacher'           => (int) $data['isteacher'],
                'maxmembers'          => (int) $data['maxmembers'],
                'canleave'            => (int) $data['canleave'],
                'groups'              => json_encode($data['groups']),
                'receivedinvites'     => json_encode($data['receivedinvites']),
                'inviteableusers'     => json_encode($data['inviteableusers']),
            ],
        ];
    }
}
