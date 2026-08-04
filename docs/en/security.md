# 🔐 Security & Compliance

* Capability-based access control (`mod/playergroup:creategroup`, `mod/playergroup:view`, `mod/playergroup:manage`, `mod/playergroup:manageinvites`)
* Every group operation is scoped by activity instance — a group ID or invite ID from another activity, or another course, is always rejected rather than trusted
* `require_sesskey()` protection on all state-changing operations; AJAX calls are validated by Moodle's `core/ajax` dispatcher
* Group creation, joining, and invite acceptance are serialised per activity instance through `\core\lock\lock_config`, closing a check-then-act race that could otherwise let two concurrent requests place a student in two groups or push a group past its member limit
* The invite picker only lists actively enrolled students and is gated by `moodle/course:viewparticipants`, so it never becomes an alternate route to participant information a course has deliberately hidden from students
* Group passwords are stored with `password_hash()` and verified with `password_verify()`; the stored hash is never returned to the client
* Deleting the activity only ever removes groups and a grouping it created itself — never a pre-existing grouping (or its other groups) the teacher pointed the activity at
* Moodle External API compliant
* Full Privacy API implementation — data export and deletion supported
* Backup and restore support, including safe ID remapping across a course duplication or restore
