<?php

namespace App\Modules\Scheduling\Listeners;

use App\Models\Cita;
use App\Models\Local;
use App\Models\Profesional;
use App\Modules\Scheduling\Events\CitaCancelada;
use App\Modules\Scheduling\Events\CitaCreada;
use App\Modules\Scheduling\Jobs\ReconstruirDisponibilidadDia;
use App\Modules\Staffing\Events\ExcepcionModificada;
use App\Modules\Staffing\Events\TurnoModificado;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * `Scheduling` escucha los cambios de `Staffing`, nunca al revés (§12.3): la
 * caché de disponibilidad se invalida POR EVENTO, no solo por TTL — si
 * cambia un turno o una excepción, el slot tiene que reflejarlo ya, no en
 * 15 minutos (§12.5).
 */
class InvalidarCacheDisponibilidad
{
    /** Tope duro aunque el local tenga un `horizonte_dias` mayor. */
    private const HORIZONTE_MAXIMO_DIAS = 90;

    public function handleTurnoModificado(TurnoModificado $event): void
    {
        $local = Local::find($event->localId);
        $profesional = Profesional::find($event->profesionalId);

        if ($local === null || $profesional === null) {
            return;
        }

        $this->invalidarRango($local, [$profesional]);
    }

    public function handleExcepcionModificada(ExcepcionModificada $event): void
    {
        // Excepción de LOCAL sola: afecta a todos los profesionales que
        // trabajan ahí.
        if ($event->localId !== null && $event->profesionalId === null) {
            $local = Local::find($event->localId);

            if ($local === null) {
                return;
            }

            $profesionales = Profesional::whereHas(
                'asignaciones',
                fn ($q) => $q->where('local_id', $local->id)->vigente(),
            )->get();

            $this->invalidarRango($local, $profesionales);

            return;
        }

        // Excepción de PROFESIONAL (con o sin local): afecta solo ese
        // profesional, en el/los local(es) que corresponda.
        if ($event->profesionalId !== null) {
            $profesional = Profesional::find($event->profesionalId);

            if ($profesional === null) {
                return;
            }

            $locales = $event->localId !== null
                ? Local::where('id', $event->localId)->get()
                : Local::whereHas(
                    'asignaciones',
                    fn ($q) => $q->where('profesional_id', $profesional->id)->vigente(),
                )->get();

            foreach ($locales as $local) {
                $this->invalidarRango($local, [$profesional]);
            }

            return;
        }

        // Solo `recurso_id`: no afecta esta caché — la disponibilidad de un
        // recurso se consulta fresca, no está cacheada (ver DisponibilidadService).
    }

    /**
     * Revisión de base de datos 2026-09-28: `disponibilidad_dia` (la
     * proyección que consulta la búsqueda para "disponible hoy/mañana") nunca
     * se recalculaba al crear o cancelar una cita — solo reaccionaba a
     * cambios de `turno`/`excepcion`. Un local podía quedar sin cupos reales
     * y la búsqueda seguía mostrándolo disponible hasta que, por coincidencia,
     * algo más disparara la reconstrucción de ese día.
     */
    public function handleCitaCreada(CitaCreada $event): void
    {
        $this->invalidarPorCita($event->citaId);
    }

    public function handleCitaCancelada(CitaCancelada $event): void
    {
        $this->invalidarPorCita($event->citaId);
    }

    private function invalidarPorCita(string $citaId): void
    {
        $cita = Cita::find($citaId);

        if ($cita === null) {
            return;
        }

        $local = Local::find($cita->local_id);
        $profesional = Profesional::find($cita->profesional_id);

        if ($local === null || $profesional === null) {
            return;
        }

        // Normalmente `inicio` y `fin` caen en el mismo día; si la cita cruza
        // medianoche, se invalidan ambos días para no dejar uno desactualizado.
        $fechas = collect([$cita->inicio->toDateString(), $cita->fin->toDateString()])->unique();

        foreach ($fechas as $fechaString) {
            $fecha = CarbonImmutable::parse($fechaString);

            Cache::forget("disponibilidad:{$local->id}:{$profesional->id}:{$fecha->toDateString()}");
            ReconstruirDisponibilidadDia::dispatch($local, $fecha)->onQueue('proyecciones');
        }
    }

    /**
     * @param  iterable<Profesional>  $profesionales
     */
    private function invalidarRango(Local $local, iterable $profesionales): void
    {
        $dias = min($local->horizonte_dias, self::HORIZONTE_MAXIMO_DIAS);
        $hoy = CarbonImmutable::today();

        for ($i = 0; $i <= $dias; $i++) {
            $fecha = $hoy->addDays($i);

            foreach ($profesionales as $profesional) {
                Cache::forget("disponibilidad:{$local->id}:{$profesional->id}:{$fecha->toDateString()}");
            }

            ReconstruirDisponibilidadDia::dispatch($local, $fecha)->onQueue('proyecciones');
        }
    }
}
