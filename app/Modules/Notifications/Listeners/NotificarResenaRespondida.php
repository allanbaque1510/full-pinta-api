<?php

namespace App\Modules\Notifications\Listeners;

use App\Models\Resena;
use App\Modules\Notifications\Application\NotificacionService;
use App\Modules\Reviews\Events\ResenaRespondida;

/** §11.2: "respuesta" → el cliente que escribió la reseña, push. */
final readonly class NotificarResenaRespondida
{
    public function __construct(private NotificacionService $notificaciones) {}

    public function handle(ResenaRespondida $event): void
    {
        $resena = Resena::with('cita.cliente')->findOrFail($event->resenaId);

        $this->notificaciones->programar(
            $resena->cita->cliente,
            'resena_respuesta',
            'social',
            ['push'],
            citaId: $resena->cita_id,
        );
    }
}
