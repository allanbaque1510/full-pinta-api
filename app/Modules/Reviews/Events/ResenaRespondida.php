<?php

namespace App\Modules\Reviews\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * El local respondió una reseña (§4.8). El cliente que la escribió se entera
 * (§11.2, "respuesta").
 */
final class ResenaRespondida
{
    use Dispatchable;

    public function __construct(
        public readonly string $resenaId,
    ) {}
}
