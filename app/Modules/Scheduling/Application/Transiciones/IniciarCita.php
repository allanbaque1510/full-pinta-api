<?php

namespace App\Modules\Scheduling\Application\Transiciones;

use App\Models\Cita;
use App\Models\Usuario;
use App\Modules\Scheduling\Application\Transiciones\Concerns\RegistraEventoCita;
use App\Modules\Scheduling\Events\CitaIniciada;

/** `confirmada` → `en_curso` — el cliente llegó (§6). */
final readonly class IniciarCita
{
    use RegistraEventoCita;

    public function __invoke(Cita $cita, ?Usuario $actor): Cita
    {
        if ($cita->estado !== 'confirmada') {
            throw_validacion('Solo se puede iniciar una cita en estado "confirmada".', 'estado');
        }

        $cita->update(['estado' => 'en_curso']);

        $this->registrarEvento($cita, 'confirmada', 'en_curso', $actor);

        CitaIniciada::dispatch($cita->id);

        return $cita;
    }
}
