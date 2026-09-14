<?php

namespace Database\Factories;

use App\Models\Cobro;
use App\Models\Suscripcion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cobro>
 */
class CobroFactory extends Factory
{
    protected $model = Cobro::class;

    public function definition(): array
    {
        return [
            'suscripcion_id' => Suscripcion::factory(),
            'monto' => 23,
            'estado' => 'pendiente',
            'intentos' => 0,
        ];
    }
}
