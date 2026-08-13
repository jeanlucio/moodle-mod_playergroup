# 🧪 Automated Tests

PlayerGroup ships with a PHPUnit suite covering business logic, web services, reports/exports,
the renderer and mobile app output, the public API, and Privacy API compliance, plus a Behat
suite for browser acceptance. Every CI push runs against the full matrix (Moodle 4.5 → 5.x,
PostgreSQL & MariaDB).

### PHPUnit — By Area

| Area | Files | Cases |
|------|------:|------:|
| Web services (`tests/external/`) | 8 | 53 |
| Core (`lib_test.php`, grading, completion, locking, member ordering, backup, privacy) | 7 | 36 |
| Reports & exports (`tests/controller/`) | 2 | 5 |
| Output & mobile app (`tests/output/`) | 2 | 14 |
| Public API (`tests/api/`) | 1 | 6 |
| **Grand Total** | **20** | **114** |

```bash
vendor/bin/phpunit --testsuite mod_playergroup
```

**Overall line coverage** (`moodle-coverage`, PHPUnit + Xdebug): **80%**.

### Behat — Acceptance Tests

| Feature file | Scenarios |
|--------------|----------:|
| `create_group.feature` | 2 |
| `join_group.feature` | 2 |
| `join_protected_group.feature` | 3 |
| `view.feature` | 3 |
| `invite_colleagues.feature` | 1 |
| `view_members.feature` | 1 |
| **Total** | **12** |

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags=@mod_playergroup --profile=chrome
```

[Full test-by-test breakdown and coverage table →]({{ '/testing.html' | relative_url }})
