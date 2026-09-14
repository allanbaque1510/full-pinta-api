<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Producto
 */
class ProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'local_id' => $this->local_id,
            'nombre' => $this->nombre,
            'precio' => $this->precio,
            'comision_pct' => $this->comision_pct,
            'activo' => $this->activo,
        ];
    }
}
