<?php

namespace Database\Factories;

use App\Models\CatalogoServicio;
use App\Models\ServicioCategoria;
use App\Models\TipoRecurso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogoServicio>
 */
class CatalogoServicioFactory extends Factory
{
    protected $model = CatalogoServicio::class;

    public function definition(): array
    {
        return [
            'categoria_id' => (ServicioCategoria::first() ?? ServicioCategoria::factory()->create())->id,
            'nombre' => fake()->words(2, true),
            'slug' => fake()->unique()->slug(3),
            'duracion_base_min' => fake()->randomElement([20, 30, 45, 60]),
            'tipo_recurso_id' => (TipoRecurso::where('codigo', 'silla')->first() ?? TipoRecurso::factory()->create(['codigo' => 'silla']))->id,
            'activo' => true,
        ];
    }
}
