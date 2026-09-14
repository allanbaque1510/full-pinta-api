<?php

namespace Database\Factories;

use App\Models\Cita;
use App\Models\CitaItem;
use App\Models\ServicioLocal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CitaItem>
 *
 * Precio y comisión congelados al agendar (§4.7).
 */
class CitaItemFactory extends Factory
{
    protected $model = CitaItem::class;

    public function definition(): array
    {
        return [
            'cita_id' => Cita::factory(),
            'servicio_local_id' => ServicioLocal::factory(),
            'precio' => fake()->randomFloat(2, 5, 60),
            'duracion_min' => 30,
            'comisionable' => true,
            'comision_pct' => 50,
        ];
    }
}
