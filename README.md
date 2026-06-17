# PlayerGroup — Moodle Activity Module

[![Moodle Plugin CI](https://github.com/jeanlucio/moodle-mod_playergroup/actions/workflows/ci.yml/badge.svg)](https://github.com/jeanlucio/moodle-mod_playergroup/actions/workflows/ci.yml)
![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange?style=flat-square&logo=moodle&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv3-blue?style=flat-square)
![Status](https://img.shields.io/badge/Status-Stable-green?style=flat-square)
[![PlayerGames Ecosystem](https://img.shields.io/badge/PlayerGames-Ecosystem-6f42c1?style=flat-square&logo=gamepad&logoColor=white)](https://moodle.org/plugins/browse.php?list=contributor&id=3970322)
![Role](https://img.shields.io/badge/Role-Team_Formation-0dcaf0?style=flat-square)

[English](#english) | [Português](#português)

---

## English

**PlayerGroup** lets students autonomously form their own groups directly from the activity page — no teacher intervention needed. It replaces manual group assignment with a self-service experience that works for any course format.

---

### ✨ Features

* 👥 **Group Creation:** Students create groups with a custom name, description, and emoji badge.
* 🔐 **Privacy Levels:** Open, protected (password), and closed (invite only).
* 📨 **Invite System:** Peer invitations via Moodle's native notification (bell + email for offline users).
* ⚙️ **Configurable Limits:** Teachers set minimum and maximum members per group.
* 🗂️ **Automatic Grouping:** Moodle grouping created automatically — no manual setup required.
* 🏆 **Gradebook Integration:** Grade awarded automatically when a student joins or creates a group; permanent even if the student later leaves.
* ✅ **Activity Completion:** Custom rule — student must join or create a group.
* 📊 **Teacher Report:** Audit log view showing the last 200 activity events, with CSV and Excel export of the full log.
* 🔗 **Groups API:** Full integration with Moodle's native groups and groupings.
* 📱 **Mobile App:** Native support in the official Moodle app — create, join, leave, invite, and manage your group on the go.

---

### 🎓 Educational Purpose

PlayerGroup is designed to:

* Foster collaborative learning through self-organised teams
* Reduce teacher workload in group formation
* Reward group membership as a gamification milestone
* Integrate naturally into gamified course flows

Suitable for:

* Project-based learning where students choose their own teams
* Labs and workshops with limited seats per group
* Gamified courses using the PlayerHUD ecosystem
* Any context where peer team selection matters

---

### 🕹️ PlayerGames Ecosystem

PlayerGroup is part of the **PlayerGames** gamification ecosystem. Together, these plugins transform Moodle into an immersive experience:

* **PlayerHUD Block:** The core gamification component — XP, levels, inventory, ranking, RPG classes, and story engine. Displays the student's group as part of their in-game profile via PlayerGroup's public API.
  👉 https://github.com/jeanlucio/moodle-block_playerhud

* **PlayerHUD Filter:** Enables item drops via shortcodes inside course content.
  👉 https://github.com/jeanlucio/moodle-filter_playerhud

* **PlayerHUD Availability Restriction:** Restricts access to course activities based on the student's current level or collected items.
  👉 https://github.com/jeanlucio/moodle-availability_playerhud

---

### 📦 Requirements

| Component | Version |
|-----------|---------|
| Moodle    | 4.5+    |
| PHP       | 8.2+    |

---

### 🛠️ Installation

1. Download or clone this repository into `mod/playergroup` inside your Moodle root.
2. Visit **Site administration > Notifications** to run the database upgrade.
3. Add a **PlayerGroup** activity to any course.

```bash
git clone git@github.com:jeanlucio/moodle-mod_playergroup.git mod/playergroup
```

---

### 📖 Usage

1. Add a **PlayerGroup** activity to your course.
2. Configure the activity settings:
   * **Minimum / Maximum members** per group
   * **Allow students to leave** their group
   * **Delete groups on activity deletion** — if checked, all groups and the grouping are permanently removed when the activity is deleted
   * **Foundation reward** — grade automatically awarded to every student who joins or creates a group
3. Students access the activity and create or join groups — open, password-protected, or invite-only.
4. The activity completes and the grade is awarded automatically when a student joins or creates a group.

---

### 🌱 Demo Environment (Quick Start)

Two CLI seed scripts create a fully configured demo course in minutes — useful for local development or evaluating the full feature set without manual setup.

| Script | Language |
|--------|----------|
| `cli/seed.php` | English |
| `cli/seed_pt_br.php` | Brazilian Portuguese |

**What is created:**

* 1 demo course with a PlayerGroup activity (open for group formation)
* 1 teacher + 30 students
* 6 groups covering all privacy levels: 4 open, 1 password-protected, 1 invite-only
* 3 students without a group, each with pending invitations from existing groups
* Audit log events spread over the past 7 days — teacher report is ready to browse immediately

**Usage:**

```bash
# First run
php mod/playergroup/cli/seed.php --password=MyDevPass1!

# Wipe and recreate from scratch
php mod/playergroup/cli/seed.php --password=MyDevPass1! --reset
```

The `--password` flag is **required** and sets the login password for all seed accounts. The script refuses to run on non-development URLs (`localhost`, `127.0.0.1`, `*.local`, `*.test`); pass `--force` to bypass this guard on a development site that uses a public domain. The scripts work on both the classic (4.x) and `public/` (5.x) directory layouts.

> Via Docker Compose: `docker compose exec <webserver-service> php mod/playergroup/cli/seed.php --password=MyDevPass1!`

The script prints a full summary on completion: course URL, activity URL, teacher credentials, group list, and student passwords.

---

### 🧪 Automated Tests

PlayerGroup ships with a PHPUnit suite covering all business logic and a Behat suite for browser acceptance. Every CI push runs against the full matrix (Moodle 4.5 → 5.x, PostgreSQL & MariaDB).

#### PHPUnit — Unit & Integration Tests

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `backup/restore_test.php` | 3 | Backup/restore round-trip for content-only and user-data modes; original course unaffected |
| `completion/custom_completion_test.php` | 2 | Custom completion rule `completionjoingroup`: incomplete without a group, complete once the student belongs to a group registered for the activity |
| `external/accept_invite_test.php` | 5 | Accept invite: success, completion tracking (manual/auto), wrong-user and already-handled rejections |
| `external/create_group_test.php` | 10 | Create group: all privacy levels, password hashing, creator membership, capability enforcement, duplicate and invalid-cmid guards, completion tracking |
| `external/join_group_test.php` | 9 | Join group: success, completion tracking, already-in-group and closed-group rejections, protected-group joins (correct/wrong password), invited user joining via password, and resolution of pending invites on join |
| `external/leave_group_test.php` | 8 | Leave group: success, canleave guard, not-in-group guard, empty-group auto-deletion, leadership transfer, pending invite cancellation |
| `external/send_invite_test.php` | 2 | Send invite: pending invite creation, and re-inviting a student after they join and leave a group |
| `lib_test.php` | 7 | add/delete_instance lifecycle, supported features |
| `playergroup_grade_test.php` | 4 | Grade award on join, bulk award, grade persistence after leaving, no grade when disabled |
| `privacy/provider_test.php` | 11 | GDPR: metadata declarations, context discovery, data export (creator/receiver), bulk and targeted deletion |
| **Total** | **61** | |

```bash
vendor/bin/phpunit --testsuite mod_playergroup
```

#### Behat — Acceptance Tests

| Feature file | Scenarios | What is covered |
|--------------|----------:|----------------|
| `create_group.feature` | 2 | Student creates an open group; creation blocked after already joining one |
| `join_group.feature` | 2 | Second student joins and sees My Group badge; one-group-per-student enforcement |
| `view.feature` | 3 | Student sees empty state and Create Group button; report link visibility by role |
| **Total** | **7** | |

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags=@mod_playergroup --profile=chrome
```

---

### 🔐 Security & Compliance

* Capability-based access control (`mod/playergroup:creategroup`, `mod/playergroup:view`)
* `require_sesskey()` on all state-changing operations
* Moodle External API compliant (AJAX via `core/ajax`)
* Full Privacy API implementation — data export and deletion supported
* Backup and restore support

---

## 📄 License / Licença

This project is licensed under the **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

---

## Português

O **PlayerGroup** permite que os alunos formem seus próprios grupos diretamente na página da atividade — sem intervenção do professor. Ele substitui a formação manual de grupos por uma experiência de autoatendimento que funciona em qualquer formato de curso.

---

### ✨ Funcionalidades

* 👥 **Criação de Grupos:** Os alunos criam grupos com nome, descrição e emoji personalizado.
* 🔐 **Níveis de Privacidade:** Aberto, protegido (senha) e fechado (somente convite).
* 📨 **Sistema de Convites:** Convites entre colegas via notificação nativa do Moodle (sininho + e-mail para usuários offline).
* ⚙️ **Limites Configuráveis:** O professor define o mínimo e máximo de membros por grupo.
* 🗂️ **Agrupamento Automático:** O agrupamento do Moodle é criado automaticamente — sem configuração manual.
* 🏆 **Integração com Notas:** Nota atribuída automaticamente quando o aluno entra ou cria um grupo; permanente mesmo que o aluno saia depois.
* ✅ **Conclusão de Atividade:** Regra personalizada — o aluno deve entrar ou criar um grupo.
* 📊 **Relatório do Professor:** Visualização do log de auditoria com os últimos 200 eventos da atividade, com exportação do log completo em CSV e Excel.
* 🔗 **API de Grupos:** Integração completa com os grupos e agrupamentos nativos do Moodle.
* 📱 **App Mobile:** Suporte nativo no app oficial do Moodle — criar, entrar, sair, convidar e gerenciar seu grupo pelo celular.

---

### 🎓 Finalidade Educacional

O PlayerGroup foi projetado para:

* Estimular o aprendizado colaborativo por meio de equipes autoformadas
* Reduzir o trabalho do professor na formação de grupos
* Recompensar a participação em grupos como um marco de gamificação
* Integrar-se naturalmente a fluxos de cursos gamificados

Indicado para:

* Aprendizagem baseada em projetos onde os alunos escolhem suas equipes
* Laboratórios e workshops com vagas limitadas por grupo
* Cursos gamificados que usam o ecossistema PlayerHUD
* Qualquer contexto em que a seleção de equipes pelos próprios alunos seja relevante

---

### 🕹️ Ecossistema PlayerGames

O PlayerGroup faz parte do ecossistema de gamificação **PlayerGames**. Juntos, esses plugins transformam o Moodle em uma experiência imersiva:

* **Bloco PlayerHUD:** O componente central de gamificação — XP, níveis, inventário, ranking, classes RPG e motor de história. Exibe o grupo do aluno como parte do seu perfil no jogo via a API pública do PlayerGroup.
  👉 https://github.com/jeanlucio/moodle-block_playerhud

* **Filtro PlayerHUD:** Permite inserir drops de itens por meio de shortcodes no conteúdo do curso.
  👉 https://github.com/jeanlucio/moodle-filter_playerhud

* **Restrição de Acesso PlayerHUD:** Restringe o acesso a atividades com base no nível atual do aluno ou nos itens coletados.
  👉 https://github.com/jeanlucio/moodle-availability_playerhud

---

### 📦 Requisitos

| Componente | Versão  |
|------------|---------|
| Moodle     | 4.5+    |
| PHP        | 8.2+    |

---

### 🛠️ Instalação

1. Baixe o arquivo `.zip` ou clone este repositório na pasta `mod/playergroup` do seu Moodle.
2. Acesse **Administração do site > Notificações** para executar a atualização do banco de dados.
3. Adicione uma atividade **PlayerGroup** a qualquer curso.

```bash
git clone git@github.com:jeanlucio/moodle-mod_playergroup.git mod/playergroup
```

---

### 📖 Como Usar

1. Adicione uma atividade **PlayerGroup** ao seu curso.
2. Configure as opções da atividade:
   * **Mínimo / Máximo de membros** por grupo
   * **Permitir que alunos saiam** do grupo
   * **Excluir grupos ao deletar a atividade** — se marcado, todos os grupos e o agrupamento são removidos permanentemente ao excluir a atividade
   * **Recompensa de fundação** — nota atribuída automaticamente a todo aluno que entra ou cria um grupo
3. Os alunos acessam a atividade e criam ou entram em grupos — aberto, protegido por senha ou somente por convite.
4. A atividade é concluída e a nota é atribuída automaticamente quando o aluno entra ou cria um grupo.

---

### 🌱 Ambiente de Demonstração (Quick Start)

Dois scripts CLI de seed criam um curso de demonstração completamente configurado em minutos — útil para desenvolvimento local ou para avaliar o conjunto completo de funcionalidades sem configuração manual.

| Script | Idioma |
|--------|--------|
| `cli/seed.php` | Inglês |
| `cli/seed_pt_br.php` | Português (Brasil) |

**O que é criado:**

* 1 curso demo com uma atividade PlayerGroup configurada (aberta para formação de grupos)
* 1 professor + 30 alunos
* 6 grupos com todos os níveis de privacidade: 4 abertos, 1 protegido por senha, 1 somente por convite
* 3 alunos sem grupo, cada um com convites pendentes de grupos existentes
* Eventos de log de auditoria distribuídos nos últimos 7 dias — o relatório do professor já está pronto para navegar

**Uso:**

```bash
# Primeira execução
php mod/playergroup/cli/seed_pt_br.php --password=MinhaDevSenha1!

# Apagar tudo e recriar do zero
php mod/playergroup/cli/seed_pt_br.php --password=MinhaDevSenha1! --reset
```

O parâmetro `--password` é **obrigatório** e define a senha de login de todas as contas seed. O script recusa executar em URLs que não sejam de desenvolvimento (`localhost`, `127.0.0.1`, `*.local`, `*.test`); use `--force` para ignorar essa verificação em um site de desenvolvimento com domínio público. Os scripts funcionam tanto no layout clássico (4.x) quanto no `public/` (5.x).

> Via Docker Compose: `docker compose exec <servico-webserver> php mod/playergroup/cli/seed_pt_br.php --password=MinhaDevSenha1!`

Ao concluir, o script exibe um resumo completo: URL do curso, URL da atividade, credenciais do professor, lista de grupos e senha dos alunos.

---

### 🧪 Testes Automatizados

O PlayerGroup inclui uma suíte PHPUnit que cobre toda a lógica de negócio e uma suíte Behat para aceitação em navegador. Todo push de CI executa a matriz completa (Moodle 4.5 → 5.x, PostgreSQL e MariaDB).

#### PHPUnit — Testes Unitários e de Integração

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `backup/restore_test.php` | 3 | Round-trip de backup/restore em modo conteúdo e com dados de usuário; curso original não afetado |
| `completion/custom_completion_test.php` | 2 | Regra de conclusão customizada `completionjoingroup`: incompleta sem grupo, completa quando o estudante pertence a um grupo registrado na atividade |
| `external/accept_invite_test.php` | 5 | Aceitar convite: sucesso, conclusão de atividade (manual/auto), rejeição por usuário errado e convite já respondido |
| `external/create_group_test.php` | 10 | Criar grupo: todos os níveis de privacidade, hash de senha, criador como membro, capability enforcement, guards contra duplicata e cmid inválido, conclusão de atividade |
| `external/join_group_test.php` | 9 | Entrar no grupo: sucesso, conclusão de atividade, rejeição por já estar em grupo e por grupo fechado, entrada em grupo protegido (senha correta/errada), entrada por senha de um aluno convidado e resolução dos convites pendentes ao entrar |
| `external/leave_group_test.php` | 8 | Sair do grupo: sucesso, guard canleave, guard não-é-membro, auto-exclusão de grupo vazio, transferência de liderança, cancelamento de convites pendentes |
| `external/send_invite_test.php` | 2 | Enviar convite: criação de convite pendente e reconvite de um aluno após ele entrar e sair de um grupo |
| `lib_test.php` | 7 | Ciclo de vida add/delete_instance, funcionalidades suportadas |
| `playergroup_grade_test.php` | 4 | Atribuição de nota ao entrar, atribuição em lote, persistência da nota após sair, sem nota quando desabilitado |
| `privacy/provider_test.php` | 11 | LGPD: declaração de metadados, descoberta de contextos, exportação de dados (criador/destinatário), exclusão em lote e individual |
| **Total** | **61** | |

```bash
vendor/bin/phpunit --testsuite mod_playergroup
```

#### Behat — Testes de Aceitação

| Arquivo de feature | Cenários | O que é coberto |
|-------------------|--------:|----------------|
| `create_group.feature` | 2 | Aluno cria um grupo aberto; criação bloqueada após já pertencer a um grupo |
| `join_group.feature` | 2 | Segundo aluno entra e vê o badge Meu Grupo; restrição de um grupo por aluno |
| `view.feature` | 3 | Aluno vê o estado vazio e o botão Criar Grupo; visibilidade do link de relatório por perfil |
| **Total** | **7** | |

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --tags=@mod_playergroup --profile=chrome
```

---

### 🔐 Segurança e Conformidade

* Controle de acesso baseado em capabilities (`mod/playergroup:creategroup`, `mod/playergroup:view`)
* Proteção com `require_sesskey()` em todas as operações de escrita
* Compatível com a API externa do Moodle (AJAX via `core/ajax`)
* Implementação completa da Privacy API — exportação e exclusão de dados suportadas
* Suporte a backup e restauração

---

## 📄 Licença

Este projeto é licenciado sob a **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio
