<?php

namespace App\Http\Resources;

use App\Models\Imagen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Imagen
 */
class ImagenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'objeto_type' => $this->objeto_type,
            'objeto_id' => $this->objeto_id,
            'tipo' => $this->tipo->codigo,
            'url' => $this->url,
            'orden' => $this->orden,
        ];
    }
}
