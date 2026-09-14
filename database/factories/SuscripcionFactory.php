<?php

namespace Database\Factories;

use App\Models\Negocio;
use App\Models\Plan;
use App\Models\Suscripcion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Suscripcion>
 */
class SuscripcionFactory extends Factory
{
    protected $model = Suscripcion::class;

    public function definition(): array
    {
        return [
            'negocio_id' => Negocio::factory(),
            'plan_id' => (Plan::where('codigo', 'pro')->first() ?? Plan::factory()->create(['codigo' => 'pro']))->id,
            'profesionales' => fake()->numberBetween(1, 8),
            'precio_mensual' => 23,
            'ciclo' => 'mensual',
            'estado' => 'activa',
            'vigente_hasta' => now()->addMonth()->toDateString(),
        ];
    }
}
