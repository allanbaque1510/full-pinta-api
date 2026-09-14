<?php

namespace App\Modules\Staffing\Http\Resources;

use App\Models\Habilidad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Habilidad
 */
class HabilidadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'profesional_id' => $this->profesional_id,
            'servicio_local_id' => $this->servicio_local_id,
            'servicio_nombre' => $this->whenLoaded(
                'servicioLocal',
                fn () => $this->servicioLocal->catalogoServicio?->nombre,
            ),
            'precio_override' => $this->precio_override,
        ];
    }
}
