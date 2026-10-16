<?php

namespace App\Policies;

use App\Enums\Module;
use App\Enums\SwapStatus;
use App\Models\ShiftAssignment;
use App\Models\SwapRequest;
use App\Models\User;

class SwapRequestPolicy
{
    /** Só o dono do plantão pede troca, e só em escala publicada. */
    public function create(User $user, ShiftAssignment $assignment): bool
    {
        return $assignment->user_id === $user->id
            && $assignment->schedule->isPublished()
            && $user->canAccessModule(Module::Trocas, 'write');
    }

    /** O colega convidado aceita (ou recusa) antes do gestor decidir. */
    public function respond(User $user, SwapRequest $swap): bool
    {
        return $swap->target_user_id === $user->id && $swap->status === SwapStatus::Pendente;
    }

    public function decide(User $user, SwapRequest $swap): bool
    {
        return ($user->isAdmin() || $user->isGestor())
            && $user->sameUnitAs($swap->requester->unit_id)
            && $swap->status === SwapStatus::Aceita;
    }

    public function cancel(User $user, SwapRequest $swap): bool
    {
        return $swap->requester_id === $user->id && $swap->status->isOpen();
    }
}
