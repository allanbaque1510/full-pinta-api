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

    public function rucVerificado(): static
    {
        return $this->state(fn () => ['ruc_verificado' => true, 'ruc_verificado_at' => now()]);
    }

    private static function planId(string $codigo): string
    {
        $existente = Plan::where('codigo', $codigo)->first();

        if ($existente !== null) {
            return $existente->id;
        }

        $factory = $codigo === 'pro' ? Plan::factory()->pro() : Plan::factory();

        return $factory->create(['codigo' => $codigo])->id;
    }
}
