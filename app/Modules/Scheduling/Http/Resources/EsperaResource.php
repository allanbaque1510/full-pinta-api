<?php

namespace App\Modules\Scheduling\Http\Resources;

use App\Models\Espera;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Espera
 */
class EsperaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'local_id' => $this->local_id,
            'cliente_id' => $this->cliente_id,
            'profesional_id' => $this->profesional_id,
            'servicio_local_id' => $this->servicio_local_id,
            'fecha_deseada' => $this->fecha_deseada?->toDateString(),
            'desde' => $this->desde,
            'hasta' => $this->hasta,
            'estado' => $this->estado,
        ];
    }
}
