<?php

namespace Database\Factories;

use App\Models\Reporte;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Reporte>
 */
class ReporteFactory extends Factory
{
    protected $model = Reporte::class;

    public function definition(): array
    {
        return [
            'tipo' => 'resena',
            'objeto_id' => (string) Str::uuid7(),
            'reportante_id' => Usuario::factory(),
            'motivo' => fake()->randomElement(['difamacion', 'contenido_inapropiado', 'falso', 'spam', 'otro']),
            'detalle' => fake()->optional()->sentence(),
            'estado' => 'pendiente',
        ];
    }
}
