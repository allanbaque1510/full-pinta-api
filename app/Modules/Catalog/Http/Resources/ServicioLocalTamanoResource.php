<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Models\ServicioLocalTamano;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServicioLocalTamano
 */
class ServicioLocalTamanoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tamano' => $this->tamano->codigo,
            'precio' => $this->precio,
            'duracion_min' => $this->duracion_min,
        ];
    }
}
