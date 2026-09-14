<?php

namespace App\Modules\Notifications\Application;

use App\Models\PlantillaWhatsapp;
use App\Modules\Notifications\Application\Contracts\EnviadorWhatsApp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Implementación provisional: escribe al log en vez de mandar WhatsApp real.
 * NUNCA usar en producción. Ver docblock de `EnviadorWhatsApp`.
 */
class LogEnviadorWhatsApp implements EnviadorWhatsApp
{
    public function enviar(string $telefono, PlantillaWhatsapp $plantilla, array $variables): string
    {
        Log::info('[WHATSAPP-PROVISIONAL] Envío real de WhatsApp Cloud API aún no implementado.', [
            'telefono' => $telefono, 'plantilla' => $plantilla->nombre, 'variables' => $variables,
        ]);

        return (string) Str::uuid();
    }
}
