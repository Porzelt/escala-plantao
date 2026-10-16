<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Unidade '.fake()->unique()->city(),
            'code' => strtoupper(fake()->unique()->bothify('U-###??')),
            'city' => fake()->city(),
        ];
    }
}
