<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Grava em audit_logs quem alterou o quê (antes/depois) em created/updated/deleted.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $m) => static::writeAudit($m, 'created', null, $m->getAttributes()));

        static::updated(function (Model $m) {
            $changes = Arr::except($m->getChanges(), ['updated_at']);
            if ($changes === []) {
                return;
            }
            static::writeAudit($m, 'updated', Arr::only($m->getOriginal(), array_keys($changes)), $changes);
        });

        static::deleted(fn (Model $m) => static::writeAudit($m, 'deleted', $m->getOriginal(), null));
    }

    protected static function writeAudit(Model $model, string $event, ?array $old, ?array $new): void
    {
        $hidden = array_merge(config('audit.hidden'), $model->getHidden());
        $user = Auth::user();

        AuditLog::create([
            'user_id' => $user?->id,
            'unit_id' => $model->getAttribute('unit_id') ?? $model->auditUnitId() ?? $user?->unit_id,
            'event' => $event,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => $old ? static::serializeValues(Arr::except($old, $hidden)) : null,
            'new_values' => $new ? static::serializeValues(Arr::except($new, $hidden)) : null,
            'ip' => app()->runningInConsole() ? null : Request::ip(),
        ]);
    }

    private static function serializeValues(array $values): array
    {
        return array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : ($v instanceof \DateTimeInterface ? $v->format('Y-m-d H:i:s') : $v), $values);
    }

    /** Models sem unit_id próprio podem informar a unidade (ex.: via relação). */
    public function auditUnitId(): ?int
    {
        return null;
    }
}
