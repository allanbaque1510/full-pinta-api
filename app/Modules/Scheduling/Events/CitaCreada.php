<?php

namespace App\Modules\Scheduling\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se agendó una cita (hold, walk-in, o una que nace `confirmada` directo por
 * `cliente_perfil.requiere_confirmacion` — §5.6). Nombrado en §12.3.
 */
final class CitaCreada
{
    use Dispatchable;

    public function __construct(
        public readonly string $citaId,
    ) {}
}
