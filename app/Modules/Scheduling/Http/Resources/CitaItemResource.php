<?php

namespace App\Modules\Scheduling\Http\Resources;

use App\Models\CitaItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CitaItem
 */
class CitaItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'servicio_local_id' => $this->servicio_local_id,
            'precio' => $this->precio,
            'duracion_min' => $this->duracion_min,
            'comisionable' => $this->comisionable,
            'comision_pct' => $this->comision_pct,
        ];
    }
}
