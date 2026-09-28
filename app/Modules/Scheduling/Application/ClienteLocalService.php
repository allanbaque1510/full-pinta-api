<?php

namespace App\Modules\Scheduling\Application;

use App\Models\Asignacion;
use App\Models\ClienteLocal;
use App\Models\Local;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * `ClienteLocal` (§4.7): continuidad de servicio (`nota`, `profesional_preferido`),
 * no una calificación — esa vive aparte en `cliente_perfil` (confiabilidad,
 * nunca mostrada como puntaje). El total de visitas y sus fechas se calculan
 * en vivo contra `cita`, no se guardan como contador (revisión de base de
 * datos, 2026-09-29): un estado guardado se puede desincronizar, un `COUNT`
 * puntual no.
 */
final readonly class ClienteLocalService
{
    private const TTL_BANDEJA_SEGUNDOS = 300;

    /**
     * Ficha individual: `nota`/`profesional_preferido` (dato del local, nunca
     * visible a otro local, §3.3) + el resumen de visitas calculado en vivo.
     *
     * @return array{nota: ?string, profesional_preferido_id: ?string, total_citas: int, primera_cita_at: ?string, ultima_cita_at: ?string}
     */
    public function ficha(Local $local, Usuario $cliente): array
    {
        $clienteLocal = ClienteLocal::where('usuario_id', $cliente->id)->where('local_id', $local->id)->first();

        return [
            'nota' => $clienteLocal?->nota,
            'profesional_preferido_id' => $clienteLocal?->profesional_preferido_id,
            ...$this->resumenVisitas($cliente->id, $local->id),
        ];
    }

    /**
     * El staff actualiza `nota`/`profesional_preferido_id`. La fila no existe
     * necesariamente todavía —ya no se crea sola al completar una cita— así
     * que este es el punto real donde nace, la primera vez que alguien anota
     * algo sobre este cliente en este local.
     *
     * @param  array{nota?: ?string, profesional_preferido_id?: ?string}  $datos
     */
    public function actualizar(Local $local, Usuario $cliente, array $datos): ClienteLocal
    {
        if (array_key_exists('profesional_preferido_id', $datos) && $datos['profesional_preferido_id'] !== null) {
            $trabajaEnElLocal = Asignacion::where('local_id', $local->id)
                ->where('profesional_id', $datos['profesional_preferido_id'])
                ->vigente()
                ->exists();

            if (! $trabajaEnElLocal) {
                throw_validacion('El profesional preferido no tiene asignación vigente en este local.', 'profesional_preferido_id');
            }
        }

        $clienteLocal = ClienteLocal::firstOrNew([
            'usuario_id' => $cliente->id,
            'local_id' => $local->id,
        ]);

        $clienteLocal->fill($datos)->save();

        return $clienteLocal;
    }

    /**
     * Bandeja mensual (§4.7): un cliente por fila, con su recurrencia en ese
     * mes — una sola consulta `GROUP BY`, no un N+1 por cliente. Cacheada
     * (puro reporte, no gatilla ninguna decisión de agendamiento — a
     * diferencia de `disponibilidad_dia`, no se invalida por evento).
     */
    public function bandejaMensual(Local $local, CarbonImmutable $mes): Collection
    {
        $clave = "cliente_local:bandeja:{$local->id}:{$mes->format('Y-m')}";

        return Cache::remember($clave, self::TTL_BANDEJA_SEGUNDOS, fn () => DB::table('cita as c')
            ->join('usuario as u', 'u.id', '=', 'c.cliente_id')
            ->select('c.cliente_id')
            ->selectRaw('u.nombre as cliente_nombre')
            ->selectRaw('COUNT(*) as visitas_en_el_mes')
            ->selectRaw('MAX(c.inicio) as ultima_visita')
            ->where('c.local_id', $local->id)
            ->where('c.estado', 'completada')
            ->whereBetween('c.inicio', [$mes->startOfMonth(), $mes->endOfMonth()])
            ->groupBy('c.cliente_id', 'u.nombre')
            ->orderByDesc('visitas_en_el_mes')
            ->get());
    }

    /**
     * @return array{total_citas: int, primera_cita_at: ?string, ultima_cita_at: ?string}
     */
    private function resumenVisitas(string $clienteId, string $localId): array
    {
        $resumen = DB::table('cita')
            ->selectRaw('COUNT(*) as total_citas')
            ->selectRaw('MIN(inicio) as primera_cita_at')
            ->selectRaw('MAX(inicio) as ultima_cita_at')
            ->where('cliente_id', $clienteId)
            ->where('local_id', $localId)
            ->where('estado', 'completada')
            ->first();

        return [
            'total_citas' => (int) $resumen->total_citas,
            'primera_cita_at' => $resumen->primera_cita_at === null ? null : CarbonImmutable::parse($resumen->primera_cita_at)->toIso8601String(),
            'ultima_cita_at' => $resumen->ultima_cita_at === null ? null : CarbonImmutable::parse($resumen->ultima_cita_at)->toIso8601String(),
        ];
    }
}
