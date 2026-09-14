<?php

namespace Database\Factories;

use App\Models\Local;
use App\Models\LocalFoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocalFoto>
 */
class LocalFotoFactory extends Factory
{
    protected $model = LocalFoto::class;

    public function definition(): array
    {
        return [
            'local_id' => Local::factory(),
            'url' => fake()->imageUrl(),
            'tipo' => fake()->randomElement(['fachada', 'interior', 'trabajo']),
            'orden' => 0,
        ];
    }
}
