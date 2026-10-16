<?php

namespace App\Models;

use App\Enums\SwapStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwapRequest extends Model
{
    use Auditable, HasFactory;

    protected $fillable = ['shift_assignment_id', 'requester_id', 'target_user_id', 'status', 'reason', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['status' => SwapStatus::class, 'decided_at' => 'datetime'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class, 'shift_assignment_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function auditUnitId(): ?int
    {
        return User::whereKey($this->requester_id)->value('unit_id');
    }
}
