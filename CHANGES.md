# Changelog

All notable changes to PlayerGroup are documented in this file.

## [v1.3.1] — 2026-08-10

### Added

- Declared the activity's chooser purpose (collaboration + assessment), so it now appears under its category tabs in "Add an activity or resource" instead of only under "All"

## [v1.3.0] — 2026-08-05

### Added

- Students can view the full member list of any group in the activity, not just their own, through a "View Members" button on each group card; mirrored in the Moodle mobile app, with the leader marked
- Teacher "Groups and Members" composition report: one row per member across every group (group, privacy, role, member name), with its own CSV/Excel export, alongside the existing activity-log report
- Member lists (web and mobile) now show the group leader first, then the rest alphabetically, instead of join order

### Security

- Invite picker (web and mobile) and the `send_invite` web service now consistently gate on `moodle/course:viewparticipants` and exclude suspended/expired enrolments, so a professor who hid the course participant list from students can no longer be bypassed by calling the service directly
- Group join, create, and accept-invite operations are now serialised per activity instance with a lock, closing a race that could let a student join two groups at once or push a group over its member cap
- `join_group`, `accept_invite`, and `create_group` now check the return value of Moodle's `groups_add_member()` instead of assuming success; a caller who is not actually enrolled in the course no longer receives a grade, completion state, or a group with no real member
- Group descriptions rendered in the mobile app now go through the same sanitisation as the web view
- Group name is no longer used unsanitised as the "View Members" modal title
- Export format is now validated against installed dataformat plugins instead of being passed straight through
- Deleting the activity no longer destroys groups that already existed in the same grouping before the activity was added to the course

### Changed

- Simplified the plugin icon SVG
- Failed group actions (join, leave, edit, create, invite) now show a plain message instead of Moodle's generic developer error dialog with a stack trace
- Edit Group and Leave Group buttons now show their label next to the icon instead of icon-only

### Fixed

- `playergroup_cm_info_view()` no longer queries the database on every course page render; the activity's opening/closing time now travels in the modinfo cache

## [v1.2.4] — 2026-06-23

### Added

- New public API method `group_info::get_badges_for_groups()` returns the badge of several groups in a single query, letting other plugins (e.g. block_playerhud) display group badges in a list without per-group queries. Groups managed by PlayerGroup but without a configured badge fall back to the default shield emoji, matching `get_player_group_in_course()`

## [v1.2.3] — 2026-06-17

### Security

- `send_invite` now verifies the recipient is enrolled in the course before creating the invite and sending the notification, preventing cross-context notification spam
- Activity open/close window is now enforced in `create_group`, `edit_group`, and `accept_invite` (previously only `join_group` enforced it)

### Fixed

- Group name and description no longer double-encode HTML entities on edit; `rawname`/`rawdescription` now pass the raw database value and let Mustache handle the single HTML-attribute escaping, preventing progressive corruption of names containing `&`, `<`, `>`, `"`, or `'`
- Export now streams log entries row-by-row via a recordset instead of loading the full result into memory, preventing potential memory spikes on large activity logs
- Password column (`playergroup_meta.password`) widened from `char(64)` to `char(255)` so future PHP `PASSWORD_DEFAULT` algorithm changes cannot silently truncate stored hashes

### Removed

- Dropped the legacy `playergroup_get_completion_state()` callback (dead code since Moodle no longer invokes it on 4.5+); completion is handled solely by the `custom_completion` class, whose coverage moved to a dedicated test

## [v1.2.2] — 2026-06-12

### Fixed

- Activity description now appears in the Moodle mobile app: the app only fills `module.description` when "Display description on course page" is enabled, but the web view always shows the intro; the mobile view now renders the activity intro through `core-course-module-info`, matching the site behaviour

## [v1.2.1] — 2026-06-04

### Changed

- Mobile app files relocated to the standard layout recommended by Moodle HQ (MDL-72048): JS files moved from `mobile/js/` to `js/mobileapp/`, template moved from `templates/` to `templates/mobileapp/`

## [v1.2.0] — 2026-06-03

### Added

- CSV and Excel export buttons on the teacher activity-log report, downloading the full log (date, user, action) through Moodle's native dataformat API
- Native Moodle App (mobile) support: the full student experience — create, join and leave groups, privacy levels, send/accept/decline invites, and view "My Group" — is available in the official Moodle mobile app through an Ionic/Angular view
- `mod_playergroup_get_activity_data` external web service that returns the full activity state (groups, current user's group, received invites, inviteable users, capabilities); it powers the mobile app
- Show/hide ("eye") toggle on every group password field — create, edit and join — using the core `togglesensitive` component
- `--force` flag on both CLI seed scripts to bypass the development-site guard on development instances that use a public domain
- PHPUnit coverage for protected-group joins (correct and wrong password), an invited student joining via the password flow, and the declining of other pending invites on join
- New `external/send_invite_test.php` covering pending invite creation and re-inviting a student after they join and leave a group

### Changed

- Joining a group now resolves the user's pending invites — the invite for the joined group is marked accepted and any others declined — so a stale invite no longer reappears if the user later leaves the group (mirrors the accept-invite behaviour)
- CLI seed scripts now resolve `config.php` across both the classic (4.x) and `public/` (5.x) Moodle directory layouts
- CI matrix standardized: PHPCS/PHPMD checks on PHP 8.3, PHP 8.4 in the runtime matrix, and Behat faildump artifacts uploaded on failure

### Fixed

- Edit-group modal no longer resets privacy to "Open": the privacy dropdown and the password-field visibility are now rendered from the group's actual values instead of being set by post-render JavaScript that raced the asynchronously attached modal body, which silently downgraded protected or closed groups on save
- Edit-group modal no longer closes when text is selected with the mouse and released outside the dialog; the form fields are wrapped so the core modal does not treat the drag-release as an outside click
- Group action buttons no longer overflow the card on Bootstrap 4 (Moodle 4.5): labels stay on a single line, the buttons no longer shrink, and the action group wraps below the member count when space is tight
- Vertical spacing between group cards restored on Bootstrap 4, where the Bootstrap 5-only `g-3` gutter had no effect; cards now use a cross-version margin

## [v1.1.1] — 2026-05-19

### Security

- CLI seed scripts now require an explicit `--password` flag and abort when `$CFG->wwwroot` does not match a development pattern, preventing accidental creation of known-credential accounts on non-development sites

### Fixed

- Screen reader labels on group action buttons now include the group name (e.g. "Join group Northern Hunters") so assistive technology announces full context instead of a generic label
- Leave-group confirmation modal now shows "Leave Group" as the confirm button instead of the generic "Save changes"
- `encode_content_links` return type hint removed to match the parent class signature, eliminating a PHP declaration incompatibility warning

### Added

- `$plugin->supported` declares the tested compatibility range (Moodle 4.5 → 5.2)
- Automated Moodle Plugin Directory release workflow: pushing a `v*` tag publishes the version automatically

## [v1.1.0] — 2026-05-02

### Added

- Automatic leadership transfer: when the group leader leaves, the oldest remaining member becomes the new leader
- New teacher setting "Automatically delete empty groups": when enabled, a group is deleted (along with its pending invites) when the last member leaves
- Audit event `group_deleted` logged when an empty group is automatically removed

### Fixed

- Activity completion no longer throws `err_system` when manual completion tracking is enabled; completion state is now updated only when the activity is configured for automatic completion
- Invite Colleagues modal now opens correctly in Moodle 5.x environments where Bootstrap's jQuery plugin (`$.fn.modal`) is not guaranteed to be registered; replaced with the cross-version AMD `theme_boost/bootstrap/modal` pattern
- Added missing GPL licence header to `styles.css`

## [v1.0.0] — 2026-04-25

Initial public release.

### Added

- Students create groups with a custom name, description, and emoji badge
- Group privacy levels: open, protected (password), and closed (invite only)
- Peer invite system via Moodle's native Message API (bell notification + email for offline users)
- Configurable minimum and maximum members per group
- Teacher option to prevent students from leaving their group
- Teacher option to delete the grouping and all groups when the activity is deleted
- Automatic Moodle grouping creation — no manual setup required for teachers
- Gradebook integration: grade is awarded automatically when a student joins or creates a group; the grade is permanent even if the student later leaves
- Activity completion rule: student must join or create a group
- Teacher report tab showing the last 200 audit log entries for the activity
- Public PHP API for other plugins: `mod_playergroup\api\group_info::get_player_group_in_course()`
- Audit events: `group_created`, `member_joined`, `member_left`, `invite_accepted`
- Full Privacy API: metadata declaration, data export, and data deletion
- Backup and restore support (content + optional user data)
- PHPUnit test suite (lib, external functions, privacy provider, backup/restore, grade awarding)
- Behat test suite (view, create group, join group)
