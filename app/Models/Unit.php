<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use Auditable, HasFactory;

    protected $fillable = ['name', 'code', 'city'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function shiftTypes(): HasMany
    {
        return $this->hasMany(ShiftType::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function auditUnitId(): ?int
    {
        return $this->id;
    }
}
