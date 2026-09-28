<?php

namespace Database\Factories;

use App\Models\Rubro;
use App\Models\ServicioCategoria;
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
            'rubro_id' => (Rubro::first() ?? Rubro::factory()->create())->id,
            'codigo' => fake()->unique()->slug(1),
            'nombre' => fake()->word(),
            'orden' => 0,
            'activo' => true,
        ];
    }
}
