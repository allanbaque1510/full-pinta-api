<?php

namespace Database\Factories;

use App\Models\PlantillaWhatsapp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlantillaWhatsapp>
 */
class PlantillaWhatsappFactory extends Factory
{
    protected $model = PlantillaWhatsapp::class;

    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->slug(2, false),
            'categoria' => 'utility',
            'idioma' => 'es',
            'estado' => 'aprobada',
            'variables' => ['nombre', 'fecha'],
        ];
    }
}
