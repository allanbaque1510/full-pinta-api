<?php

namespace App\Modules\Staffing\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Una `excepcion` (de local, de profesional o de recurso) se creó o se borró
 * (§12.3, §12.5). Exactamente uno de los tres ids viene lleno, igual que en la
 * tabla `excepcion` (constraint `objetivo`).
 *
 * `Scheduling` escucha esto para invalidar la caché de disponibilidad.
 */
final class ExcepcionModificada
{
    use Dispatchable;

    public function __construct(
        public readonly ?string $localId = null,
        public readonly ?string $profesionalId = null,
        public readonly ?string $recursoId = null,
    ) {}
}
