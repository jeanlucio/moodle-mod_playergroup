# 🧪 Testes Automatizados

O PlayerGroup inclui uma suíte PHPUnit cobrindo lógica de negócio, web services, relatórios e
exportações, o calendário, o renderer e a saída do app mobile, a API pública, upgrades, backup e
restore, e conformidade com a Privacy API, além de uma suíte Behat para aceitação em navegador.
Todo push de CI executa a matriz completa (Moodle 4.5 → 5.x, PostgreSQL e MariaDB).

### Núcleo (`tests/`, `tests/backup/`, `tests/completion/`, `tests/privacy/`)

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `lib_test.php` | 33 | Ciclo de vida da instância (`add`/`update`/`delete_instance`); funcionalidades suportadas e ícone próprio; excluir a atividade só remove os grupos e o agrupamento que ela registrou; o nome do agrupamento cabe na coluna; a data de conclusão esperada e as datas de abertura/encerramento chegam ao calendário e acompanham edições; a ação da linha do tempo (utilizável na janela, em espera antes dela, ausente depois, para quem já está em grupo ou em atividade oculta); a atividade duplicada ganha eventos próprios |
| `lib_grade_item_update_test.php` | 3 | A nota de aprovação chega ao `grade_item` do livro de notas; o reset limpa as notas e mantém o item; atividade sem nota não cria item |
| `playergroup_grade_test.php` | 7 | Nota ao entrar, concessão em lote, permanência após sair, nenhuma quando desativada, data de envio, e baixar o mínimo dá a nota a todos os grupos já formados com a data em que cada estudante se qualificou |
| `access_test.php` | 4 | As capabilities têm as máscaras de risco e os tipos certos: `manage` é marcada como dado pessoal e é de leitura, e as que alteram dados continuam de escrita |
| `db_declarations_test.php` | 10 | Todo serviço declarado em `db/services.php` tem classe e método, descreve parâmetros e retornos e está disponível ao AJAX e ao app; o handler mobile aponta para arquivos e strings reais; o provedor de mensagens e os observers estão completos |
| `mod_form_test.php` | 22 | Validação do formulário de configuração: limites de integrantes, encerramento depois da abertura, sem nota por escala, tamanhos e duplicidade de nomes; a regra de conclusão é lida, mantida e limpa corretamente; o seletor de agrupamento oferece HTML seguro e valores padrão |
| `observer_test.php` | 24 | Reações à página de Grupos e à matrícula: grupos vazios apagados ou mantidos; a liderança passa ao integrante mais antigo quando o líder é removido; um grupo esvaziado reabre e o próximo integrante o lidera; convites pendentes são resolvidos e o mínimo qualifica grupos para integrantes adicionados pela página de Grupos; exclusão do agrupamento |
| `upgrade_test.php` | 14 | O `xmldb_playergroup_upgrade()` real sobre dados de um site antigo: quem está em grupo ou tem nota mantém o que tinha, o passo pode rodar de novo, lotes maiores que um são gravados, um site vazio ou atual atualiza sem problema; o passo do calendário dá eventos às atividades existentes uma só vez |
| `completion/custom_completion_test.php` | 8 | Regra de conclusão `completionjoingroup`: segue a qualificação, não a participação atual; descrições, ordem e as regras listadas na página do curso |
| `backup/restore_test.php` | 14 | Ciclo de backup/restore com e sem dados de usuário; chaves remapeadas, linhas sem o grupo ou a pessoa descartadas; as datas de abertura/encerramento acompanham o novo início do curso; o restore traz um evento de cada tipo, e um backup anterior aos eventos também os recebe |
| `local/group_lock_test.php` | 3 | Lock obtido quando livre; `grouplockbusy` quando mantido por uma conexão de banco realmente diferente; o lock de outra atividade nunca é bloqueado |
| `privacy/provider_test.php` | 20 | Declaração de metadados (toda coluna declarada ou deixada de fora de propósito), descoberta de contextos e usuários, exportação e exclusão por usuário, por lista e por contexto, incluindo repassar o grupo de um líder |
| **Subtotal** | **162** | |

### Classes de domínio e eventos (`tests/local/`, `tests/event/`)

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `local/qualification_test.php` | 16 | Tamanho mínimo do grupo: todos se qualificam quando o grupo o atinge e mantêm após sair; baixar ou subir o mínimo; notas e datas; dar nota a uma turma inteira custa menos queries do que um a um |
| `local/grades_test.php` | 4 | Notas montadas para todos ou para uma lista de estudantes em uma query; quem nunca se qualificou fica de fora; atividade sem nota não tem nenhuma |
| `local/calendar_test.php` | 12 | Eventos das datas de abertura e encerramento: tipos conforme as datas definidas, nomes, atividades ocultas, atualizar mantém o mesmo evento, limpar uma data remove só o evento dela, refresh por registro, id, curso e site sem repetir, eventos repetidos limpos, evento de conclusão mantido, exclusão não deixa nenhum |
| `local/course_reset_test.php` | 10 | `reset_course_userdata()` real: conclusão, notas, grupos e desmatrícula limpam os dados certos; as datas e os eventos do calendário acompanham o novo início do curso |
| `local/activity_window_test.php` | 6 | A janela de disponibilidade: sem limite, bordas, um só limite, propriedades ausentes e códigos de erro |
| `local/activity_log_test.php` | 5 | O log de atividade: sem armazenamento de log, vazio, apenas eventos desta atividade do mais novo ao mais antigo, o limite e um marcador para usuários desconhecidos |
| `local/groups_report_test.php` | 5 | Linhas do relatório de grupos e integrantes: líder e integrantes, um grupo mantido sem integrantes continua com linha, ordem mista, sem grupos, nomes em texto puro |
| `local/member_list_test.php` | 5 | Líder primeiro, demais em ordem alfabética, lista sem líder, lista vazia e quem pode ver nomes |
| `local/text_test.php` | 5 | Nomes como texto puro: como digitados, entidades decodificadas, marcação removida, entrada vazia, escapados uma só vez |
| `event/events_test.php` | 4 | Nome, descrição, link e disparo de cada evento; os eventos de visualização e de listagem são somente leitura; os ids dos objetos são remapeados ao restaurar um log |
| **Subtotal** | **72** | |

### Web Services (`tests/external/`)

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `external/create_group_test.php` | 32 | Níveis de privacidade, hash da senha, participação do criador, capabilities, nomes duplicados (inclusive entre cursos), limites dos campos, conclusão; quem não está matriculado é informado do motivo e nada fica para trás; bloqueado enquanto outra requisição mantém o lock |
| `external/join_group_test.php` | 18 | Sucesso, conclusão, já em grupo, grupos fechados e cheios, senhas (a errada é uma `moodle_exception` simples), usuários convidados, convites pendentes resolvidos ao entrar, quem não está matriculado é recusado com o motivo; bloqueado enquanto outra requisição mantém o lock |
| `external/leave_group_test.php` | 14 | Sucesso, trava `canleave`, trava de quem não está em grupo, exclusão de grupo vazio, transferência de liderança, convites pendentes cancelados |
| `external/edit_group_test.php` | 26 | Edita nome, descrição, emblema e privacidade; só o líder que ainda está no grupo; senhas com hash e mantidas; `groupid` de outra atividade é recusado; regras de nome; fechar exige o direito de convidar; os erros dizem o motivo real |
| `external/accept_invite_test.php` | 16 | Sucesso, conclusão (manual e automática), usuário errado, já tratado, grupo cheio, destinatário já em grupo, datas da janela, capabilities; bloqueado enquanto outra requisição mantém o lock |
| `external/reject_invite_test.php` | 8 | Sucesso, usuário errado, já tratado, id inválido, datas da janela, capabilities e a estrutura declarada |
| `external/send_invite_test.php` | 20 | Convites pendentes, reconvidar após sair, quem pode convidar, grupo cheio, convidado que já tem grupo (o remetente é avisado), a notificação pertence ao curso da atividade, datas da janela |
| `external/get_activity_data_test.php` | 22 | O payload da web e do app: integrantes e flag de líder validados contra `execute_returns()`, nomes ocultos sem `viewparticipants`, aviso de integrantes faltando, selo "Cheio" no lugar do botão Entrar, nenhuma ação oferecida a quem não está matriculado |
| `external/view_playergroup_test.php` | 5 | Visualizar conclui a regra de visualização e dispara o evento; quem está fora do curso ou sem a capability é recusado; a estrutura declarada |
| **Subtotal** | **161** | |

### Relatórios e Exportações (`tests/controller/`)

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `controller/export_test.php` | 3 | A exportação CSV tem uma linha localizada por evento registrado; atividade sem eventos exporta só o cabeçalho |
| `controller/export_groups_test.php` | 5 | Uma linha por integrante com grupo, privacidade e papel; grupos fechados identificados; um grupo mantido sem integrantes continua com linha; nomes escritos como texto puro; sem grupos exporta só o cabeçalho |
| **Subtotal** | **8** | |

### Saída e App Mobile (`tests/output/`)

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `output/renderer_test.php` | 18 | Visão do estudante vazia e preenchida; o card do grupo escapa o nome uma só vez; o selo "Cheio" substitui o botão Entrar; o aviso de integrantes faltando; os dois relatórios; os campos de senha usam `autocomplete="new-password"` |
| `output/mobile_test.php` | 10 | `mobile_init`; `mobile_course_view` devolve a página com os dados de grupos e integrantes; verificação de capability; a descrição usa o campo sanitizado; o selo "Cheio" vem com o texto já traduzido |
| **Subtotal** | **28** | |

### API Pública (`tests/api/`)

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `api/group_info_test.php` | 6 | Sem grupo devolve nulo; resumo do grupo; emblema padrão; busca de emblemas em lote; entrada vazia; grupo sem metadados do PlayerGroup é omitido |
| **Subtotal** | **6** | |

| **Total Geral** | **437** | |

```bash
vendor/bin/phpunit --testsuite mod_playergroup
```

"Casos" conta os métodos de teste; os data providers expandem alguns deles, e o PHPUnit executa 455 testes no total.

**Cobertura de linhas** (`moodle-coverage`, PHPUnit + Xdebug, medida pelo que os testes executam): **98.9%** da lógica do plugin. 38 dos 45 arquivos com lógica estão em 100%, incluindo todas as classes de evento, `calendar`, `grades`, `participation` e `groups_report`; os demais:

| Arquivo | Cobertura de linhas |
|---------|:-------------------:|
| `classes/observer.php` | 94% |
| `classes/privacy/provider.php` | 96% |
| `classes/external/create_group.php` | 96% |
| `mod_form.php` | 97% |
| `classes/external/join_group.php` | 98% |
| `classes/local/qualification.php` | 98% |
| `lib.php` | 99% |

Os pontos de entrada (`view.php`, `index.php`, `export.php`) são exercitados pela suíte Behat, não pelo PHPUnit.

### Behat — Testes de Aceitação

| Arquivo de feature | Cenários | O que é coberto |
|--------------------|---------:|----------------|
| `create_group.feature` | 2 | O estudante cria um grupo aberto; a criação é bloqueada quando já pertence a um |
| `join_group.feature` | 2 | Um segundo estudante entra e vê o selo Meu Grupo; sem botões Entrar para quem já está em um grupo |
| `join_protected_group.feature` | 3 | Enter no campo de senha entra como Salvar; a senha errada mostra um diálogo na página, nunca um erro de sistema; o campo não oferece senhas salvas |
| `view.feature` | 5 | Botão Criar Grupo e estado vazio; link do relatório por papel; sem botão de criar para professor sem edição ou estudante que perdeu o direito |
| `view_members.feature` | 1 | Um estudante abre a lista de integrantes e vê os dois, com o líder marcado |
| `invite_colleagues.feature` | 1 | O criador do grupo vê um colega matriculado no modal de convite |
| `invite_flow.feature` | 2 | O colega aceita e entra no grupo, ou recusa e continua sem grupo |
| `minimum_members.feature` | 2 | O fundador é avisado de quantos integrantes faltam; o aviso acompanha o grupo e some no mínimo |
| `reports.feature` | 4 | O professor vê o log de atividade e o relatório de grupos e integrantes e chega aos dois pela atividade; um estudante que pede o log recebe a página normal |
| `index.feature` | 2 | O índice do curso informa que não há atividades, ou as lista |
| **Total** | **24** | |

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags=@mod_playergroup --profile=chrome
```
