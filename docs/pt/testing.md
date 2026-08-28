# 🧪 Testes Automatizados

O PlayerGroup inclui uma suíte PHPUnit cobrindo lógica de negócio, web services, relatórios e
exportações, o renderer e a saída do app mobile, a API pública, e conformidade com a Privacy
API, além de uma suíte Behat para aceitação em navegador. Todo push de CI executa a matriz
completa (Moodle 4.5 → 5.x, PostgreSQL e MariaDB).

### Núcleo (`tests/`, `tests/backup/`, `tests/completion/`, `tests/local/`, `tests/privacy/`)

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `lib_test.php` | 9 | Ciclo de vida `add_instance`/`delete_instance`, declaração de funcionalidades suportadas; excluir uma atividade apontada para um agrupamento pré-existente só remove os grupos que esta instância registrou, deixando um grupo alheio e o próprio agrupamento intactos; um agrupamento auto-criado (modo "novo") ainda é removido quando fica vazio |
| `lib_grade_item_update_test.php` | 1 | A nota de aprovação configurada realmente chega ao `grade_item` do diário de notas, não só que `grade_update()` devolve `GRADE_UPDATE_OK` |
| `playergroup_grade_test.php` | 4 | Atribuição de nota ao entrar, atribuição em lote, persistência da nota após sair, sem nota quando desabilitado |
| `completion/custom_completion_test.php` | 2 | Regra de conclusão personalizada `completionjoingroup`: incompleta sem grupo, completa quando o estudante pertence a um grupo registrado na atividade |
| `backup/restore_test.php` | 3 | Round-trip de backup/restore em modo conteúdo e com dados de usuário; curso original não afetado |
| `local/group_lock_test.php` | 3 | Lock adquirido quando livre; lança `grouplockbusy` quando o recurso está retido por uma conexão de banco genuinamente diferente (não só uma segunda instância da fábrica de locks na mesma conexão); o lock de uma instância de atividade diferente nunca é bloqueado pelo de outra |
| `local/member_list_test.php` | 4 | O líder é colocado primeiro independente da posição na entrada; os demais integrantes são ordenados alfabeticamente; uma lista sem líder ainda ordena todos alfabeticamente; uma lista vazia devolve um array vazio |
| `privacy/provider_test.php` | 11 | Declaração de metadados, descoberta de contextos, exportação de dados (criador/destinatário), exclusão em lote e individual |
| **Subtotal** | **37** | |

### Web Services (`tests/external/`)

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `create_group_test.php` | 12 | Todos os níveis de privacidade, hash de senha, criador como membro, aplicação de capability, guards contra duplicata e cmid inválido, conclusão de atividade, lança exceção e limpa (sem grupo nativo órfão) quando a adição do próprio membro falha, bloqueio enquanto outra requisição segura o lock da instância |
| `join_group_test.php` | 11 | Sucesso, conclusão de atividade, rejeição por já estar em grupo e por grupo fechado, entrada em grupo protegido (senha correta; senha errada rejeitada com `moodle_exception` simples, nunca `coding_exception`), entrada por senha de um estudante convidado, resolução dos convites pendentes ao entrar, lança exceção em vez de suceder silenciosamente quando a adição do próprio membro falha, bloqueio enquanto outra requisição segura o lock da instância |
| `leave_group_test.php` | 8 | Sucesso, guard `canleave`, guard não-é-membro, auto-exclusão de grupo vazio, transferência de liderança, cancelamento de convites pendentes |
| `edit_group_test.php` | 6 | Atualiza nome/descrição/emblema; um não-criador é rejeitado; uma nova senha é hasheada; deixar a senha em branco preserva o hash existente; mudar para longe de protegido limpa a senha; um `groupid` de outra instância de atividade é rejeitado |
| `accept_invite_test.php` | 7 | Sucesso, conclusão de atividade (manual/automática), rejeições por usuário errado e convite já respondido, lança exceção em vez de suceder silenciosamente quando a adição do próprio membro falha, bloqueio enquanto outra requisição segura o lock da instância |
| `reject_invite_test.php` | 4 | Sucesso, rejeição por usuário errado, rejeição por convite já respondido, inviteid inválido |
| `send_invite_test.php` | 3 | Criação de convite pendente, reconvite de um estudante após ele entrar e sair de um grupo, um remetente sem a capability `moodle/course:viewparticipants` é rejeitado |
| `get_activity_data_test.php` | 2 | O payload web/mobile inclui a lista de integrantes de cada grupo e a flag de líder, validado contra `execute_returns()` — é exatamente o que o app mobile consome; os integrantes são ordenados com o líder primeiro, depois alfabeticamente |
| **Subtotal** | **53** | |

### Relatórios e Exportações (`tests/controller/`)

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `export_test.php` | 2 | A exportação CSV contém uma linha localizada por evento registrado; uma atividade sem eventos exporta só o cabeçalho |
| `export_groups_test.php` | 3 | Uma linha por integrante com os rótulos corretos de grupo, privacidade e papel (líder/integrante); um grupo fechado é rotulado corretamente; uma atividade sem grupos exporta só o cabeçalho |
| **Subtotal** | **5** | |

### Saída e App Mobile (`tests/output/`)

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `renderer_test.php` | 10 | Estados vazio/preenchido da visão do estudante; o atributo `data-groupname` do card do grupo usa o nome formatado (com escape), nunca o bruto; estados vazio/preenchido do relatório de log de atividade; estados vazio/preenchido do relatório de grupos e integrantes; os campos de senha dos modais de entrar/criar/editar grupo têm `autocomplete="new-password"`, para o navegador nunca oferecer preencher com a senha salva do próprio site |
| `mobile_test.php` | 4 | `mobile_init` devolve o `init.js` inalterado; `mobile_course_view` devolve a página renderizada mais os dados de grupo/integrantes; aplicação de capability; a página renderizada liga a descrição do card do grupo ao campo sanitizado, nunca ao bruto |
| **Subtotal** | **14** | |

### API Pública (`tests/api/`)

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `group_info_test.php` | 6 | Sem grupo devolve null; os campos do resumo de um grupo; emblema padrão como fallback; busca de emblemas em lote para vários grupos; entrada vazia; um grupo nativo sem metadados do PlayerGroup é omitido |
| **Subtotal** | **6** | |

| **Total Geral** | **115** | |

```bash
vendor/bin/phpunit --testsuite mod_playergroup
```

**Cobertura de linha por classe (PHPUnit + Xdebug):**

| Classe | Cobertura de linha |
|--------|:-------------------:|
| `api\group_info` | 100% |
| `controller\export` | 100% |
| `output\mobile` | 100% |
| `output\renderer` | 100% |
| `local\group_lock` | 100% |
| `local\member_list` | 100% |
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
| **Geral** | **82%** |

As classes `event/*.php` não aparecem na lista — o Moodle só as carrega sob demanda quando o
evento correspondente de fato ocorre, então a instrumentação nunca chega a vê-las.

### Behat — Testes de Aceitação

| Arquivo de feature | Cenários | O que é coberto |
|--------------------|--------:|----------------|
| `create_group.feature` | 2 | Estudante cria um grupo aberto; criação bloqueada após já pertencer a um grupo |
| `join_group.feature` | 2 | Segundo estudante entra e vê o badge Meu Grupo; restrição de um grupo por estudante |
| `join_protected_group.feature` | 3 | Pressionar enter no campo de senha entra no grupo, exatamente como clicar em Salvar (a submissão nativa do formulário não derruba mais o parâmetro `id` da página nem quebra a tela); a senha errada mostra uma caixa de diálogo na própria página da atividade, nunca uma tela de erro do sistema; o campo de senha carrega `autocomplete="new-password"` |
| `view.feature` | 3 | Estudante vê o estado vazio e o botão Criar Grupo; visibilidade do link de relatório por perfil |
| `invite_colleagues.feature` | 1 | O criador de um grupo vê um colega matriculado listado no modal de convite |
| `view_members.feature` | 1 | Um estudante abre a lista de integrantes e vê os dois membros, com o líder marcado |
| **Total** | **12** | |

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags=@mod_playergroup --profile=chrome
```
