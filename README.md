# PlayerGroup — Moodle Activity Module

[![Moodle Plugin CI](https://github.com/jeanlucio/moodle-mod_playergroup/actions/workflows/ci.yml/badge.svg)](https://github.com/jeanlucio/moodle-mod_playergroup/actions/workflows/ci.yml)
![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange?style=flat-square&logo=moodle&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv3-blue?style=flat-square)
![Status](https://img.shields.io/badge/Status-Stable-green?style=flat-square)
[![PlayerHUD Ecosystem](https://img.shields.io/badge/PlayerHUD-Ecosystem-6f42c1?style=flat-square&logo=gamepad&logoColor=white)](https://github.com/jeanlucio/moodle-block_playerhud)
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
* 📊 **Teacher Report:** Audit log view showing the last 200 activity events.
* 🔗 **Groups API:** Full integration with Moodle's native groups and groupings.

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

### 🔗 PlayerHUD Ecosystem

PlayerGroup is part of the PlayerHUD gamification ecosystem:

* **PlayerHUD Block (Optional):** Displays the student's group as part of their in-game profile. PlayerGroup exposes a public PHP API (`mod_playergroup\api\group_info`) consumed by PlayerHUD.
  👉 https://github.com/jeanlucio/moodle-block_playerhud

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

### 🧪 Development & Testing

Two CLI seed scripts are available to populate a demo environment for manual testing:

| Script | Language |
|--------|----------|
| `cli/seed.php` | English |
| `cli/seed_pt_br.php` | Brazilian Portuguese |

Each script creates a demo course with 30 students distributed across 6 groups (open, password-protected, and invite-only), 3 students without a group, and pending invitations.

**Requirements:**
- Must be run on a development site — `$CFG->wwwroot` must contain `localhost`, `127.0.0.1`, `.local`, or `.test`. The script aborts otherwise.
- `--password=<value>` is required. There is no default to prevent accidental account creation with a known credential on non-development sites.

```bash
# First run
php mod/playergroup/cli/seed.php --password=MyDevPass1!

# Wipe and recreate from scratch
php mod/playergroup/cli/seed.php --password=MyDevPass1! --reset
```

The script prints a full summary on completion: course URL, activity URL, teacher credentials, group list, and student passwords.

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
* 📊 **Relatório do Professor:** Visualização do log de auditoria com os últimos 200 eventos da atividade.
* 🔗 **API de Grupos:** Integração completa com os grupos e agrupamentos nativos do Moodle.

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

### 🔗 Ecossistema PlayerHUD

O PlayerGroup faz parte do ecossistema de gamificação PlayerHUD:

* **Bloco PlayerHUD (Opcional):** Exibe o grupo do aluno como parte do seu perfil no jogo. O PlayerGroup oferece uma API PHP pública (`mod_playergroup\api\group_info`) consumida pelo PlayerHUD.
  👉 https://github.com/jeanlucio/moodle-block_playerhud

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

### 🧪 Desenvolvimento e Testes

Dois scripts CLI de seed estão disponíveis para popular um ambiente de demonstração para testes manuais:

| Script | Idioma |
|--------|--------|
| `cli/seed.php` | Inglês |
| `cli/seed_pt_br.php` | Português (Brasil) |

Cada script cria um curso demo com 30 alunos distribuídos em 6 grupos (aberto, protegido por senha e somente por convite), 3 alunos sem grupo e convites pendentes.

**Requisitos:**
- Deve ser executado em um site de desenvolvimento — `$CFG->wwwroot` deve conter `localhost`, `127.0.0.1`, `.local` ou `.test`. O script aborta caso contrário.
- `--password=<valor>` é obrigatório. Não há valor padrão para evitar a criação acidental de contas com credencial conhecida em ambientes que não sejam de desenvolvimento.

```bash
# Primeira execução
php mod/playergroup/cli/seed_pt_br.php --password=MinhaDevSenha1!

# Apagar tudo e recriar do zero
php mod/playergroup/cli/seed_pt_br.php --password=MinhaDevSenha1! --reset
```

Ao concluir, o script exibe um resumo completo: URL do curso, URL da atividade, credenciais do professor, lista de grupos e senha dos alunos.

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
