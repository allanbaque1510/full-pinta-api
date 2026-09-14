<?php

namespace App\Modules\Staffing\Application;

use App\Models\Asignacion;
use App\Models\Local;
use App\Models\Profesional;
use Illuminate\Database\Eloquent\Collection;

/**
 * El vínculo laboral entre un profesional y un local, SIN horarios (§4.6) —
 * los horarios son `Turno`, un modelo aparte. Un profesional puede tener
 * varias asignaciones vigentes a la vez, en locales distintos.
 */
final readonly class AsignacionService
{
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
        return Asignacion::create([
            'local_id' => $local->id,
            'profesional_id' => $profesional->id,
            'rol' => $datos['rol'],
            'modalidad' => $datos['modalidad'],
            'comision_pct' => $datos['comision_pct'],
            'desde' => $datos['desde'] ?? now()->toDateString(),
        ]);
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
     */
    public function terminar(Asignacion $asignacion, ?string $hasta = null): Asignacion
    {
        $asignacion->update(['hasta' => $hasta ?? now()->toDateString()]);

        return $asignacion;
    }
}
