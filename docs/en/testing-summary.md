# 🧪 Automated Tests

PlayerGroup ships with a PHPUnit suite covering business logic, web services, reports/exports,
the calendar, the renderer and mobile app output, the public API, upgrades, backup/restore and Privacy API compliance, plus a Behat
suite for browser acceptance. Every CI push runs against the full matrix (Moodle 4.5 → 5.x,
PostgreSQL & MariaDB).

### PHPUnit — By Area

| Area | Files | Cases |
|------|------:|------:|
| Core (`lib_test.php`, grading, completion, locking, upgrade, backup, privacy) | 12 | 162 |
| Domain classes and events (`tests/local/`, `tests/event/`) | 10 | 72 |
| Web services (`tests/external/`) | 9 | 161 |
| Reports & exports (`tests/controller/`) | 2 | 8 |
| Output & mobile app (`tests/output/`) | 2 | 28 |
| Public API (`tests/api/`) | 1 | 6 |
| **Grand Total** | **36** | **437** |

```bash
vendor/bin/phpunit --testsuite mod_playergroup
```

**Overall line coverage** (`moodle-coverage`, PHPUnit + Xdebug): **98.9%**.

### Behat — Acceptance Tests

| Feature file | Scenarios |
|--------------|----------:|
| `create_group.feature` | 2 |
| `join_group.feature` | 2 |
| `join_protected_group.feature` | 3 |
| `view.feature` | 5 |
| `view_members.feature` | 1 |
| `invite_colleagues.feature` | 1 |
| `invite_flow.feature` | 2 |
| `minimum_members.feature` | 2 |
| `reports.feature` | 4 |
| `index.feature` | 2 |
| **Total** | **24** |

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags=@mod_playergroup --profile=chrome
```

[Full test-by-test breakdown and coverage table →]({{ '/testing.html' | relative_url }})
