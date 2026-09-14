<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Models\ServicioCategoria;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServicioCategoria
 */
class ServicioCategoriaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vertical' => $this->vertical->codigo,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'icono' => $this->icono,
            'orden' => $this->orden,
        ];
    }
}
