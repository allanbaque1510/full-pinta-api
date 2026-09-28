<?php

namespace Database\Factories;

use App\Models\MetodoPago;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MetodoPago>
 */
class MetodoPagoFactory extends Factory
{
    protected $model = MetodoPago::class;

    public function definition(): array
    {
        return [
            'codigo' => Str::limit(fake()->unique()->slug(1), 20, ''),
            'nombre' => fake()->word(),
            'orden' => 0,
            'activo' => true,
        ];
    }
}
