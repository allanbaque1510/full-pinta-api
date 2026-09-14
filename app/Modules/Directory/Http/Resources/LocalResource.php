<?php

namespace App\Modules\Directory\Http\Resources;

use App\Models\Local;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Local
 */
class LocalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'negocio_id' => $this->negocio_id,
            'nombre' => $this->nombre,
            'direccion' => $this->direccion,
            'referencia' => $this->referencia,
            'lat' => $this->ubicacion['lat'] ?? null,
            'lng' => $this->ubicacion['lng'] ?? null,
            'telefono' => $this->telefono,
            'whatsapp' => $this->whatsapp,
            'verificado' => $this->verificado,
            'estado' => $this->estado,
            'lead_time_min' => $this->lead_time_min,
            'horizonte_dias' => $this->horizonte_dias,
            'politica_cancelacion_horas' => $this->politica_cancelacion_horas,
        ];
    }
}
