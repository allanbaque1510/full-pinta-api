<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Planes de la plataforma (§4.4, §4.10, §9.4): compartido entre `negocio`
 * (plan actual) y `suscripcion` (Billing).
 */
class PlanSeeder extends Seeder
{
    /**
     * codigo => resto de atributos, valores reales de la tabla §9.4/§9.6.
     *
     * @var array<string, array<string, mixed>>
     */
    private const PLANES = [
        'free' => [
            'nombre' => 'Free',
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
            'orden' => 0,
        ],
        'pro' => [
            'nombre' => 'Pro',
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
            'meses_pago_anual' => 10,   // paga 10, se lleva 12
            'orden' => 1,
        ],
    ];

    public function run(): void
    {
        foreach (self::PLANES as $codigo => $atributos) {
            Plan::updateOrCreate(['codigo' => $codigo], [...$atributos, 'activo' => true]);
        }
    }
}
