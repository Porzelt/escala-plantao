<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\ShiftType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'schedule_id' => Schedule::factory(),
            'shift_type_id' => ShiftType::factory(),
            'user_id' => User::factory(),
            'date' => now()->addDays(3)->toDateString(),
        ];
    }
}
