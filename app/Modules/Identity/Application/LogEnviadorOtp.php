<?php

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Application\Contracts\EnviadorOtp;
use Illuminate\Support\Facades\Log;

/**
 * Implementación provisional del envío de OTP: escribe al log en vez de
 * mandar WhatsApp de verdad.
 *
 * Existe para que Identity funcione de punta a punta antes de que la Fase 9
 * (WhatsApp Cloud API) esté lista. El día que se conecte el canal real, solo
 * cambia el binding en `IdentityServiceProvider` — el caso de uso que la
 * consume no se toca.
 *
 * NUNCA usar esto en producción: el código quedaría en los logs.
 */
class LogEnviadorOtp implements EnviadorOtp
{
    public function enviar(string $telefono, string $codigo): void
    {
        Log::warning('[OTP-PROVISIONAL] Envío real de WhatsApp aún no implementado (Fase 9).', [
            'telefono' => $telefono,
            'codigo' => $codigo,
        ]);
    }
}
