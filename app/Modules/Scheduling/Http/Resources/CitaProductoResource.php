<?php

namespace App\Modules\Scheduling\Http\Resources;

use App\Models\CitaProducto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CitaProducto
 */
class CitaProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'producto_id' => $this->producto_id,
            'cantidad' => $this->cantidad,
            'precio' => $this->precio,
            'comision_pct' => $this->comision_pct,
        ];
    }
}
