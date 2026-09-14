<?php

namespace App\Modules\Notifications\Listeners;

use App\Models\Profesional;
use App\Modules\Notifications\Application\NotificacionService;
use App\Modules\Staffing\Events\TurnoModificado;

/** §11.2: "Turno modificado" → profesional, push. */
final readonly class NotificarTurnoModificado
{
    public function __construct(private NotificacionService $notificaciones) {}

    public function handle(TurnoModificado $event): void
    {
        $profesional = Profesional::with('usuario')->find($event->profesionalId);

        if ($profesional?->tieneCuentaPropia()) {
            $this->notificaciones->programar($profesional->usuario, 'turno_modificado', 'agenda', ['push']);
        }
    }
}
