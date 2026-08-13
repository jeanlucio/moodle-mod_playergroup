# 🧪 Testes Automatizados

O PlayerGroup inclui uma suíte PHPUnit cobrindo lógica de negócio, web services, relatórios e
exportações, o renderer e a saída do app mobile, a API pública, e conformidade com a Privacy
API, além de uma suíte Behat para aceitação em navegador. Todo push de CI executa a matriz
completa (Moodle 4.5 → 5.x, PostgreSQL e MariaDB).

### PHPUnit — Por Área

| Área | Arquivos | Casos |
|------|---------:|------:|
| Web services (`tests/external/`) | 8 | 53 |
| Núcleo (`lib_test.php`, notas, conclusão, lock, ordenação de integrantes, backup, privacidade) | 7 | 36 |
| Relatórios e exportações (`tests/controller/`) | 2 | 5 |
| Saída e app mobile (`tests/output/`) | 2 | 14 |
| API pública (`tests/api/`) | 1 | 6 |
| **Total Geral** | **20** | **114** |

```bash
vendor/bin/phpunit --testsuite mod_playergroup
```

**Cobertura de linha geral** (`moodle-coverage`, PHPUnit + Xdebug): **80%**.

### Behat — Testes de Aceitação

| Arquivo de feature | Cenários |
|--------------------|--------:|
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

[Detalhamento completo teste-a-teste e tabela de cobertura →]({{ '/testing.html' | relative_url }})
