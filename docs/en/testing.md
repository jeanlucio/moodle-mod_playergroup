# 🧪 Automated Tests

PlayerGroup ships with a PHPUnit test suite covering business logic, web services, reports and
exports, the renderer and mobile app output, the public API, and Privacy API compliance, plus a
Behat suite for browser acceptance. Every CI push runs against the full matrix (Moodle 4.5 →
5.x, PostgreSQL & MariaDB).

### Core (`tests/`, `tests/backup/`, `tests/completion/`, `tests/local/`, `tests/privacy/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `lib_test.php` | 9 | `add_instance`/`delete_instance` lifecycle, supported-features declaration; deleting an activity pointed at a pre-existing grouping only removes groups this instance registered, leaving a foreign group and the grouping itself untouched; an auto-created ("new" mode) grouping is still removed once left empty |
| `playergroup_grade_test.php` | 4 | Grade award on join, bulk award, grade persistence after leaving, no grade when disabled |
| `completion/custom_completion_test.php` | 2 | Custom completion rule `completionjoingroup`: incomplete without a group, complete once the student belongs to a group registered for the activity |
| `backup/restore_test.php` | 3 | Backup/restore round-trip for content-only and user-data modes; original course unaffected |
| `local/group_lock_test.php` | 3 | Lock acquired when free; throws `grouplockbusy` when the resource is held by a genuinely different database connection (not just a second lock-factory instance on the same one); a different activity instance's lock is never blocked by another |
| `privacy/provider_test.php` | 11 | Metadata declaration, context discovery, data export (creator/receiver), bulk and targeted deletion |
| **Subtotal** | **32** | |

### Web Services (`tests/external/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `create_group_test.php` | 11 | All privacy levels, password hashing, creator membership, capability enforcement, duplicate and invalid-cmid guards, completion tracking, blocked while another request holds the per-instance lock |
| `join_group_test.php` | 10 | Success, completion tracking, already-in-group and closed-group rejections, protected-group joins (correct/wrong password), invited user joining via password, resolution of pending invites on join, blocked while another request holds the per-instance lock |
| `leave_group_test.php` | 8 | Success, `canleave` guard, not-in-group guard, empty-group auto-deletion, leadership transfer, pending invite cancellation |
| `edit_group_test.php` | 6 | Updates name/description/badge; a non-creator is rejected; a new password is hashed; leaving the password blank keeps the existing hash; switching away from protected clears it; a `groupid` from another activity instance is rejected |
| `accept_invite_test.php` | 6 | Success, completion tracking (manual/automatic), wrong-user and already-handled rejections, blocked while another request holds the per-instance lock |
| `reject_invite_test.php` | 4 | Success, wrong-user rejection, already-handled rejection, invalid inviteid |
| `send_invite_test.php` | 2 | Pending invite creation, re-inviting a student after they join and leave a group |
| `get_activity_data_test.php` | 1 | The mobile/web payload includes each group's member list and leader flag, validated against `execute_returns()` — this is what the mobile app actually consumes |
| **Subtotal** | **48** | |

### Reports & Exports (`tests/controller/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `export_test.php` | 2 | CSV export contains a localized row per logged event; an activity with no events exports only the header row |
| `export_groups_test.php` | 3 | One row per member with the correct group, privacy, and role (leader/member) labels; a closed group is labelled correctly; an activity with no groups exports only the header row |
| **Subtotal** | **5** | |

### Output & Mobile App (`tests/output/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `renderer_test.php` | 6 | Student view empty/populated states; activity log report empty/populated states; groups-and-members report empty/populated states |
| `mobile_test.php` | 3 | `mobile_init` returns `init.js` verbatim; `mobile_course_view` returns the rendered page plus group/member data; capability enforcement |
| **Subtotal** | **9** | |

### Public API (`tests/api/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `group_info_test.php` | 6 | No group returns null; a group's summary fields; default badge fallback; bulk badge lookup across several groups; empty input; a native group with no PlayerGroup metadata is omitted |
| **Subtotal** | **6** | |

| **Grand Total** | **100** | |

```bash
vendor/bin/phpunit --testsuite mod_playergroup
```

**Line coverage by class (PHPUnit + Xdebug):**

| Class | Line coverage |
|-------|:-------------:|
| `api\group_info` | 100% |
| `controller\export` | 100% |
| `output\mobile` | 100% |
| `output\renderer` | 100% |
| `local\group_lock` | 100% |
| `controller\export_groups` | 98% |
| `privacy\provider` | 94% |
| `instance_manager` | 93% |
| `external\edit_group` | 92% |
| `external\leave_group` | 92% |
| `external\create_group` | 90% |
| `external\join_group` | 91% |
| `external\send_invite` | 89% |
| `external\accept_invite` | 89% |
| `external\get_activity_data` | 89% |
| `external\reject_invite` | 88% |
| `completion\custom_completion` | 56% |
| **Overall** | **80%** |

The `event/*.php` classes aren't listed — Moodle only loads them lazily when the corresponding
event actually fires, so the instrumentation never sees them.

### Behat — Acceptance Tests

| Feature file | Scenarios | What is covered |
|--------------|----------:|----------------|
| `create_group.feature` | 2 | Student creates an open group; creation blocked after already joining one |
| `join_group.feature` | 2 | Second student joins and sees the My Group badge; one-group-per-student enforcement |
| `view.feature` | 3 | Student sees the empty state and Create Group button; report link visibility by role |
| `invite_colleagues.feature` | 1 | A group creator sees an enrolled colleague listed in the invite modal |
| `view_members.feature` | 1 | A student opens the member list and sees both members, with the leader marked |
| **Total** | **9** | |

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags=@mod_playergroup --profile=chrome
```
