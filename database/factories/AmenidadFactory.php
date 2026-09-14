<?php

namespace Database\Factories;

use App\Models\Amenidad;
use App\Models\AmenidadCategoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Amenidad>
 */
class AmenidadFactory extends Factory
{
    protected $model = Amenidad::class;

    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->slug(2),
            'categoria_id' => (AmenidadCategoria::first() ?? AmenidadCategoria::factory()->create())->id,
            'nombre' => fake()->words(2, true),
            'activo' => true,
        ];
    }
}
