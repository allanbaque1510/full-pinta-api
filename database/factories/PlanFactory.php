<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            // `codigo` es varchar(10): `slug()` a veces devuelve más.
            'codigo' => Str::limit(fake()->unique()->slug(1), 10, ''),
            'nombre' => fake()->word(),
            'activo' => true,
        ];
    }
}
