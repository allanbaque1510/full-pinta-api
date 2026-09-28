<?php

namespace App\Modules\Scheduling\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una fila de `ClienteLocalService::bandejaMensual()` — viene de una consulta
 * de query builder crudo (`stdClass`), no de un modelo.
 */
class ClienteLocalBandejaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cliente_id' => $this->cliente_id,
            'cliente_nombre' => $this->cliente_nombre,
            'visitas_en_el_mes' => (int) $this->visitas_en_el_mes,
            'ultima_visita' => CarbonImmutable::parse($this->ultima_visita)->toIso8601String(),
        ];
    }
}
