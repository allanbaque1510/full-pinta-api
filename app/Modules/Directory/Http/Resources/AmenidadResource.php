<?php

namespace App\Modules\Directory\Http\Resources;

use App\Models\Amenidad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Amenidad
 */
class AmenidadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'categoria' => $this->categoria->codigo,
            'nombre' => $this->nombre,
            'icono' => $this->icono,
            // Solo presente cuando viene de `$local->amenidades` (pivot
            // `local_amenidad`), no del catálogo plano de `GET /amenidades`.
            'detalle' => $this->whenPivotLoaded('local_amenidad', fn () => $this->pivot->detalle),
        ];
    }
}
