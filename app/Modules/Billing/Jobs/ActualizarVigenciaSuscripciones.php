<?php

namespace App\Modules\Billing\Jobs;

use App\Models\Asignacion;
use App\Models\Suscripcion;
use App\Modules\Billing\Application\CobroService;
use App\Modules\Billing\Application\SuscripcionService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vigencia de la suscripción (§9.6, §10.1), job diario, cola
 * `batch` (§12.6) — mismo patrón que `RecalcularScoreRanking`. Revisión de
 * base de datos, 2026-09-28: antes no existía ningún job que renovara,
 * y `SuscripcionService::cancelar()` bajaba el negocio a Free de inmediato,
 * sin respetar el período ya pagado.
 *
 * Para cada `suscripcion` con `vigente_hasta <= hoy + 7 días`:
 * - Si `vigente_hasta <= hoy` y `estado='cancelada'` → baja el negocio a Free
 *   ahora (el dueño canceló, y el período que pagó ya se cumplió). Sin cobro
 *   nuevo.
 * - Si `vigente_hasta <= hoy` y `estado='activa'` (nunca canceló, el período
 *   simplemente se cumplió) → recalcula `profesionales` (asignaciones
 *   vigentes reales en los locales del negocio) y `precio_mensual` con la
 *   fórmula del plan, `CobroService::registrar()` para el siguiente período,
 *   y pasa a `'gracia'`.
 * - Si `estado='gracia'` y ya pasó el margen de tolerancia sin pago
 *   (`config('fullpinta.suscripcion.dias_gracia')`) → pasa a `'vencida'` y
 *   baja el negocio a Free.
 *
 * **No implementado a propósito**: la notificación "suscripción por vencer"
 * a los 7 días (§11.2) — depende del canal `'email'` + un `EnviadorEmail`,
 * ninguno de los dos existe todavía (bloqueador ya registrado aparte). Se
 * omite en vez de dejar una llamada rota o un no-op silencioso.
 */
class ActualizarVigenciaSuscripciones implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CobroService $cobros, SuscripcionService $suscripciones): void
    {
        $hoy = CarbonImmutable::now()->startOfDay();
        $limite = $hoy->addDays(7);

        Suscripcion::whereIn('estado', ['activa', 'gracia', 'cancelada'])
            ->whereDate('vigente_hasta', '<=', $limite->toDateString())
            ->with('negocio')
            ->get()
            ->each(function (Suscripcion $suscripcion) use ($hoy, $cobros, $suscripciones) {
                $vigenteHasta = CarbonImmutable::parse($suscripcion->vigente_hasta)->startOfDay();

                if ($vigenteHasta->gt($hoy)) {
                    return; // Todavía dentro de los 7 días, pero no vencida — nada que hacer hoy.
                }

                match ($suscripcion->estado) {
                    'cancelada' => $suscripciones->bajarNegocioAFree($suscripcion->negocio),
                    'activa' => $this->renovarOEntrarEnGracia($suscripcion, $cobros),
                    'gracia' => $this->vencerSiPasoElMargen($suscripcion, $vigenteHasta, $suscripciones),
                    default => null,
                };
            });
    }

    private function renovarOEntrarEnGracia(Suscripcion $suscripcion, CobroService $cobros): void
    {
        DB::transaction(function () use ($suscripcion, $cobros) {
            $suscripcion->loadMissing('plan', 'negocio');
            $profesionales = $this->contarProfesionalesVigentes($suscripcion);

            $suscripcion->update([
                'profesionales' => $profesionales,
                'precio_mensual' => (float) $suscripcion->plan->precio_base
                    + (float) $suscripcion->plan->precio_adicional * ($profesionales - 1),
                'estado' => 'gracia',
            ]);

            $cobros->registrar($suscripcion);
        });
    }

    /** Conteo real, no lo que se pidió al activar: asignaciones vigentes en CUALQUIER local del negocio, un profesional no cuenta dos veces. */
    private function contarProfesionalesVigentes(Suscripcion $suscripcion): int
    {
        $localIds = $suscripcion->negocio->locales()->pluck('id');

        return Asignacion::whereIn('local_id', $localIds)->vigente()->distinct('profesional_id')->count('profesional_id');
    }

    private function vencerSiPasoElMargen(Suscripcion $suscripcion, CarbonImmutable $vigenteHasta, SuscripcionService $suscripciones): void
    {
        $limiteGracia = $vigenteHasta->addDays((int) config('fullpinta.suscripcion.dias_gracia'));

        if (CarbonImmutable::now()->lte($limiteGracia)) {
            return; // Todavía dentro del margen de gracia.
        }

        DB::transaction(function () use ($suscripcion, $suscripciones) {
            $suscripcion->update(['estado' => 'vencida']);
            $suscripciones->bajarNegocioAFree($suscripcion->negocio);
        });
    }
}
