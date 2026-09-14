<?php

namespace App\Modules\Scheduling\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se liberó el cupo que esta espera pedía y quedó `notificada` (§4.7). El
 * cliente que la escribió es a quien avisar primero (§11.2, "cupo liberado").
 */
final class EsperaNotificada
{
    use Dispatchable;

    public function __construct(
        public readonly string $esperaId,
        public readonly string $citaLiberadaId,
    ) {}
}
