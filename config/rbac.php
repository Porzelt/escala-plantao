<?php

/*
| Matriz de permissões perfil x módulo.
| Níveis: none < read < write. "write" implica "read".
| O admin ignora a matriz (Gate::before), mas fica listado para documentação.
*/

return [
    'levels' => ['none' => 0, 'read' => 1, 'write' => 2],

    'matrix' => [
        'admin' => [
            'escalas' => 'write', 'membros' => 'write', 'trocas' => 'write',
            'relatorios' => 'write', 'auditoria' => 'read',
        ],
        'gestor' => [
            'escalas' => 'write', 'membros' => 'write', 'trocas' => 'write',
            'relatorios' => 'read', 'auditoria' => 'read',
        ],
        'membro' => [
            'escalas' => 'read', 'membros' => 'none', 'trocas' => 'write',
            'relatorios' => 'none', 'auditoria' => 'none',
        ],
    ],
];
