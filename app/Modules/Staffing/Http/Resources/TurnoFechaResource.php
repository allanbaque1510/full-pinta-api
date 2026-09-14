<?php

namespace App\Modules\Staffing\Http\Resources;

use App\Models\TurnoFecha;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TurnoFecha
 */
class TurnoFechaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'profesional_id' => $this->profesional_id,
            'local_id' => $this->local_id,
            'fecha' => $this->fecha?->toDateString(),
            'tipo' => $this->tipo,
            'entra' => $this->entra ? substr($this->entra, 0, 5) : null,
            'sale' => $this->sale ? substr($this->sale, 0, 5) : null,
            'nota' => $this->nota,
        ];
    }
}
