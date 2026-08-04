# ✨ Funcionalidades

* 👥 **Criação de Grupos:** Estudantes criam grupos com nome, descrição e emoji personalizado — sem precisar de intervenção do professor.
* 🔐 **Níveis de Privacidade:** Aberto, protegido (senha) e fechado (somente convite).
* 📨 **Sistema de Convites:** Convites entre colegas via notificação nativa do Moodle (sininho + e-mail para usuários offline); a lista de convidáveis respeita `moodle/course:viewparticipants` e é filtrada a estudantes ativamente matriculados, seguindo as mesmas regras de visibilidade do resto do curso.
* 👀 **Ver Integrantes:** O contador de membros de cada card de grupo é um botão que abre a lista somente-leitura dos integrantes daquele grupo, com o fundador marcado como líder — tanto na visão web quanto no app mobile.
* ⚙️ **Limites Configuráveis:** O professor define o mínimo e máximo de integrantes por grupo.
* 🗂️ **Agrupamento Automático:** Um agrupamento do Moodle é criado automaticamente — sem configuração manual. Excluir a atividade só remove os grupos e o agrupamento que ela mesma criou; se o professor apontou para um agrupamento pré-existente, esse agrupamento e os grupos que não pertencem a esta atividade permanecem intactos.
* 🔒 **Seguro contra Concorrência:** Criar um grupo, entrar nele ou aceitar um convite é serializado por instância da atividade, então duas requisições quase simultâneas (um duplo clique, ou dois dispositivos) nunca colocam o mesmo estudante em dois grupos ao mesmo tempo nem ultrapassam o limite de integrantes.
* 🏆 **Integração com Notas:** Nota atribuída automaticamente quando o estudante entra ou cria um grupo; permanente mesmo que ele saia depois.
* ✅ **Conclusão de Atividade:** Regra personalizada — o estudante deve entrar ou criar um grupo.
* 📊 **Relatórios do Professor:**
  * **Log de Atividade:** Visão de auditoria dos últimos 200 eventos (grupo criado, entrou, saiu, convite aceito), com exportação em CSV e Excel.
  * **Grupos e Integrantes:** Uma linha por integrante — grupo, privacidade, papel (líder/integrante) e nome — para ver a composição de toda a turma de uma vez, também exportável em CSV e Excel.
* 🔗 **API de Grupos:** Integração completa com os grupos e agrupamentos nativos do Moodle, além de uma API pública somente-leitura (`mod_playergroup\api\group_info`) que outros plugins podem consultar — o resumo do grupo de um estudante, ou os emblemas de vários grupos numa única chamada em lote.
* 📱 **App Mobile:** Suporte nativo no app oficial do Moodle — criar, entrar, sair, convidar, ver integrantes e gerenciar seu grupo pelo celular.
