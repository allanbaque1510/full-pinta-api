<?php

namespace Database\Factories;

use App\Models\Local;
use App\Models\Profesional;
use App\Models\TurnoFecha;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TurnoFecha>
 */
class TurnoFechaFactory extends Factory
{
    protected $model = TurnoFecha::class;

    public function definition(): array
    {
        return [
            'profesional_id' => Profesional::factory(),
            'local_id' => Local::factory(),
            'fecha' => now()->addWeek()->toDateString(),
            'entra' => '10:00',
            'sale' => '16:00',
            'tipo' => 'extra',
        ];
    }

    /** Ese día no trabaja: sin horas, por exigencia del CHECK de la tabla. */
    public function cancela(): static
    {
        return $this->state(fn () => ['tipo' => 'cancela', 'entra' => null, 'sale' => null]);
    }
}
