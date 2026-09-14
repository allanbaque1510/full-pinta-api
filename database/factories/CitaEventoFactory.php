<?php

namespace Database\Factories;

use App\Models\Cita;
use App\Models\CitaEvento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CitaEvento>
 */
class CitaEventoFactory extends Factory
{
    protected $model = CitaEvento::class;

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
