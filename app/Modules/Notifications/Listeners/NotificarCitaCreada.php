<?php

namespace App\Modules\Notifications\Listeners;

use App\Models\Cita;
use App\Modules\Notifications\Application\NotificacionService;
use App\Modules\Scheduling\Events\CitaCreada;

/**
 * §11.2: "Cita creada" → cliente (push+whatsapp, obligatorio) y
 * profesional/local (push+websocket, si el profesional tiene cuenta propia).
 */
final readonly class NotificarCitaCreada
{
    public function __construct(private NotificacionService $notificaciones) {}

    public function handle(CitaCreada $event): void
    {
        $cita = Cita::with(['cliente', 'profesional.usuario'])->findOrFail($event->citaId);

        $this->notificaciones->programar(
            $cita->cliente,
            'cita_creada_cliente',
            'citas',
            ['push', 'whatsapp'],
            citaId: $cita->id,
            obligatorio: true,
        );

        if ($cita->profesional->tieneCuentaPropia()) {
            $this->notificaciones->programar(
                $cita->profesional->usuario,
                'cita_creada_profesional',
                'agenda',
                ['push', 'websocket'],
                citaId: $cita->id,
            );
        }
    }
}
