<?php

namespace App\Modules\Notifications\Listeners;

use App\Models\Cita;
use App\Modules\Notifications\Application\NotificacionService;
use App\Modules\Scheduling\Events\CitaCancelada;

/**
 * §11.2: "Cancelada por el local" → cliente; "cancelada por el cliente" →
 * profesional. `no_show`/`expirada` no tienen fila en la matriz — se ignoran
 * a propósito, no se inventa un envío que la especificación no pide.
 */
final readonly class NotificarCitaCancelada
{
    public function __construct(private NotificacionService $notificaciones) {}

    public function handle(CitaCancelada $event): void
    {
        if (! in_array($event->estado, ['cancelada_local', 'cancelada_cliente'], true)) {
            return;
        }

        $cita = Cita::with(['cliente', 'profesional.usuario'])->findOrFail($event->citaId);

        if ($event->estado === 'cancelada_local') {
            $this->notificaciones->programar(
                $cita->cliente,
                'cita_cancelada_cliente',
                'citas',
                ['push', 'whatsapp'],
                citaId: $cita->id,
                obligatorio: true,
            );

            return;
        }

        if ($cita->profesional->tieneCuentaPropia()) {
            $this->notificaciones->programar(
                $cita->profesional->usuario,
                'cita_cancelada_profesional',
                'agenda',
                ['push', 'websocket'],
                citaId: $cita->id,
            );
        }
    }
}
