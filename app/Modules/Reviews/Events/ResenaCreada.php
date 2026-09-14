<?php

namespace App\Modules\Reviews\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un cliente dejó una reseña (§4.8). El local se entera (§11.2, "reseña nueva").
 */
final class ResenaCreada
{
    use Dispatchable;

    public function __construct(
        public readonly string $resenaId,
    ) {}
}
