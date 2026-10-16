<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToUnit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftType extends Model
{
    use Auditable, BelongsToUnit, HasFactory;

    protected $fillable = ['unit_id', 'name', 'starts_at', 'duration_hours', 'slots', 'weight'];

    protected function casts(): array
    {
        return ['weight' => 'float', 'duration_hours' => 'integer', 'slots' => 'integer'];
    }
}
