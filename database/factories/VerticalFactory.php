<?php

namespace Database\Factories;

use App\Models\Vertical;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Vertical>
 */
class VerticalFactory extends Factory
{
    protected $model = Vertical::class;

    public function definition(): array
    {
        return [
            // `codigo` es varchar(20): `slug()` a veces devuelve más.
            'codigo' => Str::limit(fake()->unique()->slug(1), 20, ''),
            'nombre' => fake()->word(),
            'activo' => true,
        ];
    }
}
