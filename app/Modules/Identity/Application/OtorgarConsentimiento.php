<?php

namespace App\Modules\Identity\Application;

use App\Models\Consentimiento;
use App\Models\FinalidadConsentimiento;
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
        $finalidadModelo = $this->finalidad($finalidad);

        // Nulo si la finalidad no tiene documento asignado (`documento_tipo`)
        // o si el tipo asignado todavía no tiene ninguna versión publicada
        // (pendiente de confirmar con legal — ver plan-revision-bd.md).
        $documento = $finalidadModelo->documentoVigente();

        return Consentimiento::create([
            'usuario_id' => $usuario->id,
            'finalidad_id' => $finalidadModelo->id,
            'documento_legal_id' => $documento?->id,
            'otorgado' => true,
            'origen' => $origen,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'otorgado_at' => now(),
        ]);
    }

    /**
     * Autocuración (mismo patrón que `ImagenService::tipoPerfilId()`): el
     * registro por OTP otorga `operacion_servicio` automáticamente, así que
     * este catálogo no puede estar vacío nunca, ni siquiera en un entorno
     * donde el seeder todavía no corrió. El texto de relleno queda marcado
     * `[PENDIENTE-LEGAL]` — igual que en `FinalidadConsentimientoSeeder` — y
     * se sobreescribe solo cuando el seeder real corre después (`updateOrCreate`
     * por `codigo`).
     */
    private function finalidad(string $codigo): FinalidadConsentimiento
    {
        return FinalidadConsentimiento::where('codigo', $codigo)->where('activo', true)->first()
            ?? FinalidadConsentimiento::create([
                'codigo' => $codigo,
                'nombre' => "[PENDIENTE-LEGAL] {$codigo}",
                'descripcion' => '[PENDIENTE-LEGAL] Texto borrador — pendiente de redacción legal LOPDP.',
                'obligatorio' => true,
                'activo' => true,
            ]);
    }
}
