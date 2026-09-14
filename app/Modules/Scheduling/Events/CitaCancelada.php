<?php

namespace App\Modules\Scheduling\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * La cita dejó de ocupar el slot sin completarse (§6, §12.3): cubre
 * `cancelada_cliente`, `cancelada_local`, `no_show` y `expirada` — la
 * especificación no nombra un evento propio para cada una, y las cuatro
 * comparten la misma consecuencia real (liberar el slot y avisar a la lista
 * de espera). `$estado` distingue cuál fue.
 */
final class CitaCancelada
{
    use Dispatchable;

    public function __construct(
        public readonly string $citaId,
        public readonly string $estado,
        public readonly bool $tardia = false,
    ) {}
}
