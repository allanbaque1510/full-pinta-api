<?php

namespace Database\Factories;

use App\Models\DisponibilidadDia;
use App\Models\Local;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisponibilidadDia>
 */
class DisponibilidadDiaFactory extends Factory
{
    protected $model = DisponibilidadDia::class;

    public function definition(): array
    {
        return [
            'local_id' => Local::factory(),
            'fecha' => now()->addDay()->toDateString(),
            'slots_libres' => fake()->numberBetween(0, 30),
            'primer_slot' => '09:00',
            'ultimo_slot' => '18:00',
            'recalculado_at' => now(),
        ];
    }
}
