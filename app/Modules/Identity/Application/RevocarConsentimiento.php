<?php

namespace App\Modules\Identity\Application;

use App\Models\Consentimiento;
use App\Models\Usuario;

/**
 * Revoca el consentimiento vigente de un usuario para una finalidad (§13.1).
 *
 * Actualiza `revocado_at` en la fila del otorgamiento vigente — no crea una
 * fila nueva: revocar es un hecho sobre ESE otorgamiento concreto. Si no hay
 * ninguno vigente (nunca se otorgó, o ya estaba revocado), no hace nada: es
 * idempotente a propósito, para que el front pueda reintentar sin cuidado.
 */
final readonly class RevocarConsentimiento
{
    public function __invoke(Usuario $usuario, string $finalidad): ?Consentimiento
    {
        $vigente = Consentimiento::where('usuario_id', $usuario->id)
            ->where('finalidad', $finalidad)
            ->where('otorgado', true)
            ->whereNull('revocado_at')
            ->latest('otorgado_at')
            ->first();

        $vigente?->update(['revocado_at' => now()]);

        return $vigente;
    }
}
