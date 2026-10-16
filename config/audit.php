<?php

return [
    // Registros de auditoria mais antigos que isso são removidos por `model:prune` (agendado diariamente).
    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 365),

    // Campos nunca gravados no log.
    'hidden' => ['password', 'remember_token'],
];
