<?php

namespace App\Modules\Identity\Application;

use App\Models\Consentimiento;
use App\Models\Usuario;

/**
 * Otorga un consentimiento por finalidad (§13.1).
 *
 * Cada otorgamiento es una fila NUEVA, nunca una actualización: es lo que
 * permite probar, ante un reclamo, exactamente qué aceptó el usuario, con qué
 * versión del documento y desde dónde. Para revocar, ver `RevocarConsentimiento`
 * — que sí actualiza esta misma fila (`revocado_at`), porque revocar es un
 * hecho sobre ESE otorgamiento concreto, no un evento aparte.
 *
 * Operar la cita (`operacion_servicio`) no es lo mismo que recibir promociones
 * (`marketing`): se otorgan y se revocan por separado.
 */
final readonly class OtorgarConsentimiento
{
    public function __invoke(
        Usuario $usuario,
        string $finalidad,
        string $origen,
        ?string $ip = null,
        ?string $userAgent = null,
    ): Consentimiento {
        return Consentimiento::create([
            'usuario_id' => $usuario->id,
            'finalidad' => $finalidad,
            'documento_version' => config('fullpinta.version_terminos'),
            'otorgado' => true,
            'origen' => $origen,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'otorgado_at' => now(),
        ]);
    }
}
