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
    /** Longest name the activity and a grouping can have: both columns hold 255 characters. */
    private const MAXNAME = 255;

    /** Largest group size the form accepts; the database columns hold three digits. */
    private const MAXMEMBERS = 999;

    /**
     * Defines the form elements.
     *
     * @return void
     */
    public function definition(): void {
        global $DB;

        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name', 'mod_playergroup'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // Grouping configuration.
        $mform->addElement('header', 'groupinghdr', get_string('groupingmode', 'mod_playergroup'));

        $groupingoptions = [
            'new'      => get_string('groupingmode_new', 'mod_playergroup'),
            'existing' => get_string('groupingmode_existing', 'mod_playergroup'),
            'none'     => get_string('groupingmode_none', 'mod_playergroup'),
        ];
        $mform->addElement('select', 'groupingmode', get_string('groupingmode', 'mod_playergroup'), $groupingoptions);
        $mform->setDefault('groupingmode', 'new');

        $mform->addElement('text', 'groupingname', get_string('groupingname', 'mod_playergroup'), ['size' => '64']);
        $mform->setType('groupingname', PARAM_TEXT);
        $mform->addRule('groupingname', get_string('maximumchars', '', self::MAXNAME), 'maxlength', self::MAXNAME, 'client');
        $mform->hideIf('groupingname', 'groupingmode', 'neq', 'new');

        // Build the list of existing groupings for this course.
        $existinggroupings = [0 => get_string('choosedots')];
        $courseid = $this->current->course ?? 0;
        if ($courseid) {
            $coursecontext = \context_course::instance($courseid);
            $groupings = $DB->get_records('groupings', ['courseid' => $courseid], 'name ASC', 'id, name');
            foreach ($groupings as $g) {
                // The select prints the text of an option as it is, so it must be HTML that is already safe, as the
                // core does with this very list. text::plain() decodes entities and is only for destinations that
                // escape on their own.
                $existinggroupings[$g->id] = format_string($g->name, true, ['context' => $coursecontext]);
            }
        }
        $mform->addElement(
            'select',
            'existinggroupingid',
            get_string('groupingmode_existing', 'mod_playergroup'),
            $existinggroupings
        );
        $mform->hideIf('existinggroupingid', 'groupingmode', 'neq', 'existing');

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

        $mform->addElement('advcheckbox', 'deleteemptygroups', get_string('deleteemptygroups', 'mod_playergroup'));
        $mform->setDefault('deleteemptygroups', 1);
        $mform->addHelpButton('deleteemptygroups', 'deleteemptygroups', 'mod_playergroup');
        $mform->hideIf('deleteemptygroups', 'canleave', 'eq', 0);

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
        // Default to "None" — grading is optional for this activity type.
        $this->standard_grading_coursemodule_elements();
        $mform->setDefault('grade', 0);
        $mform->addElement(
            'static',
            'gradeautonotice',
            '',
            \html_writer::div(
                get_string('gradeautonotice', 'mod_playergroup'),
                'alert alert-info mb-0'
            )
        );
        $mform->hideIf('gradeautonotice', 'grade[modgrade_type]', 'eq', 'none');

        // Standard course module elements (completion, groups, etc).
        $this->standard_coursemodule_elements();

        // Remove native group/grouping selectors to prevent conflicts.
        // Grouping creation is handled via the groupinghdr section above.
        if ($mform->elementExists('groupmode')) {
            $mform->removeElement('groupmode');
        }
        if ($mform->elementExists('groupingid')) {
            $mform->removeElement('groupingid');
        }

        $this->add_action_buttons();
    }

    /**
     * Validates the names, the member limits, the availability window and the grade type.
     *
     * Without these checks a teacher can save settings that silently disable the activity: a
     * maximum of 0 marks every group as full, and a closing date before the opening date means
     * the activity is never open. A scale is rejected because the gradebook item is only
     * created for points (see playergroup_grade_item_update()).
     *
     * @param array $data Submitted form data.
     * @param array $files Submitted files.
     * @return array Field name => error message.
     */
    public function validation($data, $files): array {
        global $CFG;
        require_once($CFG->libdir . '/grouplib.php');

        $errors = parent::validation($data, $files);

        // The browser limits the length of the name, but a request does not have to come from it.
        if (\core_text::strlen(trim($data['name'] ?? '')) > self::MAXNAME) {
            $errors['name'] = get_string('maximumchars', '', self::MAXNAME);
        }

        // A grouping typed here is created with that name: it has to fit the column and be free in the course.
        if (($data['groupingmode'] ?? '') === 'new') {
            $groupingname = trim($data['groupingname'] ?? '');
            $courseid = (int) ($data['course'] ?? 0);
            if (\core_text::strlen($groupingname) > self::MAXNAME) {
                $errors['groupingname'] = get_string('maximumchars', '', self::MAXNAME);
            } else if ($groupingname !== '' && groups_get_grouping_by_name($courseid, $groupingname)) {
                $errors['groupingname'] = get_string('groupingnameexists', 'group', $groupingname);
            }
        }

        $minmembers = (int) ($data['minmembers'] ?? 0);
        $maxmembers = (int) ($data['maxmembers'] ?? 0);
        if ($minmembers < 1) {
            $errors['minmembers'] = get_string('errminmembersmin', 'mod_playergroup');
        }
        if ($maxmembers < 1) {
            $errors['maxmembers'] = get_string('errmaxmembersmin', 'mod_playergroup');
        }
        // The columns hold three digits; a larger number would only fail when the activity is saved.
        $limit = self::MAXMEMBERS;
        if ($minmembers > $limit) {
            $errors['minmembers'] = get_string('errmembersmax', 'mod_playergroup', $limit);
        }
        if ($maxmembers > $limit) {
            $errors['maxmembers'] = get_string('errmembersmax', 'mod_playergroup', $limit);
        }
        if (empty($errors['minmembers']) && empty($errors['maxmembers']) && $minmembers > $maxmembers) {
            $errors['minmembers'] = get_string('errminovermax', 'mod_playergroup');
        }

        $timeopen = (int) ($data['timeopen'] ?? 0);
        $timeclose = (int) ($data['timeclose'] ?? 0);
        if ($timeopen > 0 && $timeclose > 0 && $timeclose <= $timeopen) {
            $errors['timeclose'] = get_string('errtimeclosebeforeopen', 'mod_playergroup');
        }

        // The modgrade element stores a scale as the negative scale ID.
        if ((int) ($data['grade'] ?? 0) < 0) {
            $errors['grade'] = get_string('errnogradescale', 'mod_playergroup');
        }

        return $errors;
    }

    /**
     * Pre-processes saved data to populate grouping mode fields when editing.
     *
     * @param array $defaultvalues Default values from the database record.
     * @return void
     */
    public function data_preprocessing(&$defaultvalues): void {
        parent::data_preprocessing($defaultvalues);

        // When editing an existing instance, map groupingid back to the form fields.
        if (!empty($defaultvalues['id'])) {
            if (!empty($defaultvalues['groupingid'])) {
                $defaultvalues['groupingmode']        = 'existing';
                $defaultvalues['existinggroupingid']  = $defaultvalues['groupingid'];
            } else {
                $defaultvalues['groupingmode'] = 'none';
            }
        }
    }

    /**
     * Add custom completion rules.
     *
     * The element names carry the form's suffix. The core shows this form on the "Default activity
     * completion" page with the suffix "_playergroup" and drops any custom rule whose name lacks it,
     * which would also erase the rule from the defaults of every new activity.
     *
     * @return array Array of string IDs of added items, empty array if none.
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;
        $suffix = $this->get_suffix();
        $group = [];

        $group[] =& $mform->createElement(
            'checkbox',
            'completionjoingroup' . $suffix,
            '',
            get_string('completionjoingroup', 'mod_playergroup')
        );

        $mform->addGroup(
            $group,
            'completionjoingroupgroup' . $suffix,
            get_string('completionjoingroupgroup', 'mod_playergroup'),
            [' '],
            false
        );

        return ['completionjoingroupgroup' . $suffix];
    }

    /**
     * Stores an unticked completion rule as 0.
     *
     * A browser does not send an unticked checkbox, so the rule is missing from the submitted data and
     * the update would leave the old 1 in the database. The rule only applies with automatic tracking,
     * so it is also cleared when the teacher switches to another tracking mode.
     *
     * @param \stdClass $data Submitted data.
     * @return void
     */
    public function data_postprocessing($data): void {
        parent::data_postprocessing($data);

        if (empty($data->completionunlocked)) {
            return;
        }

        $name = 'completionjoingroup' . $this->get_suffix();
        $automatic = !empty($data->completion) && (int) $data->completion === COMPLETION_TRACKING_AUTOMATIC;
        if (!$automatic || empty($data->{$name})) {
            $data->{$name} = 0;
        }
    }

    /**
     * Called during validation to see whether some module-specific completion rules are selected.
     *
     * @param array $data Input data (not yet validated).
     * @return bool True if one or more rules is enabled, false if none are.
     */
    public function completion_rule_enabled($data): bool {
        return !empty($data['completionjoingroup' . $this->get_suffix()]);
    }
}
