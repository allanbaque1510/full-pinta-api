<?php

namespace App\Modules\Scheduling\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** El cliente confirmó su hold (`reservada` → `confirmada`, §6). */
final class CitaConfirmada
{
    use Dispatchable;

    public function __construct(
        public readonly string $citaId,
    ) {}
}
