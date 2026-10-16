<?php

namespace App\Models;

use App\Enums\ExportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleExport extends Model
{
    protected $fillable = ['schedule_id', 'user_id', 'format', 'status', 'progress', 'path', 'error'];

    protected function casts(): array
    {
        return ['status' => ExportStatus::class, 'progress' => 'integer'];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
