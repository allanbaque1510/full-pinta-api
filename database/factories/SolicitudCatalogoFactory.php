<?php

namespace Database\Factories;

use App\Models\Local;
use App\Models\SolicitudCatalogo;
use App\Models\Vertical;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SolicitudCatalogo>
 */
class SolicitudCatalogoFactory extends Factory
{
    protected $model = SolicitudCatalogo::class;

    public function definition(): array
    {
        return [
            'local_id' => Local::factory(),
            'vertical_id' => (Vertical::first() ?? Vertical::factory()->create())->id,
            'nombre_propuesto' => fake()->words(2, true),
            'descripcion' => fake()->optional()->sentence(),
            'estado' => 'pendiente',
        ];
    }
}
