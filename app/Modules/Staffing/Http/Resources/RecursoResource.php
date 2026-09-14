<?php

namespace App\Modules\Staffing\Http\Resources;

use App\Models\Recurso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Recurso
 */
class RecursoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'local_id' => $this->local_id,
            'tipo' => $this->tipoRecurso->codigo,
            'nombre' => $this->nombre,
            'activo' => $this->activo,
        ];
    }
}
