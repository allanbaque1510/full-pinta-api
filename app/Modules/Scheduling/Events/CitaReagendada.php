<?php

namespace App\Modules\Scheduling\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Reagendar NO es cancelar + crear (§4.7, §6): no penaliza al cliente y
 * conserva la trazabilidad vía `reagendada_de_id`. Nombrado en §12.3.
 */
final class CitaReagendada
{
    use Dispatchable;

    public function __construct(
        public readonly string $citaAnteriorId,
        public readonly string $citaNuevaId,
    ) {}
}
