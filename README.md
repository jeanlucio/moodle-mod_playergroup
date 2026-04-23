# PlayerGroup — Moodle Activity Module

[![Moodle Plugin CI](https://github.com/jeanlucio/moodle-mod_playergroup/actions/workflows/ci.yml/badge.svg)](https://github.com/jeanlucio/moodle-mod_playergroup/actions/workflows/ci.yml)

PlayerGroup is a Moodle activity module that turns group formation into a gamified experience — **The Tavern**. Students autonomously create guilds, invite peers, and join teams, while the plugin seamlessly integrates with gamification ecosystems such as [PlayerHUD](https://github.com/jeanlucio/moodle-block_playerhud) and PlayerRaid.

## Features

- Students create groups with a name, description, and an emoji badge
- Configurable min/max members per group
- Group privacy levels: open, protected (password), and closed
- Peer invite system via Moodle's native notification (bell + email)
- Automatic grouping creation — no manual setup required for teachers
- Gradebook integration: foundation reward for group creators
- Activity completion rule: student must join or create a group
- Full Moodle Groups API integration (native groups and groupings)

## Requirements

| Component | Version |
|-----------|---------|
| Moodle    | 4.5+    |
| PHP       | 8.2+    |

## Installation

1. Download or clone this repository into `mod/playergroup` inside your Moodle root.
2. Visit **Site administration > Notifications** to run the database upgrade.
3. Add a **PlayerGroup** activity to any course.

```bash
git clone git@github.com:jeanlucio/moodle-mod_playergroup.git mod/playergroup
```

## Configuration

When adding the activity, teachers can configure:

- **Minimum / Maximum members** per group
- **Allow students to leave** their group
- **Delete groups on activity deletion** — if checked, all groups and the grouping are permanently removed when the activity is deleted
- **Foundation reward** — grade awarded to the student who creates the group

## Development Status

> **Alpha** — core features under active development. Not recommended for production use.

Planned phases:

- [x] Phase 1 — Foundation (DB schema, form, grade API, completion, events)
- [ ] Phase 2 — Student interface (join/leave, tabs, group cards)
- [ ] Phase 3 — Invite system (Message API, accept/reject)
- [ ] Phase 4 — Ecosystem API + audit events
- [ ] Phase 5 — Privacy API, backup/restore, PHPUnit tests

## License

This program is free software: you can redistribute it and/or modify it under the terms of the
[GNU General Public License](https://www.gnu.org/licenses/gpl-3.0.html) as published by the
Free Software Foundation, either version 3 of the License, or (at your option) any later version.

Copyright © 2026 Jean Lúcio
