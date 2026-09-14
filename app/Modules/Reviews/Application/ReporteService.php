<?php

namespace App\Modules\Reviews\Application;

use App\Models\Reporte;
use App\Models\Usuario;

/**
 * Reportes de moderación (§4.8): `tipo` + `objeto_id` es polimórfico a
 * propósito, sin FK — se reporta cualquier cosa (reseña, foto, local,
 * profesional). Sin endpoint de resolver/descartar todavía: no existe panel
 * de soporte de plataforma (mismo precedente que `SolicitudCatalogo`, Fase 3)
 * — se revisan a mano por ahora.
 */
final readonly class ReporteService
{
    /**
     * @param  array{tipo: string, objeto_id: string, motivo: string, detalle?: ?string}  $datos
     */
    public function crear(Usuario $reportante, array $datos): Reporte
    {
        return Reporte::create([
            'reportante_id' => $reportante->id,
            // DEFAULT en Postgres, no en PHP — ver skill `migracion`.
            'estado' => 'pendiente',
            ...$datos,
        ]);
    }
}
