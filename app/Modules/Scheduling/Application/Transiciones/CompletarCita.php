<?php

namespace App\Modules\Scheduling\Application\Transiciones;

use App\Models\Cita;
use App\Models\Usuario;
use App\Modules\Scheduling\Application\Transiciones\Concerns\RegistraEventoCita;
use App\Modules\Scheduling\Events\CitaCompletada;

/**
 * `en_curso` → `completada` (§6). Solo este estado habilita reseña y cuenta
 * para ranking del local y liquidación de comisiones.
 *
 * No toca `cliente_local`: sus contadores de visitas se calculan en vivo
 * contra `cita` (ver `ClienteLocalService`), no se mantienen como estado
 * guardado — revisión de base de datos, 2026-09-29.
 */
final readonly class CompletarCita
{
    use RegistraEventoCita;

    public function __invoke(Cita $cita, ?Usuario $actor, ?float $propina = null): Cita
    {
        if ($cita->estado !== 'en_curso') {
            throw_validacion('Solo se puede completar una cita en estado "en_curso".', 'estado');
        }

        $cita->update([
            'estado' => 'completada',
            'completada_at' => now(),
            'propina' => $propina ?? $cita->propina,
        ]);

        $this->registrarEvento($cita, 'en_curso', 'completada', $actor, $propina !== null ? ['propina' => $propina] : []);

        CitaCompletada::dispatch($cita->id);

        return $cita;
    }
}
