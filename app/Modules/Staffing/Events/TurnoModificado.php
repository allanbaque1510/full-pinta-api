<?php

namespace App\Modules\Staffing\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un `turno` o `turno_fecha` de un profesional cambió (§12.3).
 *
 * `Scheduling` escucha esto para invalidar la caché de disponibilidad y
 * reconstruir la proyección `disponibilidad_dia` — nadie llama a Scheduling
 * directo, las reacciones entre módulos van por evento (§12.3).
 */
final class TurnoModificado
{
    use Dispatchable;

    public function __construct(
        public readonly string $localId,
        public readonly string $profesionalId,
    ) {}
}
