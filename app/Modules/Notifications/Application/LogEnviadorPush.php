<?php

namespace App\Modules\Notifications\Application;

use App\Models\DeviceToken;
use App\Modules\Notifications\Application\Contracts\EnviadorPush;
use Illuminate\Support\Facades\Log;

/**
 * Implementación provisional: escribe al log en vez de mandar FCM real.
 * NUNCA usar en producción. Ver docblock de `EnviadorPush`.
 */
class LogEnviadorPush implements EnviadorPush
{
    public function enviar(DeviceToken $token, array $payload): ?string
    {
        Log::info('[PUSH-PROVISIONAL] Envío real de FCM aún no implementado.', [
            'device_token_id' => $token->id, 'payload' => $payload,
        ]);

        return null;
    }
}
