# 🔐 Segurança e Conformidade

* Controle de acesso baseado em capabilities (`mod/playergroup:creategroup`, `mod/playergroup:view`, `mod/playergroup:manage`, `mod/playergroup:manageinvites`)
* Toda operação de grupo é isolada por instância da atividade — um ID de grupo ou de convite de outra atividade, ou de outro curso, é sempre rejeitado em vez de aceito
* Proteção com `require_sesskey()` em todas as operações que alteram estado; as chamadas AJAX são validadas pelo dispatcher `core/ajax` do Moodle
* Criar grupo, entrar em grupo e aceitar convite são serializados por instância da atividade via `\core\lock\lock_config`, fechando uma corrida de verificação-e-ação que de outra forma poderia colocar um estudante em dois grupos ou ultrapassar o limite de integrantes com duas requisições concorrentes
* A lista de convidáveis só lista estudantes ativamente matriculados e respeita `moodle/course:viewparticipants`, então nunca vira uma rota alternativa para informação de participantes que o curso decidiu esconder dos estudantes
* Senhas de grupo são armazenadas com `password_hash()` e verificadas com `password_verify()`; o hash armazenado nunca é devolvido ao cliente
* Excluir a atividade só remove grupos e um agrupamento que ela mesma criou — nunca um agrupamento pré-existente (ou os outros grupos dele) que o professor tenha apontado a atividade a usar
* Compatível com a API externa do Moodle
* Implementação completa da Privacy API — exportação e exclusão de dados suportadas
* Suporte a backup e restauração, incluindo remapeamento seguro de IDs numa duplicação ou restauração de curso
