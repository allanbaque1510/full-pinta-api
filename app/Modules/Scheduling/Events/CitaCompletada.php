<?php

namespace App\Modules\Scheduling\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * `en_curso` → `completada` (§6). Solo este estado habilita reseña y cuenta
 * para ranking del local y liquidación de comisiones. Nombrado en §12.3.
 */
final class CitaCompletada
{
    use Dispatchable;

    public function __construct(
        public readonly string $citaId,
    ) {}
}
