<?php

namespace App\Modules\Notifications\Application;

use App\Modules\Notifications\Application\Contracts\EnviadorWebSocket;
use Illuminate\Support\Facades\Log;

/**
 * Implementación provisional: escribe al log en vez de emitir por Reverb real.
 * NUNCA usar en producción. Ver docblock de `EnviadorWebSocket`.
 */
class LogEnviadorWebSocket implements EnviadorWebSocket
{
    public function emitir(string $canal, array $payload): void
    {
        Log::info('[WEBSOCKET-PROVISIONAL] Reverb aún no está instalado.', [
            'canal' => $canal, 'payload' => $payload,
        ]);
    }
}
