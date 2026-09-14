<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Models\ServicioLocal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServicioLocal
 */
class ServicioLocalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'local_id' => $this->local_id,
            'catalogo_servicio_id' => $this->catalogo_servicio_id,
            'nombre' => $this->whenLoaded('catalogoServicio', fn () => $this->catalogoServicio->nombre),
            'precio' => $this->precio,
            'precio_desde' => $this->precio_desde,
            'duracion_min' => $this->duracion_min,
            'buffer_min' => $this->buffer_min,
            'comisionable' => $this->comisionable,
            'activo' => $this->activo,
            'tamanos' => ServicioLocalTamanoResource::collection($this->whenLoaded('tamanos')),
        ];
    }
}
