<?php

namespace App\Modules\Directory\Http\Resources;

use App\Models\NegocioMiembro;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NegocioMiembro
 */
class NegocioMiembroResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'usuario_id' => $this->usuario_id,
            'usuario_nombre' => $this->usuario->nombre,
            'negocio_id' => $this->negocio_id,
            'rol' => $this->rol_personal,
            'local_id' => $this->local_id,
            'local_nombre' => $this->local?->nombre,
            'desde' => $this->desde->toDateString(),
            'hasta' => $this->hasta?->toDateString(),
        ];
    }
}
