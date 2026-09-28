<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Models\SolicitudCatalogo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SolicitudCatalogo
 */
class SolicitudCatalogoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'local_id' => $this->local_id,
            'solicitante_id' => $this->solicitante_id,
            'rubro' => $this->rubro->codigo,
            'nombre_propuesto' => $this->nombre_propuesto,
            'descripcion' => $this->descripcion,
            'estado' => $this->estado,
            'motivo_rechazo' => $this->motivo_rechazo,
        ];
    }
}
