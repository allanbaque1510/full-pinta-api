<?php

namespace App\Modules\Reviews\Jobs;

use App\Models\Cita;
use App\Models\Local;
use App\Models\Resena;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Ranking orgánico (§7.1-7.3): "el score se recalcula por job nocturno, nunca
 * en el request" — cola `batch` (§12.6), agendado en `bootstrap/app.php`.
 *
 * `local.score_ranking` combina el promedio bayesiano (§7.1, única fórmula
 * exacta que da la especificación) con los factores del §7.3, que la
 * especificación lista SIN pesos — la ponderación de abajo es una
 * interpretación explícita, no un número que venga del documento:
 *
 * - **Bayesiano** (0-5, término dominante): `(v/(v+M))*R + (M/(v+M))*C`.
 * - **Actividad**: citas `completada` en los últimos 30 días, `min(n,30)/30`
 *   — con tope, para no premiar volumen puro sin límite.
 * - **Confiabilidad**: `1 - (cancelada_local / total_citas_30d)` sobre citas
 *   con `inicio` en los últimos 30 días — penaliza cancelaciones **del
 *   local**, nunca las del cliente (eso es `cliente_perfil`, otro dato).
 * - **Completitud de perfil**: promedio de 3 booleanos (≥1 foto, ≥1 servicio
 *   activo, ≥1 horario).
 * - **Bonus verificado**: `+0.1` fijo.
 *
 * **No modelado a propósito**: "tiempo de respuesta a solicitudes" (§7.3) —
 * el esquema no tiene noción de solicitud pendiente de aprobación (el
 * agendamiento es inmediato vía slot, no por aprobación del local), así que
 * no hay de dónde sacar ese dato. Se omite en vez de inventar una proxy.
 */
class RecalcularScoreRanking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Peso del prior bayesiano (§7.1: "m = 10, ajustable"). */
    private const PESO_PRIOR = 10;

    private const TOPE_ACTIVIDAD_30D = 30;

    private const BONUS_VERIFICADO = 0.1;

    private const PESO_COMPLETITUD = 0.5;

    public function handle(): void
    {
        $promedioGlobal = (float) (Resena::publicadas()->avg('puntaje_local') ?? 0);
        $ahora = CarbonImmutable::now();

        Local::where('estado', 'activo')->each(function (Local $local) use ($promedioGlobal, $ahora) {
            $local->update(['score_ranking' => $this->calcularScore($local, $promedioGlobal, $ahora)]);
        });
    }

    private function calcularScore(Local $local, float $promedioGlobal, CarbonImmutable $ahora): float
    {
        return $this->bayesiano($local, $promedioGlobal)
            + $this->actividad($local, $ahora)
            + $this->confiabilidad($local, $ahora)
            + $this->completitud($local) * self::PESO_COMPLETITUD
            + ($local->verificado ? self::BONUS_VERIFICADO : 0);
    }

    private function bayesiano(Local $local, float $promedioGlobal): float
    {
        $resenas = Resena::where('local_id', $local->id)->publicadas();
        $v = $resenas->count();
        $r = (float) ($resenas->avg('puntaje_local') ?? 0);

        if ($v === 0) {
            return $promedioGlobal;
        }

        return ($v / ($v + self::PESO_PRIOR)) * $r + (self::PESO_PRIOR / ($v + self::PESO_PRIOR)) * $promedioGlobal;
    }

    private function actividad(Local $local, CarbonImmutable $ahora): float
    {
        $citas30d = Cita::where('local_id', $local->id)
            ->where('estado', 'completada')
            ->where('completada_at', '>=', $ahora->subDays(30))
            ->count();

        return min($citas30d, self::TOPE_ACTIVIDAD_30D) / self::TOPE_ACTIVIDAD_30D;
    }

    private function confiabilidad(Local $local, CarbonImmutable $ahora): float
    {
        $total = Cita::where('local_id', $local->id)->where('inicio', '>=', $ahora->subDays(30))->count();

        if ($total === 0) {
            return 1.0;
        }

        $canceladasLocal = Cita::where('local_id', $local->id)
            ->where('estado', 'cancelada_local')
            ->where('inicio', '>=', $ahora->subDays(30))
            ->count();

        return 1 - ($canceladasLocal / $total);
    }

    private function completitud(Local $local): float
    {
        $factores = [
            $local->fotos()->exists(),
            $local->servicios()->where('activo', true)->exists(),
            $local->horarios()->exists(),
        ];

        return array_sum($factores) / count($factores);
    }
}
