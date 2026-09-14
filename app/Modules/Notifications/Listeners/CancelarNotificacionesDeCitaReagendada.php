<?php

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Application\NotificacionService;
use App\Modules\Scheduling\Events\CitaReagendada;

/**
 * §11.9: "no cancelar notificaciones programadas" es el bug más común. La
 * cita nueva ya dispara su propio `CitaCreada` desde
 * `CitaService::reservar()` (reusado por `ReagendarCita`, Fase 6), así que
 * sus notificaciones se programan solas — aquí solo se cancelan las
 * pendientes de la cita VIEJA.
 */
final readonly class CancelarNotificacionesDeCitaReagendada
{
    public function __construct(private NotificacionService $notificaciones) {}

    public function handle(CitaReagendada $event): void
    {
        $this->notificaciones->cancelarPendientesDeCita($event->citaAnteriorId);
    }
}
