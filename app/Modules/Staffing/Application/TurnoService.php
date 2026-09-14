<?php

namespace App\Modules\Staffing\Application;

use App\Models\Asignacion;
use App\Models\Turno;
use App\Modules\Staffing\Events\TurnoModificado;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;

/**
 * El horario recurrente de un profesional (§4.6, §4.11). El constraint
 * `turno_sin_traslape` (creado en la Fase 0) es la única fuente de verdad
 * contra solapamientos — este servicio no valida traslapes por su cuenta, los
 * captura cuando Postgres los rechaza y los traduce a un error legible. Camino
 * optimista, igual que agendar una cita (§5.3): más barato que precalcular
 * traslapes en PHP, y es la base la que de verdad no puede equivocarse.
 */
final readonly class TurnoService
{
    /** SQLSTATE de violación de un constraint EXCLUDE. */
    private const EXCLUSION_VIOLATION = '23P01';

    public function listarPorAsignacion(Asignacion $asignacion): Collection
    {
        return $asignacion->turnos;
    }

    /**
     * `profesional_id`/`local_id` se copian de la asignación, nunca del
     * request: están desnormalizados solo para el constraint, y si no
     * coincidieran con la asignación real, el constraint protegería datos
     * que no corresponden (ver skill `migracion`).
     */
    public function crear(Asignacion $asignacion, array $datos): Turno
    {
        $this->validarHoras($datos['entra'] ?? null, $datos['sale'] ?? null);

        $turno = $this->capturandoTraslape(fn () => Turno::create([
            'asignacion_id' => $asignacion->id,
            'profesional_id' => $asignacion->profesional_id,
            'local_id' => $asignacion->local_id,
            ...$datos,
        ]));

        TurnoModificado::dispatch($turno->local_id, $turno->profesional_id);

        return $turno;
    }

    public function actualizar(Turno $turno, array $datos): Turno
    {
        // `substr(...,0,5)`: lo que ya está en la fila viene con segundos
        // ("09:00:00"), lo que llega del request es "H:i" — mismo desajuste
        // de formato que en `HorarioLocalService::actualizar()`.
        $this->validarHoras(
            $datos['entra'] ?? substr($turno->entra, 0, 5),
            $datos['sale'] ?? substr($turno->sale, 0, 5),
        );

        $turno = $this->capturandoTraslape(function () use ($turno, $datos) {
            $turno->update($datos);

            return $turno;
        });

        TurnoModificado::dispatch($turno->local_id, $turno->profesional_id);

        return $turno;
    }

    private function validarHoras(?string $entra, ?string $sale): void
    {
        if ($entra !== null && $sale !== null && $sale <= $entra) {
            throw_validacion('La hora de salida debe ser posterior a la de entrada.', 'sale');
        }
    }

    public function eliminar(Turno $turno): void
    {
        // Tabla sin `activo`/`estado`: se borra de verdad (§4.2).
        $turno->delete();

        TurnoModificado::dispatch($turno->local_id, $turno->profesional_id);
    }

    private function capturandoTraslape(callable $operacion): Turno
    {
        try {
            return $operacion();
        } catch (QueryException $e) {
            if ($e->getCode() !== self::EXCLUSION_VIOLATION) {
                throw $e;
            }

            throw_validacion(
                'Ese horario se traslapa con otro turno de este profesional (en este local o en otro).',
                'entra',
            );
        }
    }
}
