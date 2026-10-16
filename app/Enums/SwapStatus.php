<?php

namespace App\Enums;

/**
 * Fluxo da troca: solicitada -> aceita pelo colega -> aprovada/recusada pelo gestor.
 */
enum SwapStatus: string
{
    case Pendente = 'pendente';
    case Aceita = 'aceita';
    case Aprovada = 'aprovada';
    case Recusada = 'recusada';
    case Cancelada = 'cancelada';

    public function isOpen(): bool
    {
        return in_array($this, [self::Pendente, self::Aceita], true);
    }
}
