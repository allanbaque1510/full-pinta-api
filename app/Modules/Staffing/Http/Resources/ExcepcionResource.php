<?php

namespace App\Modules\Staffing\Http\Resources;

use App\Models\Excepcion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Excepcion
 */
class ExcepcionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'local_id' => $this->local_id,
            'profesional_id' => $this->profesional_id,
            'recurso_id' => $this->recurso_id,
            'fecha_inicio' => $this->fecha_inicio,
            'fecha_fin' => $this->fecha_fin,
            'motivo' => $this->motivo,
            'nota' => $this->nota,
        ];
    }
}
