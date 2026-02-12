<?php

return [
    'auth' => [
        'success' => 'Autenticado com sucesso via Firebase!',
        'unauthorized' => 'Não autorizado: Token não fornecido',
        'invalid_token' => 'Não autorizado: Token inválido',
    ],
    'user' => [
        'registered' => 'Usuário registrado com sucesso',
        'updated' => 'Usuário atualizado com sucesso',
    ],
    'league' => [
        'created' => 'Liga criada com sucesso',
        'updated' => 'Liga atualizada com sucesso',
        'joined' => 'Entrou na liga com sucesso',
        'not_found' => 'Liga com código :code não encontrada.',
        'closed' => 'Esta liga está fechada e não aceita novos membros.',
        'already_member' => 'Você já é membro desta liga.',
        'not_member' => 'Você não é membro desta liga.',
        'target_not_member' => 'O usuário alvo não é membro desta liga.',
        'owner_only' => 'Apenas o dono pode editar esta liga.',
    ],
    'prediction' => [
        'saved' => 'Palpite salvo com sucesso',
        'match_started' => 'A partida já começou. Palpites encerrados.',
        'invalid_match' => 'Esta partida não pertence à competição da liga.',
        'not_found' => 'Palpite não encontrado.',
        'unauthorized' => 'Você não tem permissão para visualizar este palpite.',
    ],
];
