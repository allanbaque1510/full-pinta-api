<?php

namespace App\Modules\Notifications\Listeners;

use App\Models\Espera;
use App\Modules\Notifications\Application\NotificacionService;
use App\Modules\Scheduling\Events\EsperaNotificada;

/**
 * §11.2: "cupo liberado" → cliente en espera, push+whatsapp, obligatorio (es
 * la función que retiene clientes, §4.7).
 *
 * `tipo_evento` lleva el id de la espera como sufijo: el `UNIQUE(cita_id,
 * tipo_evento, canal)` no incluye `usuario_id`, y dos clientes distintos
 * pueden esperar el mismo cupo liberado — sin el sufijo, el segundo chocaría
 * contra el primero.
 */
final readonly class NotificarEsperaNotificada
{
    public function __construct(private NotificacionService $notificaciones) {}

    public function handle(EsperaNotificada $event): void
    {
        $espera = Espera::with('cliente')->findOrFail($event->esperaId);

        $this->notificaciones->programar(
            $espera->cliente,
            "cupo_liberado_{$espera->id}",
            'citas',
            ['push', 'whatsapp'],
            citaId: $event->citaLiberadaId,
            obligatorio: true,
        );
    }
}
