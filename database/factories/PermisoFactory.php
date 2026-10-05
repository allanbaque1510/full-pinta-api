<?php

namespace Database\Factories;

use App\Models\Permiso;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Permiso>
 */
class PermisoFactory extends Factory
{
    protected $model = Permiso::class;

    public function definition(): array
    {
        return [
            'codigo' => Str::limit(fake()->unique()->slug(2), 60, ''),
            'nombre' => fake()->words(3, true),
            'descripcion' => fake()->sentence(),
            'activo' => true,
        ];
    }
}
