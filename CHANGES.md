# Changelog

All notable changes to PlayerGroup are documented in this file.

## [1.2.0] - 2026-06-03

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

## [1.1.1] - 2026-05-19

### Security

- CLI seed scripts now require an explicit `--password` flag and abort when `$CFG->wwwroot` does not match a development pattern, preventing accidental creation of known-credential accounts on non-development sites

### Fixed

- Screen reader labels on group action buttons now include the group name (e.g. "Join group Northern Hunters") so assistive technology announces full context instead of a generic label
- Leave-group confirmation modal now shows "Leave Group" as the confirm button instead of the generic "Save changes"
- `encode_content_links` return type hint removed to match the parent class signature, eliminating a PHP declaration incompatibility warning

### Added

- `$plugin->supported` declares the tested compatibility range (Moodle 4.5 → 5.2)
- Automated Moodle Plugin Directory release workflow: pushing a `v*` tag publishes the version automatically

## [1.1.0] - 2026-05-02

### Added

- Automatic leadership transfer: when the group leader leaves, the oldest remaining member becomes the new leader
- New teacher setting "Automatically delete empty groups": when enabled, a group is deleted (along with its pending invites) when the last member leaves
- Audit event `group_deleted` logged when an empty group is automatically removed

### Fixed

- Activity completion no longer throws `err_system` when manual completion tracking is enabled; completion state is now updated only when the activity is configured for automatic completion
- Invite Colleagues modal now opens correctly in Moodle 5.x environments where Bootstrap's jQuery plugin (`$.fn.modal`) is not guaranteed to be registered; replaced with the cross-version AMD `theme_boost/bootstrap/modal` pattern
- Added missing GPL licence header to `styles.css`

## [1.0.0] - 2026-04-25

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
