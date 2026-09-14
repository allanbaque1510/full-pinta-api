<?php

namespace App\Modules\Directory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\HorarioLocal
 */
class HorarioLocalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'local_id' => $this->local_id,
            'dia_semana' => $this->dia_semana,
            'abre' => substr($this->abre, 0, 5),
            'cierra' => substr($this->cierra, 0, 5),
        ];
    }
}
