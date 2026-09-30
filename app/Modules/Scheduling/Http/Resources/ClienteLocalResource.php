<?php

namespace App\Modules\Scheduling\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha individual del cliente en un local (§4.7) — no es un modelo Eloquent:
 * viene del array que arma `ClienteLocalService::ficha()`, combinando la fila
 * de `cliente_local` (si existe), el resumen de visitas calculado en vivo, y
 * la confiabilidad de `cliente_perfil` (§5.6) — uso interno del staff.
 */
class ClienteLocalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'nota' => $this['nota'],
            'profesional_preferido_id' => $this['profesional_preferido_id'],
            'total_citas' => $this['total_citas'],
            'primera_cita_at' => $this['primera_cita_at'],
            'ultima_cita_at' => $this['ultima_cita_at'],
            'no_shows' => $this['no_shows'],
            'cancelaciones_tardias' => $this['cancelaciones_tardias'],
            'requiere_confirmacion' => $this['requiere_confirmacion'],
        ];
    }
}
