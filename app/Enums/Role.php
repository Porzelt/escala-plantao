<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Gestor = 'gestor';
    case Membro = 'membro';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Gestor => 'Gestor de unidade',
            self::Membro => 'Membro da equipe',
        };
    }
}
