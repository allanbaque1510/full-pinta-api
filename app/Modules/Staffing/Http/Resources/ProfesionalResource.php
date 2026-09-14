<?php

namespace App\Modules\Staffing\Http\Resources;

use App\Models\Profesional;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Profesional
 */
class ProfesionalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'alias' => $this->alias,
            'bio' => $this->bio,
            'foto_url' => $this->foto_url,
            'independiente' => $this->independiente,
            'perfil_publico' => $this->perfil_publico,
            'traslado_min' => $this->traslado_min,
            'tiene_cuenta_propia' => $this->tieneCuentaPropia(),
        ];
    }
}
