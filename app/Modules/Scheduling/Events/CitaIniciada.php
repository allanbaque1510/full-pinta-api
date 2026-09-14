<?php

namespace App\Modules\Scheduling\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** El cliente llegó (`confirmada` → `en_curso`, §6). */
final class CitaIniciada
{
    use Dispatchable;

    public function __construct(
        public readonly string $citaId,
    ) {}
}
