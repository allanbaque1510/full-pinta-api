<?php

namespace Database\Factories;

use App\Models\Liquidacion;
use App\Models\Local;
use App\Models\Profesional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Liquidacion>
 */
class LiquidacionFactory extends Factory
{
    protected $model = Liquidacion::class;

    public function definition(): array
    {
        return [
            'local_id' => Local::factory(),
            'profesional_id' => Profesional::factory(),
            'periodo_desde' => now()->startOfMonth()->toDateString(),
            'periodo_hasta' => now()->endOfMonth()->toDateString(),
            'total_servicios' => 0,
            'total_productos' => 0,
            'total_propinas' => 0,
            'comision_servicios' => 0,
            'comision_productos' => 0,
            'total_a_pagar' => 0,
            'estado' => 'borrador',
        ];
    }
}
