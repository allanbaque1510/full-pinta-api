<?php

namespace Database\Factories;

use App\Models\Profesional;
use App\Models\ProfesionalFoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfesionalFoto>
 */
class ProfesionalFotoFactory extends Factory
{
    protected $model = ProfesionalFoto::class;

    public function definition(): array
    {
        return [
            'profesional_id' => Profesional::factory(),
            'url' => fake()->imageUrl(),
            'orden' => 0,
        ];
    }
}
