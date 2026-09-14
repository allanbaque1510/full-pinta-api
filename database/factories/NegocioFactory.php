<?php

namespace Database\Factories;

use App\Models\Negocio;
use App\Models\Plan;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Negocio>
 */
class NegocioFactory extends Factory
{
    protected $model = Negocio::class;

    public function definition(): array
    {
        return [
            'nombre_marca' => fake()->company(),
            'ruc' => fake()->numerify('#############'),
            'propietario_id' => Usuario::factory(),
            'plan_id' => self::planId('free'),
        ];
    }

    public function pro(): static
    {
        return $this->state(fn () => [
            'plan_id' => self::planId('pro'),
            'plan_vigente_hasta' => now()->addMonth()->toDateString(),
        ]);
    }

    private static function planId(string $codigo): string
    {
        return (Plan::where('codigo', $codigo)->first() ?? Plan::factory()->create(['codigo' => $codigo]))->id;
    }
}
