<?php

namespace App\Modules\Billing\Http\Resources;

use App\Models\Cobro;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cobro
 */
class CobroResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'suscripcion_id' => $this->suscripcion_id,
            'monto' => $this->monto,
            'estado' => $this->estado,
            'intentos' => $this->intentos,
            'pagado_at' => $this->pagado_at?->toIso8601String(),
            'comprobante_sri' => $this->comprobante_sri,
        ];
    }
}
