<?php

namespace App\Modules\Billing\Http\Resources;

use App\Models\Liquidacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * §9.4-9.5: "liquidación calculada y visible pero bloqueada" en el plan Free
 * — se ve el total, no el desglose. Requiere `local.negocio` cargado
 * (`->load('local.negocio')`), sin eso rompe `preventLazyLoading()`.
 *
 * @mixin Liquidacion
 */
class LiquidacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $esPro = $this->local->negocio->esPro();

        return [
            'id' => $this->id,
            'local_id' => $this->local_id,
            'profesional_id' => $this->profesional_id,
            'periodo_desde' => $this->periodo_desde->toDateString(),
            'periodo_hasta' => $this->periodo_hasta->toDateString(),
            'total_servicios' => $this->when($esPro, $this->total_servicios),
            'total_productos' => $this->when($esPro, $this->total_productos),
            'comision_servicios' => $this->when($esPro, $this->comision_servicios),
            'comision_productos' => $this->when($esPro, $this->comision_productos),
            'total_propinas' => $this->total_propinas,
            'total_a_pagar' => $this->total_a_pagar,
            'estado' => $this->estado,
            'cerrada_at' => $this->cerrada_at?->toIso8601String(),
        ];
    }
}
