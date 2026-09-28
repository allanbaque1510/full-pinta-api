<?php

namespace App\Modules\Billing\Application;

use App\Models\Negocio;
use App\Models\Plan;
use App\Models\Suscripcion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Suscripción del negocio al plan Pro (§9.6, §10.1) — administrativo, sin
 * pasarela de pago: la v1 no enruta dinero por la plataforma. `negocio.plan_id`
 * (la entidad "activa" ahora mismo) y `suscripcion` (el contrato de
 * facturación con su ciclo) se actualizan juntos, nunca por separado.
 */
final readonly class SuscripcionService
{
    /** SQLSTATE de violación de unicidad. */
    private const UNIQUE_VIOLATION = '23505';

    /**
     * @param  array{plan: string, profesionales: int, ciclo: string}  $datos
     */
    public function activar(Negocio $negocio, array $datos): Suscripcion
    {
        $plan = Plan::where('codigo', $datos['plan'])->firstOrFail();
        $precioMensual = (float) $plan->precio_base + (float) $plan->precio_adicional * ($datos['profesionales'] - 1);
        $vigenteHasta = $datos['ciclo'] === 'anual' ? now()->addYear() : now()->addMonth();

        try {
            return DB::transaction(function () use ($negocio, $plan, $datos, $precioMensual, $vigenteHasta) {
                // A lo sumo una 'activa' a la vez (§9.6, índice único parcial):
                // la anterior queda 'cancelada', superseded por esta — no se
                // deja que el índice la rechace con un error confuso.
                Suscripcion::where('negocio_id', $negocio->id)->where('estado', 'activa')
                    ->update(['estado' => 'cancelada']);

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
        } catch (QueryException $e) {
            if ($e->getCode() !== self::UNIQUE_VIOLATION) {
                throw $e;
            }

            throw_validacion('Este negocio ya tiene una suscripción activa; espera a que la petición en curso termine.', 'plan');
        }
    }

    /**
     * Solo marca `estado='cancelada'` — el negocio SIGUE en su plan actual
     * hasta que `vigente_hasta` se cumpla de forma natural (lo baja
     * `ActualizarVigenciaSuscripciones`, el job diario). Antes bajaba el
     * negocio a Free de inmediato, lo que no respetaba el período ya pagado
     * (mensual o anual) — revisión de base de datos, 2026-09-28.
     */
    public function cancelar(Suscripcion $suscripcion): Suscripcion
    {
        $suscripcion->update(['estado' => 'cancelada']);

        return $suscripcion;
    }

    /**
     * Único camino para bajar un negocio a Free al vencer una suscripción
     * (cancelada que cumplió su período, o vencida tras el margen de
     * gracia) — usado por `ActualizarVigenciaSuscripciones`.
     */
    public function bajarNegocioAFree(Negocio $negocio): void
    {
        DB::transaction(function () use ($negocio) {
            // Autocuración si el seeder de `plan` (Fase 1) no corrió — mismo
            // criterio que `NotificacionCategoria` en `NotificacionService`
            // (Fase 9): conjunto cerrado y fijo, no vale la pena que un test
            // de otro módulo falle por esto.
            $planFree = Plan::where('codigo', 'free')->first()
                ?? Plan::create(['codigo' => 'free', 'nombre' => 'Free', 'activo' => true]);

            $negocio->update(['plan_id' => $planFree->id, 'plan_vigente_hasta' => null]);
        });
    }

    public function montoDelCiclo(Suscripcion $suscripcion): float
    {
        $suscripcion->loadMissing('plan');

        return $suscripcion->ciclo === 'anual'
            ? (float) $suscripcion->precio_mensual * $suscripcion->plan->meses_pago_anual
            : (float) $suscripcion->precio_mensual;
    }
}
