<?php

namespace App\Modules\Staffing\Http\Resources;

use App\Models\Asignacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Asignacion
 */
class AsignacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'local_id' => $this->local_id,
            'profesional_id' => $this->profesional_id,
            'profesional_nombre' => $this->whenLoaded('profesional', fn () => $this->profesional->nombre),
            'rol' => $this->rol,
            'modalidad' => $this->modalidad,
            'comision_pct' => $this->comision_pct,
            // `->toDateString()`, no el valor crudo: el cast `date` de
            // Eloquent solo aplica su formato al serializar el MODELO
            // directo (`$model->toArray()`); un Resource que lee el atributo
            // y lo mete en su propio array se salta ese paso — sin esto
            // devuelve un ISO completo con hora falsa medianoche UTC.
            'desde' => $this->desde?->toDateString(),
            'hasta' => $this->hasta?->toDateString(),
        ];
    }
}
