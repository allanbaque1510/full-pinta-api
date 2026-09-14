<?php

namespace App\Modules\Directory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una fila de `BusquedaLocalService::buscar()` — viene de una consulta de
 * query builder crudo (`stdClass`), no de un modelo `Local`.
 */
class BusquedaLocalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'negocio_id' => $this->negocio_id,
            'nombre' => $this->nombre,
            'direccion' => $this->direccion,
            'lat' => (float) $this->lat,
            'lng' => (float) $this->lng,
            'distancia_m' => round((float) $this->distancia_m, 1),
            'telefono' => $this->telefono,
            'whatsapp' => $this->whatsapp,
            'verificado' => (bool) $this->verificado,
            'score_ranking' => (float) $this->score_ranking,
        ];
    }
}
