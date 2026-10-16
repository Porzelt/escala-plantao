<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'name' => 'Diurno',
            'starts_at' => '07:00',
            'duration_hours' => 12,
            'slots' => 1,
            'weight' => 1,
        ];
    }

    public function noturno(): static
    {
        return $this->state(['name' => 'Noturno', 'starts_at' => '19:00', 'weight' => 1.5]);
    }
}
