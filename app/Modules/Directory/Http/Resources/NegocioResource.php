<?php

namespace App\Modules\Directory\Http\Resources;

use App\Models\Negocio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Negocio
 */
class NegocioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre_marca' => $this->nombre_marca,
            'ruc' => $this->ruc,
            'propietario_id' => $this->propietario_id,
            'plan' => $this->plan->codigo,
            'plan_vigente_hasta' => $this->plan_vigente_hasta?->toDateString(),
        ];
    }
}
