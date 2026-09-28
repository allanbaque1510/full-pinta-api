<?php

namespace Database\Factories;

use App\Models\Cita;
use App\Models\CitaBitacora;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CitaBitacora>
 */
class CitaBitacoraFactory extends Factory
{
    protected $model = CitaBitacora::class;

    public function definition(): array
    {
        return [
            'cita_id' => Cita::factory(),
            'estado_anterior' => 'reservada',
            'estado_nuevo' => 'confirmada',
            'actor_rol' => 'cliente',
            'created_at' => now(),
        ];
    }
}
