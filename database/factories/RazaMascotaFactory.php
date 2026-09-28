<?php

namespace Database\Factories;

use App\Models\EspecieMascota;
use App\Models\RazaMascota;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RazaMascota>
 */
class RazaMascotaFactory extends Factory
{
    protected $model = RazaMascota::class;

    public function definition(): array
    {
        return [
            'especie_id' => EspecieMascota::factory(),
            'codigo' => Str::slug(fake()->unique()->word(), '_'),
            'nombre' => fake()->word(),
            'activo' => true,
        ];
    }
}
