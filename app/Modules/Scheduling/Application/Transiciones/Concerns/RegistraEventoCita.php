<?php

namespace App\Modules\Scheduling\Application\Transiciones\Concerns;

use App\Models\Cita;
use App\Models\CitaEvento;
use App\Models\Usuario;
use App\Support\Auth\ContextoAcceso;

/**
 * Cada transición de estado escribe una fila en `cita_evento` (§6) — la
 * auditoría inmutable que responde "¿de quién fue esta cita y qué le pasó"
 * cuando hay un reclamo de comisión.
 */
trait RegistraEventoCita
{
    /**
     * @param  array<string,mixed>  $payload
     */
    private function registrarEvento(
        Cita $cita,
        ?string $estadoAnterior,
        string $estadoNuevo,
        ?Usuario $actor,
        array $payload = [],
    ): void {
        CitaEvento::create([
            'cita_id' => $cita->id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'actor_usuario_id' => $actor?->id,
            'actor_rol' => $this->actorRol($cita, $actor),
            'payload' => $payload === [] ? null : $payload,
        ]);
    }

    /** `null` actor = disparado por el sistema (job de expiración de holds). */
    private function actorRol(Cita $cita, ?Usuario $actor): ?string
    {
        if ($actor === null) {
            return 'sistema';
        }

        if ($actor->id === $cita->cliente_id) {
            return 'cliente';
        }

        $cita->loadMissing('local');

        return (new ContextoAcceso($actor))->rolEnLocal($cita->local);
    }
}
