<?php

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Application\Contracts\EnviadorCodigoEmail;
use Illuminate\Support\Facades\Log;

/**
 * Implementación provisional del envío de código por correo: escribe al log
 * en vez de mandar un email de verdad.
 *
 * Existe para que Identity funcione de punta a punta antes de que exista un
 * proveedor de correo saliente contratado (bloqueador externo). El día que se
 * conecte el proveedor real, solo cambia el binding en `IdentityServiceProvider`
 * — el caso de uso que la consume no se toca.
 *
 * NUNCA usar esto en producción: el código quedaría en los logs.
 */
class LogEnviadorCodigoEmail implements EnviadorCodigoEmail
{
    public function enviar(string $email, string $codigo): void
    {
        Log::warning('[EMAIL-PROVISIONAL] Envío real de correo aún no implementado (sin proveedor contratado).', [
            'email' => $email,
            'codigo' => $codigo,
        ]);
    }
}
