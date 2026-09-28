<?php

namespace Database\Factories;

use App\Models\FinalidadConsentimiento;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FinalidadConsentimiento>
 */
class FinalidadConsentimientoFactory extends Factory
{
    protected $model = FinalidadConsentimiento::class;

    public function definition(): array
    {
        return [
            'codigo' => Str::limit(fake()->unique()->slug(2), 40, ''),
            'nombre' => fake()->words(3, true),
            'descripcion' => fake()->sentence(),
            'obligatorio' => false,
            'activo' => true,
        ];
    }
}
