# Changelog

All notable changes to PlayerGroup are documented in this file.

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
