<?php

namespace App\Modules\Scheduling\Application;

use Carbon\CarbonImmutable;

/**
 * Un horario disponible para agendar: quién lo atendería, con qué recurso
 * (si el servicio lo necesita) y la ventana exacta.
 */
final readonly class Slot
{
    public function __construct(
        public string $profesionalId,
        public ?string $recursoId,
        public CarbonImmutable $inicio,
        public CarbonImmutable $fin,
    ) {}
}
