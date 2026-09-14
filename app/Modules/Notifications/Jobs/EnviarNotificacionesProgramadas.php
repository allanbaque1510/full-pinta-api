<?php

namespace App\Modules\Notifications\Jobs;

use App\Models\DeviceToken;
use App\Models\Notificacion;
use App\Models\PlantillaWhatsapp;
use App\Modules\Notifications\Application\Contracts\EnviadorPush;
use App\Modules\Notifications\Application\Contracts\EnviadorWebSocket;
use App\Modules\Notifications\Application\Contracts\EnviadorWhatsApp;
use App\Modules\Notifications\Application\NotificacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * El envío real (§12.6, cola `notificaciones`): nunca dentro del request.
 * Despacha según `canal` al puerto correspondiente — hoy los `LogEnviador*`
 * provisionales (ver skill `notificacion`), mañana FCM/Meta/Reverb reales sin
 * tocar este job, solo el binding en `NotificationsServiceProvider`.
 *
 * Costo de WhatsApp (§11.3) con un valor de referencia fijo (`0.02` USD, rango
 * "utility") mientras no exista integración real con las tarifas de Meta —
 * placeholder documentado, no un número negociado.
 */
class EnviarNotificacionesProgramadas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const COSTO_WHATSAPP_REFERENCIA_USD = 0.02;

    public function handle(
        NotificacionService $notificaciones,
        EnviadorPush $push,
        EnviadorWhatsApp $whatsapp,
        EnviadorWebSocket $websocket,
    ): void {
        Notificacion::pendientes()->with('usuario')->get()->each(function (Notificacion $notificacion) use ($notificaciones, $push, $whatsapp, $websocket) {
            try {
                match ($notificacion->canal) {
                    'push' => $this->enviarPush($notificacion, $push, $notificaciones),
                    'whatsapp' => $this->enviarWhatsApp($notificacion, $whatsapp, $notificaciones),
                    'websocket' => $this->enviarWebSocket($notificacion, $websocket, $notificaciones),
                    default => $notificaciones->marcarFallida($notificacion, "Canal '{$notificacion->canal}' sin implementación de envío."),
                };
            } catch (Throwable $e) {
                $notificaciones->marcarFallida($notificacion, $e->getMessage());
            }
        });
    }

    private function enviarPush(Notificacion $notificacion, EnviadorPush $push, NotificacionService $notificaciones): void
    {
        $tokens = DeviceToken::where('usuario_id', $notificacion->usuario_id)->where('activo', true)->get();

        if ($tokens->isEmpty()) {
            $notificaciones->marcarFallida($notificacion, 'El usuario no tiene ningún device_token activo.');

            return;
        }

        $proveedorId = null;

        foreach ($tokens as $token) {
            $proveedorId = $push->enviar($token, [
                'tipo' => $notificacion->tipo_evento,
                'cita_id' => $notificacion->cita_id,
            ]) ?? $proveedorId;
        }

        $notificaciones->marcarEnviada($notificacion, $proveedorId);
    }

    private function enviarWhatsApp(Notificacion $notificacion, EnviadorWhatsApp $whatsapp, NotificacionService $notificaciones): void
    {
        $plantilla = PlantillaWhatsapp::where('nombre', $notificacion->plantilla)->where('estado', 'aprobada')->first();

        if ($plantilla === null) {
            $notificaciones->marcarFallida($notificacion, "La plantilla '{$notificacion->plantilla}' no existe o no está aprobada.");

            return;
        }

        $proveedorId = $whatsapp->enviar($notificacion->usuario->telefono, $plantilla, []);
        $notificaciones->marcarEnviada($notificacion, $proveedorId, self::COSTO_WHATSAPP_REFERENCIA_USD);
    }

    private function enviarWebSocket(Notificacion $notificacion, EnviadorWebSocket $websocket, NotificacionService $notificaciones): void
    {
        $websocket->emitir("usuario.{$notificacion->usuario_id}", [
            'tipo' => $notificacion->tipo_evento,
            'cita_id' => $notificacion->cita_id,
        ]);
        $notificaciones->marcarEnviada($notificacion, null);
    }
}
