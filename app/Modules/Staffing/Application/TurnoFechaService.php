<?php

namespace App\Modules\Staffing\Application;

use App\Models\Local;
use App\Models\Profesional;
use App\Models\TurnoFecha;
use App\Modules\Staffing\Events\TurnoModificado;
use Illuminate\Database\Eloquent\Collection;

/**
 * Overrides por fecha concreta ("este sábado no voy a Urdesa, voy a
 * Alborada") — no modifica el turno recurrente (§4.6). El cálculo de
 * disponibilidad (Fase 5) aplica primero los recurrentes vigentes y luego
 * estos.
 */
final readonly class TurnoFechaService
{
    public function listarPorProfesional(Profesional $profesional): Collection
    {
        return $profesional->turnoFechas()->orderBy('fecha')->get();
    }

    /**
     * @param  array{fecha: string, tipo: string, entra?: ?string, sale?: ?string, nota?: ?string}  $datos
     */
    public function crear(Profesional $profesional, Local $local, array $datos): TurnoFecha
    {
        if ($datos['tipo'] === 'cancela') {
            $datos['entra'] = null;
            $datos['sale'] = null;
        } elseif (($datos['entra'] ?? null) === null || ($datos['sale'] ?? null) === null) {
            throw_validacion('Un override que no cancela necesita hora de entrada y de salida.', 'entra');
        } elseif ($datos['sale'] <= $datos['entra']) {
            throw_validacion('La hora de salida debe ser posterior a la de entrada.', 'sale');
        }

        $turnoFecha = TurnoFecha::create([
            'profesional_id' => $profesional->id,
            'local_id' => $local->id,
            ...$datos,
        ]);

        TurnoModificado::dispatch($turnoFecha->local_id, $turnoFecha->profesional_id);

        return $turnoFecha;
    }

    public function eliminar(TurnoFecha $turnoFecha): void
    {
        $turnoFecha->delete();

        TurnoModificado::dispatch($turnoFecha->local_id, $turnoFecha->profesional_id);
    }
}
