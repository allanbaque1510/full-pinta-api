<?php

namespace Database\Factories;

use App\Models\TamanoMascota;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TamanoMascota>
 */
class TamanoMascotaFactory extends Factory
{
    protected $model = TamanoMascota::class;

    public function definition(): array
    {
        return [
            // `codigo` es varchar(20): `slug()` a veces devuelve más.
            'codigo' => Str::limit(fake()->unique()->slug(1), 20, ''),
            'nombre' => fake()->word(),
            'orden' => 0,
            'activo' => true,
        ];
    }
}
