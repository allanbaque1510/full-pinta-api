<?php

namespace Database\Factories;

use App\Models\EspecieMascota;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EspecieMascota>
 */
class EspecieMascotaFactory extends Factory
{
    protected $model = EspecieMascota::class;

    public function definition(): array
    {
        return [
            'codigo' => Str::limit(fake()->unique()->slug(1), 20, ''),
            'nombre' => fake()->word(),
            'activo' => true,
        ];
    }
}
