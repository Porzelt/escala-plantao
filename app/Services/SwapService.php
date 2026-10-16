<?php

namespace App\Services;

use App\Enums\SwapStatus;
use App\Models\ShiftAssignment;
use App\Models\SwapRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SwapService
{
    public function request(ShiftAssignment $assignment, User $requester, User $target, ?string $reason): SwapRequest
    {
        if ($target->id === $requester->id || $target->unit_id !== $requester->unit_id || ! $target->active) {
            throw ValidationException::withMessages(['target_user_id' => 'Escolha um colega ativo da mesma unidade.']);
        }

        if ($this->hasConflict($target, $assignment)) {
            throw ValidationException::withMessages(['target_user_id' => 'O colega já tem plantão nesta data.']);
        }

        $open = SwapRequest::where('shift_assignment_id', $assignment->id)
            ->whereIn('status', [SwapStatus::Pendente, SwapStatus::Aceita])->exists();

        if ($open) {
            throw ValidationException::withMessages(['shift_assignment_id' => 'Já existe uma troca em andamento para este plantão.']);
        }

        return SwapRequest::create([
            'shift_assignment_id' => $assignment->id,
            'requester_id' => $requester->id,
            'target_user_id' => $target->id,
            'reason' => $reason,
        ]);
    }

    public function respond(SwapRequest $swap, bool $accept): void
    {
        $swap->update(['status' => $accept ? SwapStatus::Aceita : SwapStatus::Recusada]);
    }

    /** Aprovação do gestor: transfere o plantão com lock para evitar corrida. */
    public function decide(SwapRequest $swap, User $manager, bool $approve): void
    {
        DB::transaction(function () use ($swap, $manager, $approve) {
            $assignment = ShiftAssignment::lockForUpdate()->findOrFail($swap->shift_assignment_id);

            if ($approve) {
                if ($this->hasConflict($swap->target, $assignment)) {
                    throw ValidationException::withMessages(['swap' => 'O colega passou a ter plantão nesta data.']);
                }
                $assignment->update(['user_id' => $swap->target_user_id]);
            }

            $swap->update([
                'status' => $approve ? SwapStatus::Aprovada : SwapStatus::Recusada,
                'decided_by' => $manager->id,
                'decided_at' => now(),
            ]);
        });
    }

    private function hasConflict(User $user, ShiftAssignment $assignment): bool
    {
        return ShiftAssignment::where('user_id', $user->id)
            ->whereDate('date', $assignment->date)
            ->exists();
    }
}
