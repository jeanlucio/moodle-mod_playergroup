# 🧪 Testes Automatizados

O PlayerGroup inclui uma suíte PHPUnit cobrindo lógica de negócio, web services, relatórios/exportações,
o calendário, o renderer e a saída do app mobile, a API pública, upgrades, backup/restore e conformidade com a Privacy API, além de uma
suíte Behat para aceitação em navegador. Todo push de CI executa a matriz completa (Moodle 4.5 → 5.x,
PostgreSQL e MariaDB).

### PHPUnit — Por Área

| Área | Arquivos | Casos |
|------|---------:|------:|
| Núcleo (`lib_test.php`, notas, conclusão, locks, upgrade, backup, privacidade) | 12 | 162 |
| Classes de domínio e eventos (`tests/local/`, `tests/event/`) | 10 | 72 |
| Web services (`tests/external/`) | 9 | 161 |
| Relatórios e exportações (`tests/controller/`) | 2 | 8 |
| Saída e app mobile (`tests/output/`) | 2 | 28 |
| API pública (`tests/api/`) | 1 | 6 |
| **Total Geral** | **36** | **437** |

```bash
vendor/bin/phpunit --testsuite mod_playergroup
```

**Cobertura geral de linhas** (`moodle-coverage`, PHPUnit + Xdebug): **98.9%**.

### Behat — Testes de Aceitação

| Arquivo de feature | Cenários |
|--------------------|---------:|
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

[Detalhamento teste a teste e tabela de cobertura →]({{ '/testing.html' | relative_url }})
