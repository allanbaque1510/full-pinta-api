<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Models\CatalogoServicio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CatalogoServicio
 */
class CatalogoServicioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vertical' => $this->categoria->vertical->codigo,
            'categoria_codigo' => $this->categoria->codigo,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'duracion_base_min' => $this->duracion_base_min,
            'tipo_recurso' => $this->tipoRecurso->codigo,
        ];
    }
}
