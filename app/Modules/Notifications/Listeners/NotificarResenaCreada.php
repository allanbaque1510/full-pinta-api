<?php

namespace App\Modules\Notifications\Listeners;

use App\Models\NegocioMiembro;
use App\Models\Resena;
use App\Modules\Notifications\Application\NotificacionService;
use App\Modules\Reviews\Events\ResenaCreada;

/**
 * §11.2: "reseña nueva" → local, push. En la práctica, a quien tenga
 * membresía vigente `propietario`/`admin` en el negocio de ese local (§3.2) —
 * la especificación dice "el local", no un único destinatario.
 *
 * `tipo_evento` lleva el id del destinatario como sufijo por la misma razón
 * que `NotificarEsperaNotificada`: puede haber más de un propietario/admin
 * para el mismo local.
 */
final readonly class NotificarResenaCreada
{
    public function __construct(private NotificacionService $notificaciones) {}

    public function handle(ResenaCreada $event): void
    {
        $resena = Resena::with('local')->findOrFail($event->resenaId);

        $destinatarios = NegocioMiembro::where('negocio_id', $resena->local->negocio_id)
            ->vigente()
            ->whereIn('rol', ['propietario', 'admin'])
            ->where(fn ($q) => $q->whereNull('local_id')->orWhere('local_id', $resena->local_id))
            ->with('usuario')
            ->get();

        foreach ($destinatarios as $miembro) {
            $this->notificaciones->programar(
                $miembro->usuario,
                "resena_nueva_{$miembro->usuario_id}",
                'social',
                ['push'],
                citaId: $resena->cita_id,
            );
        }
    }
}
