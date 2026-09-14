<?php

namespace App\Modules\Scheduling\Application;

use App\Models\Cita;
use App\Models\Excepcion;
use App\Models\Habilidad;
use App\Models\Local;
use App\Models\Profesional;
use App\Models\Recurso;
use App\Models\ServicioLocal;
use App\Models\Turno;
use App\Models\TurnoFecha;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * El motor de disponibilidad (§5.1): los 10 filtros que dicen qué horarios se
 * pueden ofrecer para agendar. Es de solo lectura — crear la cita de verdad es
 * `CitaService` (Fase 6).
 *
 * Nota de huso horario: `horario_local.abre/cierra` y `turno(_fecha).entra/sale`
 * son columnas `time` sin zona — se interpretan en UTC, igual que el resto de
 * la base (CLAUDE.md: "todo en UTC en base de datos"). No hay campo de huso
 * horario por local en el esquema (Ecuador continental es un solo huso, sin
 * horario de verano), así que combinarlas con la fecha pedida sin conversión
 * es la lectura más simple y consistente con esa regla.
 *
 * Los filtros que NO dependen del servicio pedido (1, 2, 4-local/profesional,
 * 5, 6, 8, 9) viven en `ventanasLibres()`, que es la unidad que se cachea por
 * (local, profesional, día) — §12.5. Los que sí dependen del servicio (3,
 * duración, 10, 7-recurso) viven en `slots()`.
 */
final readonly class DisponibilidadService
{
    private const GRANULARIDAD_MINUTOS = 15;

    private const TTL_CACHE_SEGUNDOS = 900;

    /**
     * @param  array<int,string>  $servicioLocalIds
     */
    public function slots(Local $local, CarbonImmutable $fecha, array $servicioLocalIds, ?string $profesionalId = null): Collection
    {
        $servicios = ServicioLocal::with('catalogoServicio.tipoRecurso')->whereIn('id', $servicioLocalIds)->get();

        if ($servicios->count() !== count($servicioLocalIds)) {
            throw_validacion('Alguno de los servicios pedidos no existe o no está activo.', 'servicios');
        }

        $duracionTotal = (int) $servicios->sum('duracion_min') + (int) $servicios->max('buffer_min');

        // Supuesto documentado: todos los servicios de una misma cita comparten
        // el mismo tipo de recurso requerido (o ninguno). La especificación no
        // cubre composición multi-recurso — no está en el alcance de la v1.
        $tipoRecursoId = $servicios->first()->catalogoServicio->tipoRecurso->codigo === 'ninguno'
            ? null
            : $servicios->first()->catalogoServicio->tipo_recurso_id;

        $profesionales = $this->profesionalesElegibles($local, $fecha, $servicioLocalIds, $profesionalId);

        $slots = collect();

        foreach ($profesionales as $profesional) {
            foreach ($this->ventanasLibres($local, $profesional, $fecha) as [$inicioVentana, $finVentana]) {
                $candidato = $this->alinearA15Minutos($inicioVentana);

                while (true) {
                    $finCandidato = $candidato->addMinutes($duracionTotal);

                    if ($finCandidato->gt($finVentana)) {
                        break;
                    }

                    $recursoId = null;

                    if ($tipoRecursoId !== null) {
                        $recurso = $this->recursoLibre($local, $tipoRecursoId, $candidato, $finCandidato);

                        if ($recurso === null) {
                            $candidato = $candidato->addMinutes(self::GRANULARIDAD_MINUTOS);

                            continue;
                        }

                        $recursoId = $recurso->id;
                    }

                    $slots->push(new Slot($profesional->id, $recursoId, $candidato, $finCandidato));
                    $candidato = $candidato->addMinutes(self::GRANULARIDAD_MINUTOS);
                }
            }
        }

        return $slots->sortBy([
            fn (Slot $a, Slot $b) => $a->inicio->timestamp <=> $b->inicio->timestamp,
            fn (Slot $a, Slot $b) => $a->profesionalId <=> $b->profesionalId,
        ])->values();
    }

    /**
     * Resumen grueso para la proyección `disponibilidad_dia` (§8, §12.5): NO
     * filtra por servicio ni por recurso, solo cuenta instantes de 15 min en
     * los que AL MENOS UN profesional del local está libre. Es un read-model
     * aproximado para la búsqueda ("disponible hoy"), no para agendar.
     *
     * @return array{slots_libres:int, primer_slot:?CarbonImmutable, ultimo_slot:?CarbonImmutable}
     */
    public function resumenDia(Local $local, CarbonImmutable $fecha): array
    {
        $profesionales = Profesional::whereHas(
            'asignaciones',
            fn ($q) => $q->where('local_id', $local->id)->vigenteEn($fecha),
        )->get();

        $inicios = collect();

        foreach ($profesionales as $profesional) {
            foreach ($this->ventanasLibres($local, $profesional, $fecha) as [$inicio, $fin]) {
                $candidato = $this->alinearA15Minutos($inicio);

                while ($candidato->lt($fin)) {
                    $inicios->push($candidato);
                    $candidato = $candidato->addMinutes(self::GRANULARIDAD_MINUTOS);
                }
            }
        }

        $inicios = $inicios->unique(fn (CarbonImmutable $c) => $c->timestamp)->sort();

        return [
            'slots_libres' => $inicios->count(),
            'primer_slot' => $inicios->first(),
            'ultimo_slot' => $inicios->last(),
        ];
    }

    /**
     * Ventanas libres de un profesional en un local, un día concreto (§5.1,
     * filtros 1, 2, 4-local/profesional, 5, 6, 8, 9). Unidad cacheada 15 min,
     * invalidada por evento (`TurnoModificado`/`ExcepcionModificada`) — nunca
     * solo por TTL, porque cancelar una cita tiene que liberar el slot ya.
     *
     * @return list<array{0:CarbonImmutable,1:CarbonImmutable}>
     */
    public function ventanasLibres(Local $local, Profesional $profesional, CarbonImmutable $fecha): array
    {
        $clave = "disponibilidad:{$local->id}:{$profesional->id}:{$fecha->toDateString()}";

        // `cache.serializable_classes = false` (default de seguridad de
        // Laravel) rechaza CUALQUIER objeto al leer de Redis, `CarbonImmutable`
        // incluido — devuelve `__PHP_Incomplete_Class` en silencio. Se cachean
        // las fechas como string ISO-8601 (siempre seguro) y se reconstruyen al
        // leer.
        $ventanas = Cache::remember(
            $clave,
            self::TTL_CACHE_SEGUNDOS,
            fn () => collect($this->calcularVentanasLibres($local, $profesional, $fecha))
                ->map(fn (array $v) => [$v[0]->toIso8601String(), $v[1]->toIso8601String()])
                ->all(),
        );

        return collect($ventanas)
            ->map(fn (array $v) => [CarbonImmutable::parse($v[0]), CarbonImmutable::parse($v[1])])
            ->all();
    }

    /**
     * @return list<array{0:CarbonImmutable,1:CarbonImmutable}>
     */
    private function calcularVentanasLibres(Local $local, Profesional $profesional, CarbonImmutable $fecha): array
    {
        if ($local->estado !== 'activo') {
            return [];
        }

        $diaSemana = $fecha->dayOfWeek;

        $ventanasLocal = $local->horarios()->where('dia_semana', $diaSemana)->get()
            ->map(fn ($h) => [$this->horaEnFecha($fecha, $h->abre), $this->horaEnFecha($fecha, $h->cierra)])
            ->all();

        if ($ventanasLocal === []) {
            return [];
        }

        $ventanas = $this->ventanasTurno($local, $profesional, $fecha, $diaSemana);

        // Filtro 1: no se puede trabajar fuera del horario del local, aunque
        // el turno diga otra cosa (el más restrictivo de los dos manda).
        $ventanas = collect($ventanasLocal)
            ->flatMap(fn (array $vLocal) => $this->intersectarIntervalo($ventanas, $vLocal))
            ->all();

        // Filtro 4 (local y profesional, no recurso — eso es filtro 7).
        foreach ($this->excepcionesAplicables($local, $profesional, $fecha) as $bloqueo) {
            $ventanas = $this->restarIntervalo($ventanas, $bloqueo);
        }

        // Filtros 5 y 6: citas existentes del profesional en cualquier local,
        // con `traslado_min` de colchón si la cita adyacente es de otro local.
        foreach ($this->bloqueosPorCitas($profesional, $fecha, $local) as $bloqueo) {
            $ventanas = $this->restarIntervalo($ventanas, $bloqueo);
        }

        // Filtros 8 y 9: anticipación mínima y horizonte máximo.
        $limite = [
            CarbonImmutable::now()->addMinutes($local->lead_time_min),
            CarbonImmutable::now()->addDays($local->horizonte_dias),
        ];

        return collect($ventanas)
            ->flatMap(fn (array $v) => $this->intersectarIntervalo([$v], $limite))
            ->values()
            ->all();
    }

    /**
     * Turno recurrente + overrides de `turno_fecha`, ya combinados (§4.6): la
     * especificación solo dice "los overrides se aplican después de los
     * recurrentes", así que la precedencia exacta queda documentada aquí, no
     * inferida en el momento de leer el código.
     *
     * Cada override es por (profesional, local, fecha) — nunca cruza locales:
     * "este sábado no voy a Urdesa, voy a Alborada" se modela con DOS filas,
     * una `cancela` en Urdesa y una `extra`/`reemplaza` en Alborada, no con
     * una sola fila que "mueva" al profesional de un local a otro.
     *
     * - `reemplaza` sustituye el turno recurrente de ESE local ese día.
     * - `cancela` (si no hay `reemplaza`) lo anula sin reemplazo.
     * - `extra` se suma siempre, haya o no recurrente.
     *
     * @return list<array{0:CarbonImmutable,1:CarbonImmutable}>
     */
    private function ventanasTurno(Local $local, Profesional $profesional, CarbonImmutable $fecha, int $diaSemana): array
    {
        $recurrentes = Turno::where('profesional_id', $profesional->id)
            ->where('local_id', $local->id)
            ->where('dia_semana', $diaSemana)
            ->vigenteEn($fecha)
            ->get()
            ->map(fn (Turno $t) => [$this->horaEnFecha($fecha, $t->entra), $this->horaEnFecha($fecha, $t->sale)]);

        $overrides = TurnoFecha::where('profesional_id', $profesional->id)
            ->where('local_id', $local->id)
            ->whereDate('fecha', $fecha)
            ->get();

        $reemplazos = $overrides->where('tipo', 'reemplaza');
        $hayCancela = $overrides->contains('tipo', 'cancela');
        $extras = $overrides->where('tipo', 'extra');

        $base = $reemplazos->isNotEmpty()
            ? $reemplazos->map(fn (TurnoFecha $o) => [$this->horaEnFecha($fecha, $o->entra), $this->horaEnFecha($fecha, $o->sale)])
            : ($hayCancela ? collect() : $recurrentes);

        return $base->merge(
            $extras->map(fn (TurnoFecha $o) => [$this->horaEnFecha($fecha, $o->entra), $this->horaEnFecha($fecha, $o->sale)]),
        )->all();
    }

    /**
     * Excepciones de LOCAL (bloquea a todos ahí) o de PROFESIONAL (bloquea en
     * ese local si también trae `local_id`, o en todos si no — §4.6). Las de
     * recurso se resuelven en `recursoLibre()`, no aquí.
     *
     * @return list<array{0:CarbonImmutable,1:CarbonImmutable}>
     */
    private function excepcionesAplicables(Local $local, Profesional $profesional, CarbonImmutable $fecha): array
    {
        $diaInicio = $fecha->startOfDay();
        $diaFin = $fecha->endOfDay();

        return Excepcion::whereNull('recurso_id')
            ->where(fn ($q) => $q
                ->where(fn ($q2) => $q2->where('local_id', $local->id)->whereNull('profesional_id'))
                ->orWhere(fn ($q2) => $q2->where('profesional_id', $profesional->id)->whereNull('local_id'))
                ->orWhere(fn ($q2) => $q2->where('profesional_id', $profesional->id)->where('local_id', $local->id)))
            ->where('fecha_inicio', '<', $diaFin)
            ->where('fecha_fin', '>', $diaInicio)
            ->get()
            ->map(fn (Excepcion $e) => [
                $e->fecha_inicio->gt($diaInicio) ? $e->fecha_inicio : $diaInicio,
                $e->fecha_fin->lt($diaFin) ? $e->fecha_fin : $diaFin,
            ])
            ->all();
    }

    /**
     * Citas que ya ocupan al profesional (§5.1 filtros 5 y 6), en cualquier
     * local. Si la cita es de OTRO local, se le suma `traslado_min` de
     * colchón a cada lado — la base no sabe de geografía, esto no lo puede
     * validar ningún constraint.
     *
     * @return list<array{0:CarbonImmutable,1:CarbonImmutable}>
     */
    private function bloqueosPorCitas(Profesional $profesional, CarbonImmutable $fecha, Local $local): array
    {
        return Cita::query()->ocupanSlot()
            ->where('profesional_id', $profesional->id)
            ->where('inicio', '<', $fecha->endOfDay()->addDay())
            ->where('fin', '>', $fecha->startOfDay()->subDay())
            ->get()
            ->map(function (Cita $cita) use ($local, $profesional) {
                $colchon = $cita->local_id === $local->id ? 0 : $profesional->traslado_min;

                return [$cita->inicio->subMinutes($colchon), $cita->fin->addMinutes($colchon)];
            })
            ->all();
    }

    private function recursoLibre(Local $local, string $tipoRecursoId, CarbonImmutable $inicio, CarbonImmutable $fin): ?Recurso
    {
        $recursos = Recurso::where('local_id', $local->id)
            ->where('tipo_recurso_id', $tipoRecursoId)
            ->where('activo', true)
            ->get();

        foreach ($recursos as $recurso) {
            $ocupado = Cita::query()->ocupanSlot()
                ->where('recurso_id', $recurso->id)
                ->where('inicio', '<', $fin)
                ->where('fin', '>', $inicio)
                ->exists();

            if ($ocupado) {
                continue;
            }

            $bloqueado = Excepcion::where('recurso_id', $recurso->id)
                ->where('fecha_inicio', '<', $fin)
                ->where('fecha_fin', '>', $inicio)
                ->exists();

            if (! $bloqueado) {
                return $recurso;
            }
        }

        return null;
    }

    /**
     * @param  array<int,string>  $servicioLocalIds
     */
    private function profesionalesElegibles(Local $local, CarbonImmutable $fecha, array $servicioLocalIds, ?string $profesionalId): EloquentCollection
    {
        $query = Profesional::whereHas(
            'asignaciones',
            fn ($q) => $q->where('local_id', $local->id)->vigenteEn($fecha),
        );

        if ($profesionalId !== null) {
            $query->where('id', $profesionalId);
        }

        // Filtro 3: el profesional necesita habilidad para TODOS los
        // servicios pedidos, no solo alguno.
        return $query->get()->filter(function (Profesional $profesional) use ($servicioLocalIds) {
            foreach ($servicioLocalIds as $servicioLocalId) {
                $tiene = Habilidad::where('profesional_id', $profesional->id)
                    ->where('servicio_local_id', $servicioLocalId)
                    ->exists();

                if (! $tiene) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    private function horaEnFecha(CarbonImmutable $fecha, string $hora): CarbonImmutable
    {
        [$horas, $minutos] = array_map('intval', explode(':', $hora));

        return $fecha->startOfDay()->addHours($horas)->addMinutes($minutos);
    }

    private function alinearA15Minutos(CarbonImmutable $momento): CarbonImmutable
    {
        $momento = $momento->startOfMinute();
        $resto = $momento->timestamp % (self::GRANULARIDAD_MINUTOS * 60);

        return $resto === 0 ? $momento : $momento->addSeconds(self::GRANULARIDAD_MINUTOS * 60 - $resto);
    }

    /**
     * @param  list<array{0:CarbonImmutable,1:CarbonImmutable}>  $ventanas
     * @param  array{0:CarbonImmutable,1:CarbonImmutable}  $ocupado
     * @return list<array{0:CarbonImmutable,1:CarbonImmutable}>
     */
    private function restarIntervalo(array $ventanas, array $ocupado): array
    {
        $resultado = [];

        foreach ($ventanas as [$inicio, $fin]) {
            if ($ocupado[1]->lte($inicio) || $ocupado[0]->gte($fin)) {
                $resultado[] = [$inicio, $fin];

                continue;
            }

            if ($ocupado[0]->gt($inicio)) {
                $resultado[] = [$inicio, $ocupado[0]];
            }

            if ($ocupado[1]->lt($fin)) {
                $resultado[] = [$ocupado[1], $fin];
            }
        }

        return $resultado;
    }

    /**
     * @param  list<array{0:CarbonImmutable,1:CarbonImmutable}>  $ventanas
     * @param  array{0:CarbonImmutable,1:CarbonImmutable}  $limite
     * @return list<array{0:CarbonImmutable,1:CarbonImmutable}>
     */
    private function intersectarIntervalo(array $ventanas, array $limite): array
    {
        $resultado = [];

        foreach ($ventanas as [$inicio, $fin]) {
            $nuevoInicio = $inicio->gt($limite[0]) ? $inicio : $limite[0];
            $nuevoFin = $fin->lt($limite[1]) ? $fin : $limite[1];

            if ($nuevoInicio->lt($nuevoFin)) {
                $resultado[] = [$nuevoInicio, $nuevoFin];
            }
        }

        return $resultado;
    }
}
