<?php

namespace Database\Factories;

use App\Models\ServicioCategoria;
use App\Models\Vertical;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicioCategoria>
 */
class ServicioCategoriaFactory extends Factory
{
    protected $model = ServicioCategoria::class;

    public function definition(): array
    {
        return [
            'vertical_id' => (Vertical::first() ?? Vertical::factory()->create())->id,
            'codigo' => fake()->unique()->slug(1),
            'nombre' => fake()->word(),
            'orden' => 0,
            'activo' => true,
        ];
    }
}
