<?php

namespace App\Modules\Billing\Http\Resources;

use App\Models\Suscripcion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Suscripcion
 */
class SuscripcionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'negocio_id' => $this->negocio_id,
            'plan' => $this->whenLoaded('plan', fn () => $this->plan->codigo),
            'profesionales' => $this->profesionales,
            'precio_mensual' => $this->precio_mensual,
            'ciclo' => $this->ciclo,
            'estado' => $this->estado,
            'vigente_hasta' => $this->vigente_hasta?->toDateString(),
        ];
    }
}
