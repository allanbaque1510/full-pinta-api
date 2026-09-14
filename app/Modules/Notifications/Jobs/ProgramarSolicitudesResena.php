<?php

namespace App\Modules\Notifications\Jobs;

use App\Models\Cita;
use App\Modules\Notifications\Application\NotificacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * §11.2: "pedir reseña" → cliente, push, 2 h después de completada.
 *
 * Acotado a los últimos 14 días (`Cita::admiteResena()`, mismo plazo, §4.8):
 * pasada la ventana ya no se puede reseñar, así que pedirlo sería un
 * recordatorio inútil — y sin este corte el `whereDoesntHave` reescanearía
 * el historial completo de citas completadas para siempre.
 */
class ProgramarSolicitudesResena implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificacionService $notificaciones): void
    {
        Cita::with('cliente')
            ->where('estado', 'completada')
            ->where('completada_at', '>=', now()->subDays(14))
            ->whereDoesntHave('resena')
            ->each(function (Cita $cita) use ($notificaciones) {
                $notificaciones->programar(
                    $cita->cliente,
                    'pedir_resena',
                    'social',
                    ['push'],
                    citaId: $cita->id,
                    programadaPara: $notificaciones->ajustarAVentanaDeEnvio($cita->completada_at->addHours(2)),
                );
            });
    }
}
