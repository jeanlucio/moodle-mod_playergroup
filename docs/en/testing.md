# 🧪 Automated Tests

PlayerGroup ships with a PHPUnit test suite covering business logic, web services, reports and
exports, the calendar, the renderer and mobile app output, the public API, upgrades, backup and
restore, and Privacy API compliance, plus a Behat suite for browser acceptance. Every CI push runs
against the full matrix (Moodle 4.5 → 5.x, PostgreSQL & MariaDB).

### Core (`tests/`, `tests/backup/`, `tests/completion/`, `tests/privacy/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `lib_test.php` | 33 | Instance lifecycle (`add`/`update`/`delete_instance`); supported features and branded icon; deleting an activity only removes the groups and grouping it registered; the grouping name fits its column; the expected-completion date and the opening/closing dates reach the calendar and follow later edits; the timeline action (usable inside the window, waiting before it, gone after it, for a student already in a group, or for a hidden activity); a duplicated activity gets its own events |
| `lib_grade_item_update_test.php` | 3 | The pass grade lands on the gradebook `grade_item`; a reset clears the grades and keeps the item; an activity without a grade creates no item |
| `playergroup_grade_test.php` | 7 | Grade on joining, bulk award, persistence after leaving, none when disabled, the submission date, and lowering the minimum grades every group already formed with the date each student qualified |
| `access_test.php` | 4 | The capabilities carry the right risk bitmasks and types: `manage` is flagged as personal data and is a read capability, and the ones that change data stay write |
| `db_declarations_test.php` | 10 | Every service declared in `db/services.php` has its class and method, describes its parameters and returns, and is available to AJAX and the app; the mobile handler points at real files and strings; the message provider and the observers are complete |
| `mod_form_test.php` | 22 | Settings form validation: member limits, closing date after opening, no scale grade, name and grouping-name lengths and duplicates; the completion rule is read, kept and cleared correctly; the grouping selector offers safe HTML and defaults |
| `observer_test.php` | 24 | Reactions to the Groups page and the enrolment: empty groups deleted or kept; leadership passes to the oldest member when the leader is removed; an emptied group opens again and its next member leads it; pending invites settle and the minimum qualifies groups for members added from the Groups page; deleting the grouping |
| `upgrade_test.php` | 14 | The real `xmldb_playergroup_upgrade()` on data shaped like an old site: everyone in a group or holding a grade keeps what they had, the step can run again, batches larger than one write, an empty or current site upgrades cleanly; the calendar step gives existing activities their events once |
| `completion/custom_completion_test.php` | 8 | Custom completion rule `completionjoingroup`: follows the qualification, not the current membership; descriptions, sort order and the rules listed on the course page |
| `backup/restore_test.php` | 14 | Backup/restore round trip with and without user data; foreign keys remapped, rows without their group or person dropped; the opening/closing dates follow the new course start; the restore brings one event of each kind, and a backup made before the events existed gets them too |
| `local/group_lock_test.php` | 3 | Lock acquired when free; `grouplockbusy` when held by a genuinely different database connection; another activity's lock is never blocked |
| `privacy/provider_test.php` | 20 | Metadata declaration (every column declared or left out on purpose), context and user discovery, export, and deletion by user, by list and by context, including handing a leader's group over |
| **Subtotal** | **162** | |

### Domain classes and events (`tests/local/`, `tests/event/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `local/qualification_test.php` | 16 | Minimum group size: every member qualifies when the group reaches it and keeps it after leaving; lowering or raising the minimum; grades and dates; awarding a whole class costs fewer queries than awarding one by one |
| `local/grades_test.php` | 4 | Grades built for everyone or for a list of students in one query; a student who never qualified is skipped; an activity without a grade has none |
| `local/calendar_test.php` | 12 | Events of the opening and closing dates: types by which dates are set, names, hidden activities, updates keep the same event, clearing one date removes only its event, refresh by record, id, course and site without repeating, repeated events cleaned up, completion event kept, deletion leaves none |
| `local/course_reset_test.php` | 10 | The real `reset_course_userdata()`: completion, grades, groups and unenrolment clear the right data; the dates and the calendar events move with the new course start |
| `local/activity_window_test.php` | 6 | The availability window: unbounded, boundaries, a single bound, missing properties, and the error codes |
| `local/activity_log_test.php` | 5 | The activity log: no log store, empty, only this activity's events newest first, the limit, and a placeholder for unknown users |
| `local/groups_report_test.php` | 5 | Rows of the groups-and-members report: leader and members, a group kept with no member still has a row, mixed order, no groups, plain-text names |
| `local/member_list_test.php` | 5 | Leader first, non-leaders alphabetical, a list with no leader, an empty list, and who may see names |
| `local/text_test.php` | 5 | Names as plain text: as typed, entities decoded, markup stripped, empty input, escaped once |
| `event/events_test.php` | 4 | Name, description, link and dispatch of every event; the viewed and list-viewed events are read-only; the object ids are remapped when a log is restored |
| **Subtotal** | **72** | |

### Web Services (`tests/external/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `external/create_group_test.php` | 32 | Privacy levels, password hashing, creator membership, capabilities, duplicate names (also across courses), field limits, completion; a user who is not enrolled is told why and nothing is left behind; blocked while another request holds the lock |
| `external/join_group_test.php` | 18 | Success, completion, already-in-group, closed and full groups, passwords (wrong one is a plain `moodle_exception`), invited users, pending invites resolved on join, a user who is not enrolled is refused with the reason; blocked while another request holds the lock |
| `external/leave_group_test.php` | 14 | Success, `canleave` guard, not-in-group guard, empty-group deletion, leadership transfer, pending invites cancelled |
| `external/edit_group_test.php` | 26 | Edits name, description, badge and privacy; only the leader who is still in the group; passwords hashed and kept; a `groupid` from another activity is rejected; name rules; closing needs the right to invite; errors name the real reason |
| `external/accept_invite_test.php` | 16 | Success, completion (manual and automatic), wrong user, already handled, group full, receiver already in a group, window dates, capabilities; blocked while another request holds the lock |
| `external/reject_invite_test.php` | 8 | Success, wrong user, already handled, invalid id, window dates, capabilities and the declared structure |
| `external/send_invite_test.php` | 20 | Pending invites, re-inviting after leaving, who may invite, full group, an invitee who already has a group (the sender is told so), the notification belongs to the course of the activity, window dates |
| `external/get_activity_data_test.php` | 22 | The web and app payload: members and leader flag validated against `execute_returns()`, names withheld without `viewparticipants`, missing-members notice, the full badge instead of the join button, no actions offered to someone who is not enrolled |
| `external/view_playergroup_test.php` | 5 | Viewing completes the view rule and triggers the viewed event; outsiders and users without the capability are refused; the declared structure |
| **Subtotal** | **161** | |

### Reports & Exports (`tests/controller/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `controller/export_test.php` | 3 | CSV export has a localized row per logged event; an activity without events exports only the header |
| `controller/export_groups_test.php` | 5 | One row per member with group, privacy and role; closed groups labelled; a group kept with no member still has its row; names written as plain text; no groups exports only the header |
| **Subtotal** | **8** | |

### Output & Mobile App (`tests/output/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `output/renderer_test.php` | 18 | Student view empty and populated states; the group card escapes the name once; the full badge replaces the join button; the missing-members notice; both reports; password fields set `autocomplete="new-password"` |
| `output/mobile_test.php` | 10 | `mobile_init`; `mobile_course_view` returns the page plus group and member data; capability enforcement; the description is bound to the sanitised field; the full badge comes with its translated text |
| **Subtotal** | **28** | |

### Public API (`tests/api/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `api/group_info_test.php` | 6 | No group returns null; a group's summary; default badge; bulk badge lookup; empty input; a group without PlayerGroup metadata is omitted |
| **Subtotal** | **6** | |

| **Grand Total** | **437** | |

```bash
vendor/bin/phpunit --testsuite mod_playergroup
```

"Cases" counts the test methods; data providers expand some of them, and PHPUnit executes 455 tests in all.

**Line coverage** (`moodle-coverage`, PHPUnit + Xdebug, measured by what the tests execute): **98.9%** of the plugin logic. 38 of the 45 files with logic are at 100%, including every event class, `calendar`, `grades`, `participation` and `groups_report`; the others:

| File | Line coverage |
|------|:-------------:|
| `classes/observer.php` | 94% |
| `classes/privacy/provider.php` | 96% |
| `classes/external/create_group.php` | 96% |
| `mod_form.php` | 97% |
| `classes/external/join_group.php` | 98% |
| `classes/local/qualification.php` | 98% |
| `lib.php` | 99% |

The entry points (`view.php`, `index.php`, `export.php`) are exercised by the Behat suite, not by PHPUnit.

### Behat — Acceptance Tests

| Feature file | Scenarios | What is covered |
|--------------|----------:|----------------|
| `create_group.feature` | 2 | A student creates an open group; creation is blocked once they belong to one |
| `join_group.feature` | 2 | A second student joins and sees the My Group badge; no Join buttons for someone already in a group |
| `join_protected_group.feature` | 3 | Enter in the password field joins like Save; the wrong password shows a dialog on the page, never a system error; the field offers no saved passwords |
| `view.feature` | 5 | Create Group button and empty state; report link by role; no create button for a non-editing teacher or a student whose right was taken away |
| `view_members.feature` | 1 | A student opens the member list and sees both members, with the leader marked |
| `invite_colleagues.feature` | 1 | A group creator sees an enrolled colleague in the invite modal |
| `invite_flow.feature` | 2 | The colleague accepts and joins the group, or declines and stays without one |
| `minimum_members.feature` | 2 | The founder is told how many members are missing; the notice follows the group and goes away at the minimum |
| `reports.feature` | 4 | Teacher sees the activity log and the groups-and-members report, reaches both from the activity page; a student asking for the log gets the normal page |
| `index.feature` | 2 | The course index says there are none, or lists the activities |
| **Total** | **24** | |

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags=@mod_playergroup --profile=chrome
```
