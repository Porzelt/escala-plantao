<?php

namespace App\Models;

use App\Enums\ScheduleStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    use Auditable, BelongsToUnit, HasFactory;

    protected $fillable = ['unit_id', 'month', 'status', 'seed', 'created_by', 'published_at'];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'status' => ScheduleStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPublished(): bool
    {
        return $this->status === ScheduleStatus::Publicada;
    }
}
