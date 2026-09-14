<?php

namespace App\Modules\Staffing\Http\Resources;

use App\Models\Turno;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Turno
 */
class TurnoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asignacion_id' => $this->asignacion_id,
            'profesional_id' => $this->profesional_id,
            'local_id' => $this->local_id,
            'dia_semana' => $this->dia_semana,
            'entra' => substr($this->entra, 0, 5),
            'sale' => substr($this->sale, 0, 5),
            'vigente_desde' => $this->vigente_desde?->toDateString(),
            'vigente_hasta' => $this->vigente_hasta?->toDateString(),
        ];
    }
}
