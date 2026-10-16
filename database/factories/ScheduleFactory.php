<?php

namespace Database\Factories;

use App\Enums\ScheduleStatus;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'month' => now()->startOfMonth()->toDateString(),
            'status' => ScheduleStatus::Rascunho,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => ScheduleStatus::Publicada, 'published_at' => now()]);
    }
}
