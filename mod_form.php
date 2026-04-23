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
 * The main module configuration form.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module instance settings form.
 */
class mod_playergroup_mod_form extends moodleform_mod {
    /**
     * Defines the form elements.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name', 'mod_playergroup'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // Team rules and game mechanics.
        $mform->addElement('header', 'gamerules', get_string('gamerules', 'mod_playergroup'));

        $mform->addElement('text', 'minmembers', get_string('minmembers', 'mod_playergroup'), ['size' => '5']);
        $mform->setType('minmembers', PARAM_INT);
        $mform->setDefault('minmembers', 2);

        $mform->addElement('text', 'maxmembers', get_string('maxmembers', 'mod_playergroup'), ['size' => '5']);
        $mform->setType('maxmembers', PARAM_INT);
        $mform->setDefault('maxmembers', 5);

        $mform->addElement('advcheckbox', 'canleave', get_string('canleave', 'mod_playergroup'));
        $mform->setDefault('canleave', 1);

        $mform->addElement('advcheckbox', 'deletegroups', get_string('deletegroups', 'mod_playergroup'));
        $mform->setDefault('deletegroups', 0);
        $mform->addElement(
            'static',
            'deletegroupswarning',
            '',
            get_string('deletegroupswarning', 'mod_playergroup')
        );

        // Availability window.
        $mform->addElement('header', 'availability', get_string('availability', 'mod_playergroup'));
        $mform->addElement(
            'date_time_selector',
            'timeopen',
            get_string('timeopen', 'mod_playergroup'),
            ['optional' => true]
        );
        $mform->addElement(
            'date_time_selector',
            'timeclose',
            get_string('timeclose', 'mod_playergroup'),
            ['optional' => true]
        );

        // Standard grading elements (handles the grade field and gradebook integration).
        $this->standard_grading_coursemodule_elements();

        // Standard course module elements (completion, groups, etc).
        $this->standard_coursemodule_elements();

        // Remove native group/grouping selectors to prevent conflicts.
        // Grouping creation is handled automatically in the background.
        if ($mform->elementExists('groupmode')) {
            $mform->removeElement('groupmode');
        }
        if ($mform->elementExists('groupingid')) {
            $mform->removeElement('groupingid');
        }

        $this->add_action_buttons();
    }

    /**
     * Add custom completion rules.
     *
     * @return array Array of string IDs of added items, empty array if none.
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;
        $group = [];

        $group[] =& $mform->createElement(
            'checkbox',
            'completionjoingroup',
            '',
            get_string('completionjoingroup', 'mod_playergroup')
        );

        $mform->addGroup(
            $group,
            'completionjoingroupgroup',
            get_string('completionjoingroupgroup', 'mod_playergroup'),
            [' '],
            false
        );

        return ['completionjoingroupgroup'];
    }

    /**
     * Called during validation to see whether some module-specific completion rules are selected.
     *
     * @param array $data Input data (not yet validated).
     * @return bool True if one or more rules is enabled, false if none are.
     */
    public function completion_rule_enabled($data): bool {
        return !empty($data['completionjoingroup']);
    }
}
