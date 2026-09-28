<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            // `codigo` es varchar(10): `slug()` a veces devuelve más.
            'codigo' => Str::limit(fake()->unique()->slug(1), 10, ''),
            'nombre' => fake()->word(),
            'limite_locales' => 1,
            'limite_profesionales' => 1,
            'limite_fotos' => 3,
            'liquidacion_desglose' => false,
            'recordatorios_whatsapp' => false,
            'responder_resenas' => false,
            'estadisticas_completas' => false,
            'promociones_horas_valle' => false,
            'bloque_destacados' => false,
            'precio_base' => 0,
            'precio_adicional' => 0,
            'meses_pago_anual' => 12,
            'activo' => true,
        ];
    }

    /** Valores reales del plan Pro (§9.4, §9.6) — mismos del seeder. */
    public function pro(): static
    {
        return $this->state(fn () => [
            'limite_locales' => null,
            'limite_profesionales' => null,
            'limite_fotos' => null,
            'liquidacion_desglose' => true,
            'recordatorios_whatsapp' => true,
            'responder_resenas' => true,
            'estadisticas_completas' => true,
            'promociones_horas_valle' => true,
            'bloque_destacados' => true,
            'precio_base' => 8,
            'precio_adicional' => 5,
            'meses_pago_anual' => 10,
        ]);
    }
}
