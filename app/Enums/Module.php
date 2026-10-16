<?php

namespace App\Enums;

/**
 * Módulos do sistema. Cada perfil recebe um nível de acesso por módulo
 * (ver config/rbac.php), e o middleware `module:<modulo>,<nivel>` protege as rotas.
 */
enum Module: string
{
    case Escalas = 'escalas';
    case Membros = 'membros';
    case Trocas = 'trocas';
    case Relatorios = 'relatorios';
    case Auditoria = 'auditoria';
}
