<?php

namespace App\Models\Concerns;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Isolamento de dados por unidade: todo model com `unit_id` só enxerga
 * registros da unidade do usuário autenticado (admin vê todas).
 * Em jobs/console não há usuário, então o escopo não se aplica.
 */
trait BelongsToUnit
{
    public static function bootBelongsToUnit(): void
    {
        static::addGlobalScope('unit', function (Builder $query) {
            $user = Auth::user();

            if ($user && ! $user->isAdmin()) {
                $query->where($query->qualifyColumn('unit_id'), $user->unit_id);
            }
        });

        static::creating(function ($model) {
            $user = Auth::user();

            if ($user && ! $user->isAdmin()) {
                $model->unit_id = $user->unit_id;
            }
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
