<?php

namespace App\Modules\Billing\Application;

use App\Models\Negocio;
use App\Models\Plan;
use App\Models\Suscripcion;
use Illuminate\Support\Facades\DB;

/**
 * Suscripción del negocio al plan Pro (§9.6, §10.1) — administrativo, sin
 * pasarela de pago: la v1 no enruta dinero por la plataforma. `negocio.plan_id`
 * (la entidad "activa" ahora mismo) y `suscripcion` (el contrato de
 * facturación con su ciclo) se actualizan juntos, nunca por separado.
 */
final readonly class SuscripcionService
{
    private const PRECIO_BASE_USD = 8.0;

    private const PRECIO_ADICIONAL_USD = 5.0;

    /** Plan anual: paga 10 meses, 2 gratis (§9.6). */
    private const MESES_PLAN_ANUAL = 10;

    /**
     * @param  array{plan: string, profesionales: int, ciclo: string}  $datos
     */
    public function activar(Negocio $negocio, array $datos): Suscripcion
    {
        $plan = Plan::where('codigo', $datos['plan'])->firstOrFail();
        $precioMensual = self::PRECIO_BASE_USD + self::PRECIO_ADICIONAL_USD * ($datos['profesionales'] - 1);
        $vigenteHasta = $datos['ciclo'] === 'anual' ? now()->addYear() : now()->addMonth();

        return DB::transaction(function () use ($negocio, $plan, $datos, $precioMensual, $vigenteHasta) {
            $suscripcion = Suscripcion::create([
                'negocio_id' => $negocio->id,
                'plan_id' => $plan->id,
                'profesionales' => $datos['profesionales'],
                'precio_mensual' => $precioMensual,
                'ciclo' => $datos['ciclo'],
                'estado' => 'activa',
                'vigente_hasta' => $vigenteHasta->toDateString(),
            ]);

            $negocio->update(['plan_id' => $plan->id, 'plan_vigente_hasta' => $vigenteHasta->toDateString()]);

            return $suscripcion;
        });
    }

    /**
     * Cancela de inmediato — la v1 no modela periodo de gracia por falta de
     * cobro real (sin pasarela, no hay "reintentar el cobro" que perseguir).
     */
    public function cancelar(Suscripcion $suscripcion): Suscripcion
    {
        return DB::transaction(function () use ($suscripcion) {
            $suscripcion->update(['estado' => 'cancelada']);

            // Autocuración si el seeder de `plan` (Fase 1) no corrió — mismo
            // criterio que `NotificacionCategoria` en `NotificacionService`
            // (Fase 9): conjunto cerrado y fijo, no vale la pena que un test
            // de otro módulo falle por esto.
            $planFree = Plan::where('codigo', 'free')->first()
                ?? Plan::create(['codigo' => 'free', 'nombre' => 'Free', 'activo' => true]);

            $suscripcion->negocio()->update(['plan_id' => $planFree->id, 'plan_vigente_hasta' => null]);

            return $suscripcion;
        });
    }

    public function montoDelCiclo(Suscripcion $suscripcion): float
    {
        return $suscripcion->ciclo === 'anual'
            ? (float) $suscripcion->precio_mensual * self::MESES_PLAN_ANUAL
            : (float) $suscripcion->precio_mensual;
    }
}
