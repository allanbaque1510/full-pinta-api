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
 * §11.2: "recordatorio" (24 h antes, push) y "recordatorio final" (2-3 h
 * antes, whatsapp + push). No hace falta una "ventana de detección": se
 * reescanea todo lo `confirmada` en cada corrida y el `UNIQUE(cita_id,
 * tipo_evento, canal)` hace que programarlo dos veces sea gratis — "no se
 * resuelve con una condición dentro del job" (skill `notificacion`).
 *
 * `programada_para` se calcula sobre el horario real de la cita y se ajusta a
 * la ventana de silencio 8:00-21:00 Guayaquil (§11.9) — puede terminar en el
 * pasado si el ajuste la movió a la noche anterior; eso es intencional: la
 * fila queda `pendiente` de inmediato en vez de esperar hasta esa hora.
 */
class ProgramarRecordatoriosCitas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificacionService $notificaciones): void
    {
        Cita::with('cliente')
            ->where('estado', 'confirmada')
            ->where('inicio', '>', now())
            ->each(function (Cita $cita) use ($notificaciones) {
                $notificaciones->programar(
                    $cita->cliente,
                    'recordatorio_24h',
                    'citas',
                    ['push'],
                    citaId: $cita->id,
                    programadaPara: $notificaciones->ajustarAVentanaDeEnvio($cita->inicio->subHours(24)),
                );

                $notificaciones->programar(
                    $cita->cliente,
                    'recordatorio_final',
                    'citas',
                    ['whatsapp', 'push'],
                    citaId: $cita->id,
                    programadaPara: $notificaciones->ajustarAVentanaDeEnvio($cita->inicio->subHours(3)),
                );
            });
    }
}
