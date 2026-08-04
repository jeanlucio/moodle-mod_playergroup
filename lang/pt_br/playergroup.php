<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Brazilian Portuguese strings for the PlayerGroup module.
 *
 * @package    mod_playergroup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// phpcs:disable moodle.Files.LineLength

$string['acceptinvite'] = 'Aceitar';
$string['activityclosed'] = 'Esta atividade não está mais aceitando formação de grupos.';
$string['activityclosedmsg'] = 'Esta atividade foi encerrada. A formação de grupos não é mais permitida.';
$string['activitylog'] = 'Registro de Atividade';
$string['activitylog_empty'] = 'Nenhuma atividade registrada ainda.';
$string['activitynotopen'] = 'Esta atividade ainda não está aberta para formação de grupos.';
$string['activityopensfrom'] = 'Esta atividade será aberta para formação de grupos em {$a}.';
$string['alreadyingroup'] = 'Você já pertence a um grupo nesta atividade.';
$string['availability'] = 'Disponibilidade';
$string['back'] = 'Voltar';
$string['canleave'] = 'Permitir que os estudantes saiam do grupo';
$string['cannotleavegroup'] = 'O professor desabilitou a opção de sair do grupo.';
$string['completionjoingroup'] = 'O estudante deve entrar ou criar um grupo';
$string['completionjoingroup_desc'] = 'Deve pertencer a um grupo para concluir';
$string['completionjoingroupgroup'] = 'Exigir afiliação a um grupo';
$string['creategroup'] = 'Criar Grupo';
$string['defaultgroupingname'] = 'Grupos de';
$string['deleteemptygroups'] = 'Excluir grupos vazios automaticamente';
$string['deleteemptygroups_help'] = 'Quando habilitado, o grupo será excluído automaticamente ao ser esvaziado. Todos os convites pendentes para esse grupo também serão cancelados. Esta opção só se aplica quando os estudantes têm permissão para sair do grupo.';
$string['deletegroups'] = 'Excluir grupos e agrupamento ao excluir a atividade';
$string['deletegroupswarning'] = 'Atenção: se marcado, ao excluir esta atividade todos os grupos e o agrupamento serão removidos permanentemente. Para excluir a atividade mas manter os grupos, desmarque esta opção antes de excluir.';
$string['description'] = 'Descrição';
$string['editgroup'] = 'Editar Grupo';
$string['editgroup_named'] = 'Editar grupo {$a}';
$string['event_group_created'] = 'Grupo criado';
$string['event_group_deleted'] = 'Grupo excluído automaticamente';
$string['event_invite_accepted'] = 'Convite aceito';
$string['event_member_joined'] = 'Membro ingressou no grupo';
$string['event_member_left'] = 'Membro saiu do grupo';
$string['export_csv'] = 'Exportar para CSV';
$string['export_excel'] = 'Exportar para Excel';
$string['gamerules'] = 'Regras do Jogo e Equipes';
$string['grade'] = 'Recompensa de fundação (nota)';
$string['gradeautonotice'] = 'A avaliação é automática: o estudante recebe os pontos configurados assim que entra ou cria um grupo. A nota é permanente — não é removida se o estudante sair do grupo depois.';
$string['groupbadge'] = 'Brasão / Emoji';
$string['groupclosed'] = 'Fechado (somente convite)';
$string['groupcreated'] = 'Seu grupo foi criado com sucesso!';
$string['groupfull'] = 'Cheio';
$string['groupingdescription'] = 'Agrupamento gerado automaticamente pelo PlayerGroup para a atividade: {$a}';
$string['groupingmode'] = 'Agrupamento';
$string['groupingmode_existing'] = 'Usar agrupamento existente';
$string['groupingmode_new'] = 'Criar novo agrupamento';
$string['groupingmode_none'] = 'Sem agrupamento';
$string['groupingname'] = 'Nome do agrupamento';
$string['groupisfull'] = 'Este grupo já atingiu o máximo de membros.';
$string['groupjoined'] = 'Você entrou no grupo com sucesso!';
$string['groupleft'] = 'Você saiu do grupo.';
$string['grouplockbusy'] = 'Outra solicitação para este grupo já está sendo processada. Tente novamente em instantes.';
$string['groupmembers_named'] = 'Integrantes de {$a}';
$string['groupname'] = 'Nome do grupo';
$string['groupopen'] = 'Aberto';
$string['grouppassword'] = 'Senha do grupo';
$string['groupprotected'] = 'Protegido (senha)';
$string['groups_title'] = 'Grupos da Atividade';
$string['groupsreport'] = 'Grupos e Integrantes';
$string['groupsreport_empty'] = 'Nenhum grupo foi criado nesta atividade ainda.';
$string['groupupdated'] = 'Seu grupo foi atualizado com sucesso!';
$string['invitealreadyhandled'] = 'Este convite já foi respondido.';
$string['invitealreadysent'] = 'Já existe um convite pendente para este estudante.';
$string['invitecolleagues'] = 'Convidar Colegas';
$string['invitedby'] = 'Por: {$a}';
$string['invitemessage'] = 'Você foi convidado(a) para entrar no grupo \'{$a->group}\' por {$a->sender}.';
$string['invitepending'] = 'Convite para grupo';
$string['inviterejected'] = 'Convite recusado.';
$string['invitesent'] = 'Convite enviado!';
$string['joinfailed'] = 'Não foi possível entrar no grupo. Você pode não estar matriculado(a) neste curso.';
$string['joingroup'] = 'Entrar no Grupo';
$string['joingroup_named'] = 'Entrar no grupo {$a}';
$string['leader'] = 'Líder';
$string['leadernamed'] = 'Líder: {$a}';
$string['leavegroup'] = 'Sair do Grupo';
$string['leavegroup_named'] = 'Sair do grupo {$a}';
$string['leavegroupconfirm'] = 'Tem certeza que deseja sair deste grupo?';
$string['maxmembers'] = 'Máximo de membros por grupo';
$string['member'] = 'Integrante';
$string['messageprovider:invite'] = 'Convite para grupo';
$string['minmembers'] = 'Mínimo de membros por grupo';
$string['mobile_back'] = 'Voltar';
$string['mobile_cancel'] = 'Cancelar';
$string['mobile_creategroup'] = 'Criar grupo';
$string['mobile_decline'] = 'Recusar';
$string['mobile_editgroup'] = 'Editar grupo';
$string['mobile_enterpassword'] = 'Digite a senha do grupo';
$string['mobile_error'] = 'Ocorreu um erro. Tente novamente.';
$string['mobile_groupname_required'] = 'O nome do grupo é obrigatório.';
$string['mobile_inviteaccept'] = 'Aceitar';
$string['mobile_invitedecline'] = 'Recusar';
$string['mobile_inviteusers'] = 'Convidar membros';
$string['mobile_joinopen'] = 'Entrar';
$string['mobile_joinprotected'] = 'Entrar (senha necessária)';
$string['mobile_leaveconfirm'] = 'Tem certeza que deseja sair deste grupo?';
$string['mobile_nogroups'] = 'Nenhum grupo ainda. Seja o primeiro a criar!';
$string['mobile_noinvites'] = 'Nenhum convite pendente.';
$string['mobile_passwordkeep'] = 'Deixe em branco para manter a senha atual';
$string['mobile_privacyclosed'] = 'Fechado (somente convite)';
$string['mobile_privacyopen'] = 'Aberto';
$string['mobile_privacyprotected'] = 'Protegido (senha)';
$string['mobile_save'] = 'Salvar';
$string['modulename'] = 'PlayerGroup';
$string['modulename_help'] = 'O PlayerGroup permite que os estudantes criem grupos de forma autônoma e convidem colegas. Ele se integra com ecossistemas de gamificação, facilitando dinâmicas de equipe e colaboração.';
$string['modulenameplural'] = 'PlayerGroups';
$string['name'] = 'Nome da atividade';
$string['nocolleagueswithoutgroup'] = 'Todos os estudantes matriculados já pertencem a um grupo.';
$string['nogroups'] = 'Nenhum grupo foi criado ainda. Seja o primeiro!';
$string['nogroupyet'] = 'Você ainda não faz parte de nenhum grupo. Crie um novo grupo ou entre em um existente.';
$string['notingroup'] = 'Você não é membro de nenhum grupo nesta atividade.';
$string['onegrouponly'] = 'Você pode participar de apenas um grupo nesta atividade.';
$string['passwordkeepblank'] = 'Deixe em branco para manter a senha atual';
$string['playergroup:addinstance'] = 'Adicionar uma nova atividade PlayerGroup';
$string['playergroup:creategroup'] = 'Criar novos grupos';
$string['playergroup:manage'] = 'Gerenciar atividade PlayerGroup';
$string['playergroup:manageinvites'] = 'Enviar e gerenciar convites de grupo';
$string['playergroup:view'] = 'Visualizar conteúdo do PlayerGroup';
$string['pluginadministration'] = 'Administração do PlayerGroup';
$string['pluginname'] = 'PlayerGroup';
$string['privacy'] = 'Privacidade';
$string['privacy:metadata:creatorid'] = 'O ID do usuário que criou o grupo.';
$string['privacy:metadata:playergroup_invites'] = 'Armazena convites de grupo enviados e recebidos pelos estudantes.';
$string['privacy:metadata:playergroup_meta'] = 'Armazena metadados dos grupos criados pelos estudantes nesta atividade.';
$string['privacy:metadata:receiverid'] = 'O ID do usuário que recebeu o convite para o grupo.';
$string['privacy:metadata:senderid'] = 'O ID do usuário que enviou o convite para o grupo.';
$string['privacy:metadata:timecreated'] = 'O momento em que este registro foi criado.';
$string['receivedinvites'] = 'Convites Pendentes';
$string['rejectinvite'] = 'Recusar';
$string['report_action'] = 'Ação';
$string['report_date'] = 'Data';
$string['report_group'] = 'Grupo';
$string['report_name'] = 'Nome';
$string['report_role'] = 'Papel';
$string['report_user'] = 'Usuário';
$string['sendinvite'] = 'Convidar';
$string['showonlyavailable'] = 'Mostrar apenas grupos disponíveis';
$string['tab_groups'] = 'Grupos';
$string['tab_mygroup'] = 'Meu Grupo';
$string['timeclose'] = 'Aberto até';
$string['timeopen'] = 'Aberto de';
$string['usernotenrolled'] = 'Esse estudante não está matriculado neste curso.';
$string['viewmembers_named'] = 'Ver integrantes de {$a}';
$string['viewreport'] = 'Ver Relatório';
$string['wrongpassword'] = 'Senha incorreta.';
