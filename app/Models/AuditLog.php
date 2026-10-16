<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use BelongsToUnit, MassPrunable;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'unit_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    /** Política de retenção: executada por `php artisan model:prune` (agendado em routes/console.php). */
    public function prunable(): Builder
    {
        return static::withoutGlobalScopes()->where('created_at', '<', now()->subDays(config('audit.retention_days')));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
