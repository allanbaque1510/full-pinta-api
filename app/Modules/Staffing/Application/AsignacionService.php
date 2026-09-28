<?php

namespace App\Modules\Staffing\Application;

use App\Models\Asignacion;
use App\Models\Local;
use App\Models\Profesional;
use App\Models\Turno;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * El vínculo laboral entre un profesional y un local, SIN horarios (§4.6) —
 * los horarios son `Turno`, un modelo aparte. Un profesional puede tener
 * varias asignaciones vigentes a la vez, en locales distintos — pero nunca
 * dos vigentes a la vez para el MISMO (local, profesional): lo garantiza
 * `asignacion_una_vigente` (índice único parcial sobre `hasta IS NULL`).
 */
final readonly class AsignacionService
{
    /** SQLSTATE de violación de unicidad. */
    private const UNIQUE_VIOLATION = '23505';

    public function listarPorLocal(Local $local): Collection
    {
        return $local->asignaciones()->vigente()->with('profesional')->get();
    }

    /**
     * Suma un profesional que YA EXISTE a un local nuevo — para un profesional
     * que recién se crea, ver `ProfesionalService::crearConAsignacion()`.
     */
    public function crear(Local $local, Profesional $profesional, array $datos): Asignacion
    {
        try {
            // `DB::transaction()` en vez de un `create()` suelto: si ya
            // estamos dentro de otra transacción (la de `RefreshDatabase` en
            // tests, o cualquier transacción del caller), Postgres exige un
            // `SAVEPOINT` para recuperarse de una violación de constraint sin
            // abortar TODA la transacción externa (mismo patrón documentado
            // en `NotificacionService::crear()`).
            return DB::transaction(fn () => Asignacion::create([
                'local_id' => $local->id,
                'profesional_id' => $profesional->id,
                'rol' => $datos['rol'],
                'modalidad' => $datos['modalidad'],
                'comision_pct' => $datos['comision_pct'],
                'desde' => $datos['desde'] ?? now()->toDateString(),
            ]));
        } catch (QueryException $e) {
            if ($e->getCode() !== self::UNIQUE_VIOLATION) {
                throw $e;
            }

            throw_validacion(
                'Este profesional ya tiene una asignación vigente en este local; termínala antes de crear una nueva.',
                'profesional_id',
            );
        }
    }

    public function actualizar(Asignacion $asignacion, array $datos): Asignacion
    {
        $asignacion->update($datos);

        return $asignacion;
    }

    /**
     * No se borra: se termina, poniendo `hasta` (§4.2 — un tercer patrón de
     * "borrado", además de `activo` y `estado`: aquí es una fecha de fin de
     * vigencia). Los turnos y citas ya generados bajo esta asignación quedan
     * intactos.
     *
     * Cierra también los `turno` vigentes de esa asignación en la misma
     * fecha — si no, el motor de disponibilidad seguiría ofreciendo horarios
     * con un profesional que ya no trabaja ahí (revisión de base de datos,
     * 2026-09-28: `DisponibilidadService` verifica la asignación vigente,
     * pero eso no sirve de nada si el `turno` nunca se cierra).
     */
    public function terminar(Asignacion $asignacion, ?string $hasta = null): Asignacion
    {
        $fechaFin = $hasta ?? now()->toDateString();

        DB::transaction(function () use ($asignacion, $fechaFin) {
            $asignacion->update(['hasta' => $fechaFin]);

            Turno::where('asignacion_id', $asignacion->id)
                ->whereNull('vigente_hasta')
                ->update(['vigente_hasta' => $fechaFin]);
        });

        return $asignacion;
    }
}
