<?php

namespace Database\Factories;

use App\Models\TipoImagen;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TipoImagen>
 */
class TipoImagenFactory extends Factory
{
    protected $model = TipoImagen::class;

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
