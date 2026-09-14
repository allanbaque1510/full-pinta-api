<?php

namespace App\Modules\Scheduling\Application\Transiciones;

use App\Models\Cita;
use App\Models\Usuario;
use App\Modules\Scheduling\Application\Transiciones\Concerns\RegistraEventoCita;
use App\Modules\Scheduling\Events\CitaConfirmada;

/** `reservada` → `confirmada` (§6). */
final readonly class ConfirmarCita
{
    use RegistraEventoCita;

    public function __invoke(Cita $cita, ?Usuario $actor): Cita
    {
        if ($cita->estado !== 'reservada') {
            throw_validacion('Solo se puede confirmar una cita en estado "reservada".', 'estado');
        }

        $cita->update(['estado' => 'confirmada', 'confirmada_at' => now()]);

        $this->registrarEvento($cita, 'reservada', 'confirmada', $actor);

        CitaConfirmada::dispatch($cita->id);

        return $cita;
    }
}
