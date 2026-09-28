<?php

namespace Database\Seeders;

use App\Models\FinalidadConsentimiento;
use Illuminate\Database\Seeder;

/**
 * Finalidades de consentimiento (§13.1): reemplaza el varchar+CHECK que
 * antes vivía en `consentimiento.finalidad` — el nombre/descripción que ve el
 * usuario en la pantalla de consentimiento vive aquí, no hardcodeado en el
 * Flutter.
 *
 * IMPORTANTE: `nombre`/`descripcion` son texto BORRADOR (placeholder), marcado
 * explícitamente `[PENDIENTE-LEGAL]` — no es copia final. El texto real que se
 * muestra al usuario para autorizar cada finalidad es una decisión de
 * negocio/legal, no técnica (ver `context/plan-revision-bd.md`, pregunta 10),
 * y debe reemplazarse antes de salir a producción.
 *
 * `documento_tipo` queda sin asignar (null) para las 4 — misma razón: cuáles
 * finalidades necesitan un `documento_legal` propio detrás está pendiente de
 * confirmar con asesoría legal LOPDP.
 */
class FinalidadConsentimientoSeeder extends Seeder
{
    /**
     * codigo => [nombre, descripcion, obligatorio], en orden de presentación.
     *
     * @var array<string, array{0: string, 1: string, 2: bool}>
     */
    private const FINALIDADES = [
        'operacion_servicio' => [
            '[PENDIENTE-LEGAL] Operación del servicio',
            '[PENDIENTE-LEGAL] Texto borrador — pendiente de redacción legal LOPDP.',
            true,
        ],
        'comunicaciones_transaccionales' => [
            '[PENDIENTE-LEGAL] Comunicaciones sobre tus citas',
            '[PENDIENTE-LEGAL] Texto borrador — pendiente de redacción legal LOPDP.',
            true,
        ],
        'marketing' => [
            '[PENDIENTE-LEGAL] Promociones y novedades',
            '[PENDIENTE-LEGAL] Texto borrador — pendiente de redacción legal LOPDP.',
            false,
        ],
        'transferencia_internacional' => [
            '[PENDIENTE-LEGAL] Transferencia internacional de datos',
            '[PENDIENTE-LEGAL] Texto borrador — pendiente de redacción legal LOPDP.',
            true,
        ],
    ];

    public function run(): void
    {
        $orden = 0;

        foreach (self::FINALIDADES as $codigo => [$nombre, $descripcion, $obligatorio]) {
            FinalidadConsentimiento::updateOrCreate(
                ['codigo' => $codigo],
                [
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'obligatorio' => $obligatorio,
                    'orden' => ++$orden,
                    'activo' => true,
                ],
            );
        }
    }
}
