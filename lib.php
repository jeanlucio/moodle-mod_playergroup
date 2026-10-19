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
 * Core module functions for the PlayerGroup plugin.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Indicates API features that the plugin supports.
 *
 * @param string $feature The feature string.
 * @return mixed True if supported, a purpose string for FEATURE_MOD_PURPOSE/FEATURE_MOD_OTHERPURPOSE, null if not.
 */
function playergroup_supports(string $feature): mixed {
    $features = [
        FEATURE_MOD_INTRO              => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_COMPLETION_HAS_RULES   => true,
        FEATURE_GRADE_HAS_GRADE        => true,
        FEATURE_GRADE_OUTCOMES         => true,
        FEATURE_BACKUP_MOODLE2         => true,
        FEATURE_SHOW_DESCRIPTION       => true,
        FEATURE_MOD_PURPOSE            => MOD_PURPOSE_COLLABORATION,
    ];

    // FEATURE_MOD_OTHERPURPOSE only exists from Moodle 5.x onwards; this plugin also
    // targets Moodle 4.5, where referencing the undefined constant is a fatal error.
    if (defined('FEATURE_MOD_OTHERPURPOSE')) {
        $features[FEATURE_MOD_OTHERPURPOSE] = MOD_PURPOSE_ASSESSMENT;
    }

    return $features[$feature] ?? null;
}

/**
 * Tells Moodle this plugin's icon is branded, disabling the purpose-based recolour filter.
 *
 * Without this, core recolours pix/icon.svg to a flat purpose colour on the course page
 * and activity chooser, discarding the icon's own authored colour.
 *
 * @return bool
 */
function playergroup_is_branded(): bool {
    return true;
}

/**
 * Resolves the grouping ID from the form submission data.
 *
 * Handles three modes: create a new grouping, use an existing one, or none.
 *
 * @param \stdClass $playergroup The submitted form data (contains groupingmode, groupingname, existinggroupingid).
 * @return int The resolved grouping ID (0 when none).
 */
function playergroup_resolve_groupingid(\stdClass $playergroup): int {
    $mode = $playergroup->groupingmode ?? 'none';

    if ($mode === 'new') {
        $customname = trim($playergroup->groupingname ?? '');
        $manager = new \mod_playergroup\instance_manager();
        return $manager->process_activity_grouping($playergroup, $customname);
    }

    if ($mode === 'existing') {
        return (int) ($playergroup->existinggroupingid ?? 0);
    }

    return 0;
}

/**
 * Saves a new instance of the playergroup into the database.
 *
 * @param \stdClass $playergroup The submitted form data.
 * @param ?\mod_playergroup_mod_form $mform The form instance.
 * @return int The new instance ID.
 */
function playergroup_add_instance(\stdClass $playergroup, ?\mod_playergroup_mod_form $mform = null): int {
    global $DB;

    $playergroup->timemodified = time();
    $playergroup->timeopen     = $playergroup->timeopen ?? 0;
    $playergroup->timeclose    = $playergroup->timeclose ?? 0;
    $playergroup->groupingid   = 0;

    // Insert the base record first to get an ID.
    $playergroup->id = $DB->insert_record('playergroup', $playergroup);

    $groupingid = playergroup_resolve_groupingid($playergroup);

    $updateobj = new \stdClass();
    $updateobj->id         = $playergroup->id;
    $updateobj->groupingid = $groupingid;
    $DB->update_record('playergroup', $updateobj);

    playergroup_grade_item_update($playergroup);

    // The core always gives the course module; the events hang from it, so a call without one has none to make.
    if (!empty($playergroup->coursemodule)) {
        $record = $DB->get_record('playergroup', ['id' => $playergroup->id], '*', MUST_EXIST);
        $visible = (bool) $DB->get_field('course_modules', 'visible', ['id' => $playergroup->coursemodule], MUST_EXIST);
        \mod_playergroup\local\calendar::set_events($record, (int) $playergroup->coursemodule, $visible);
    }

    if (!empty($playergroup->completionexpected)) {
        \core_completion\api::update_completion_date_event(
            $playergroup->coursemodule,
            'playergroup',
            $playergroup->id,
            $playergroup->completionexpected
        );
    }

    return $playergroup->id;
}

/**
 * Updates an existing instance of the playergroup.
 *
 * @param \stdClass $playergroup The submitted form data.
 * @param ?\mod_playergroup_mod_form $mform The form instance.
 * @return bool True on success.
 */
function playergroup_update_instance(\stdClass $playergroup, ?\mod_playergroup_mod_form $mform = null): bool {
    global $DB;

    $playergroup->timemodified = time();
    $playergroup->timeopen     = $playergroup->timeopen ?? 0;
    $playergroup->timeclose    = $playergroup->timeclose ?? 0;
    $playergroup->id           = $playergroup->instance;
    $playergroup->groupingid   = playergroup_resolve_groupingid($playergroup);

    $old = $DB->get_record('playergroup', ['id' => $playergroup->id], 'id, minmembers, grade', MUST_EXIST);

    $result = $DB->update_record('playergroup', $playergroup);

    // Clearing the date removes the event as well, so it is always passed on.
    \core_completion\api::update_completion_date_event(
        $playergroup->coursemodule,
        'playergroup',
        $playergroup->id,
        !empty($playergroup->completionexpected) ? $playergroup->completionexpected : null
    );

    playergroup_grade_item_update($playergroup);

    $record = $DB->get_record('playergroup', ['id' => $playergroup->id], '*', MUST_EXIST);

    // The dates may have changed, or been cleared.
    $visible = (bool) $DB->get_field('course_modules', 'visible', ['id' => $playergroup->coursemodule], MUST_EXIST);
    \mod_playergroup\local\calendar::set_events($record, (int) $playergroup->coursemodule, $visible);

    // Lowering the minimum can make existing groups count. Raising it never takes anything away,
    // since a student who already qualified keeps the grade and the completion.
    if ((int) $record->minmembers < (int) $old->minmembers) {
        $cm = get_coursemodule_from_instance('playergroup', $record->id, $record->course, false, MUST_EXIST);
        $lock = \mod_playergroup\local\group_lock::acquire((int) $record->id);
        try {
            $qualified = \mod_playergroup\local\qualification::record_all_groups($record);
        } finally {
            $lock->release();
        }
        \mod_playergroup\local\qualification::award($record, $cm, $qualified);
    }

    // Turning the grade on, or changing its value, must reach the students who already qualified:
    // creating the grade item sends no grades, and only a student qualifying later would be graded.
    if ((float) $record->grade > 0 && (float) $record->grade !== (float) $old->grade) {
        playergroup_update_grades($record);
    }

    return $result;
}

/**
 * Deletes an instance of the playergroup and its associated data.
 *
 * @param int $id The instance ID.
 * @return bool True on success.
 */
function playergroup_delete_instance(int $id): bool {
    global $CFG, $DB;

    require_once($CFG->libdir . '/gradelib.php');

    if (!$playergroup = $DB->get_record('playergroup', ['id' => $id])) {
        return false;
    }

    // Conditionally delete this instance's own groups, and the grouping when it ends up empty, based
    // on the professor's preference. This does not depend on the activity having a grouping, and
    // never touches groups it did not register.
    if (!empty($playergroup->deletegroups)) {
        $manager = new \mod_playergroup\instance_manager();
        $manager->delete_activity_groups((int) $id, (int) $playergroup->groupingid);
    }

    // Remove the grade item from the gradebook.
    grade_update('mod/playergroup', $playergroup->course, 'mod', 'playergroup', $id, 0, null, ['deleted' => 1]);

    // Clean up plugin-specific tables.
    $DB->delete_records('playergroup_invites', ['playergroupid' => $id]);
    $DB->delete_records('playergroup_meta', ['playergroupid' => $id]);
    $DB->delete_records('playergroup_qualified', ['playergroupid' => $id]);

    return $DB->delete_records('playergroup', ['id' => $id]);
}

/**
 * Course reset callback: clears what the options of the core reset page leave behind in the activities.
 *
 * @param \stdClass $data The reset options chosen by the teacher.
 * @return array[] Status rows for the reset report.
 */
function playergroup_reset_userdata(\stdClass $data): array {
    return \mod_playergroup\local\course_reset::reset($data);
}

/**
 * Creates or updates the grade item in the gradebook for a playergroup instance.
 *
 * @param \stdClass $playergroup The playergroup instance record.
 * @param mixed $grades Grade data, null, or 'reset' to clear recorded grades.
 * @return int GRADE_UPDATE_OK or GRADE_UPDATE_FAILED.
 */
function playergroup_grade_item_update(\stdClass $playergroup, mixed $grades = null): int {
    global $CFG;

    require_once($CFG->libdir . '/gradelib.php');

    if (!isset($playergroup->courseid)) {
        $playergroup->courseid = $playergroup->course;
    }

    if (!empty($playergroup->grade) && $playergroup->grade > 0) {
        $params = [
            'itemname'  => $playergroup->name,
            'gradetype' => GRADE_TYPE_VALUE,
            'grademax'  => $playergroup->grade,
            'grademin'  => 0,
        ];
    } else {
        $params = ['gradetype' => GRADE_TYPE_NONE];
    }

    $isreset = $grades === 'reset';
    if ($isreset) {
        $params['reset'] = true;
        $grades = null;
    }

    $result = grade_update(
        'mod/playergroup',
        $playergroup->courseid,
        'mod',
        'playergroup',
        $playergroup->id,
        0,
        $grades,
        $params
    );

    // The core grade_update() function silently ignores a 'gradepass' key in
    // $itemdetails: its own internal allow-list (lib/gradelib.php) only lets
    // itemname/idnumber/gradetype/grademax/grademin/scaleid/multfactor/plusfactor/
    // deleted/hidden through. The pass grade has to be applied directly on the
    // grade_item instead, mirroring how mod_workshop does it.
    if ($result === GRADE_UPDATE_OK && !$isreset && !empty($playergroup->gradepass)) {
        $gradeitem = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'playergroup',
            'iteminstance' => $playergroup->id,
            'itemnumber' => 0,
            'courseid' => $playergroup->courseid,
        ]);
        if ($gradeitem && (float)$gradeitem->gradepass !== (float)$playergroup->gradepass) {
            $gradeitem->gradepass = (float)$playergroup->gradepass;
            $gradeitem->update();
        }
    }

    return $result;
}

/**
 * Updates grades in the gradebook for a playergroup instance.
 *
 * Grades are awarded permanently to students who qualified, that is, who were in a group
 * that had reached the minimum number of members (see \mod_playergroup\local\qualification).
 * Leaving a group does not remove a previously earned grade, and neither does the group
 * shrinking afterwards. Students who never qualified are not graded.
 *
 * @param \stdClass $playergroup The playergroup instance.
 * @param int $userid Award grade for this user only (0 = every student who qualified).
 * @return void
 */
function playergroup_update_grades(\stdClass $playergroup, int $userid = 0): void {
    $grades = \mod_playergroup\local\grades::for_students($playergroup, $userid > 0 ? [$userid] : []);

    if (!empty($grades)) {
        playergroup_grade_item_update($playergroup, $grades);
    }
}

/**
 * Populates the course module info object with custom completion rule data.
 *
 * Called by Moodle when building cm_info. Stores the completionjoingroup flag
 * in customdata so activity_custom_completion::get_available_custom_rules() can
 * determine which rules are enabled for this instance.
 *
 * @param \stdClass $coursemodule The raw course_modules row (id, instance, …).
 * @return \cached_cm_info|false A populated info object, or false on failure.
 */
function playergroup_get_coursemodule_info(\stdClass $coursemodule): \cached_cm_info|false {
    global $DB;

    $fields = 'id, name, intro, introformat, completionjoingroup, timeopen, timeclose';
    $playergroup = $DB->get_record('playergroup', ['id' => $coursemodule->instance], $fields);
    if (!$playergroup) {
        return false;
    }

    $info = new \cached_cm_info();
    $info->name = $playergroup->name;

    // The core shows the activity description on the course page only when the module fills it in.
    if (!empty($coursemodule->showdescription)) {
        // Do not filter the cached version: the filters run at display time.
        $info->content = format_module_intro('playergroup', $playergroup, $coursemodule->id, false);
    }

    // The completion rule only applies while the tracking is automatic, whatever stayed in the database.
    $automatic = (int) ($coursemodule->completion ?? 0) === COMPLETION_TRACKING_AUTOMATIC;
    if ($automatic && !empty($playergroup->completionjoingroup)) {
        $info->customdata['customcompletionrules']['completionjoingroup'] = 1;
    }

    // Carried in the modinfo cache so playergroup_cm_info_view() can read the availability
    // window without a database query on every course page render.
    $info->customdata['timeopen'] = (int) $playergroup->timeopen;
    $info->customdata['timeclose'] = (int) $playergroup->timeclose;

    return $info;
}

/**
 * Injects availability information below the activity link on the course page.
 *
 * Called by Moodle when rendering the course module list. Displays the opening
 * date or a "closed" notice when the activity is outside its availability window.
 *
 * @param \cm_info $cm The course module info object.
 * @return void
 */
function playergroup_cm_info_view(\cm_info $cm): void {
    $customdata = $cm->customdata ?? [];
    $now        = time();
    $timeopen   = (int) ($customdata['timeopen'] ?? 0);
    $timeclose  = (int) ($customdata['timeclose'] ?? 0);

    if ($timeopen > 0 && $now < $timeopen) {
        $msg = get_string('activityopensfrom', 'mod_playergroup', userdate($timeopen));
        $cm->set_after_link(\html_writer::tag(
            'div',
            \html_writer::tag('small', $msg, ['class' => 'text-muted']),
            ['class' => 'pg-availability-hint mt-1']
        ));
    } else if ($timeclose > 0 && $now > $timeclose) {
        $msg = get_string('activityclosedmsg', 'mod_playergroup');
        $cm->set_after_link(\html_writer::tag(
            'div',
            \html_writer::tag('small', $msg, ['class' => 'text-muted']),
            ['class' => 'pg-availability-hint mt-1']
        ));
    }
}

/**
 * Describes the active custom completion rules.
 *
 * @param \stdClass $course The course object.
 * @param \cm_info $cm The course module info.
 * @return array An array of active completion rule descriptions.
 */
function playergroup_get_completion_active_rule_descriptions(\stdClass $course, \cm_info $cm): array {
    $descriptions = [];

    $rules = $cm->customdata['customcompletionrules'] ?? [];
    if (!empty($rules['completionjoingroup'])) {
        $descriptions[] = get_string('completionjoingroup_desc', 'mod_playergroup');
    }

    return $descriptions;
}

/**
 * Brings the calendar events of the opening and closing dates in step with the activities.
 *
 * Called by the core when it duplicates an activity or refreshes the events of a course, and by this plugin
 * after a reset of the course and from the upgrade.
 *
 * @param int $courseid The course, or 0 for every course.
 * @param int|\stdClass|null $instance One activity, as a record or an id, to refresh only it.
 * @param object|null $cm The course module of that activity, when the caller has it.
 * @return bool Always true.
 */
function playergroup_refresh_events(int $courseid = 0, int|\stdClass|null $instance = null, ?object $cm = null): bool {
    global $DB;

    if ($instance === null) {
        \mod_playergroup\local\calendar::refresh_events($courseid);
        return true;
    }

    if (!is_object($instance)) {
        $instance = $DB->get_record('playergroup', ['id' => $instance], '*', MUST_EXIST);
    }
    $cm ??= get_coursemodule_from_instance('playergroup', $instance->id, $instance->course, false, MUST_EXIST);
    \mod_playergroup\local\calendar::set_events($instance, (int) $cm->id, !empty($cm->visible));

    return true;
}

/**
 * Tells the calendar and the timeline what to offer for an event of the activity.
 *
 * The only events are the date the teacher expects the activity to be completed by. Once the student
 * completed it there is nothing left to do, so no action is offered.
 *
 * @param \calendar_event $event The calendar event.
 * @param \core_calendar\action_factory $factory The factory of actions.
 * @param int $userid The user the action is for, or 0 for the current user.
 * @return \core_calendar\local\event\entities\action_interface|null The action, or null for none.
 */
function mod_playergroup_core_calendar_provide_event_action(
    \calendar_event $event,
    \core_calendar\action_factory $factory,
    int $userid = 0
): ?\core_calendar\local\event\entities\action_interface {
    global $USER;

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['playergroup'][$event->instance] ?? null;
    if (!$cm || !$cm->uservisible) {
        return null;
    }

    $windowevents = [\mod_playergroup\local\calendar::EVENT_OPEN, \mod_playergroup\local\calendar::EVENT_CLOSE];
    if (in_array($event->eventtype, $windowevents, true)) {
        return \mod_playergroup\local\calendar::window_action($cm, $factory, $userid ?: (int) $USER->id);
    }

    $completion = new \completion_info($cm->get_course());
    if ($completion->get_data($cm, false, $userid)->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
        get_string('view'),
        new \moodle_url('/mod/playergroup/view.php', ['id' => $cm->id]),
        1,
        true
    );
}
