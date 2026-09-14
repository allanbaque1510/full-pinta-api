<?php

namespace Database\Factories;

use App\Models\NotificacionCategoria;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificacionCategoria>
 */
class NotificacionCategoriaFactory extends Factory
{
    protected $model = NotificacionCategoria::class;

    public function definition(): array
    {
        return [
            // `codigo` es varchar(20): `slug()` a veces devuelve más.
            'codigo' => Str::limit(fake()->unique()->slug(1), 20, ''),
            'nombre' => fake()->word(),
            'activo' => true,
        ];
    }
}
