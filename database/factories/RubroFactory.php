<?php

namespace Database\Factories;

use App\Models\Rubro;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Rubro>
 */
class RubroFactory extends Factory
{
    protected $model = Rubro::class;

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
