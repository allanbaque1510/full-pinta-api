<?php

namespace App\Modules\Scheduling\Http\Resources;

use App\Models\Cita;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cita
 */
class CitaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'local_id' => $this->local_id,
            'profesional_id' => $this->profesional_id,
            'recurso_id' => $this->recurso_id,
            'cliente_id' => $this->cliente_id,
            // Privacidad (§3.3): el teléfono del cliente solo se revela desde
            // que la cita está confirmada — antes de eso el local no lo necesita.
            'cliente_telefono' => $this->when(
                in_array($this->estado, ['confirmada', 'en_curso', 'completada'], true),
                fn () => $this->cliente->telefono,
            ),
            'inicio' => $this->inicio->toIso8601String(),
            'fin' => $this->fin->toIso8601String(),
            'estado' => $this->estado,
            'canal' => $this->canal,
            'precio_total' => $this->precio_total,
            'propina' => $this->propina,
            'metodo_pago' => $this->metodo_pago,
            'cliente_nuevo' => $this->cliente_nuevo,
            'para_tipo' => $this->para_tipo,
            'para_nombre' => $this->para_nombre,
            'mascota_id' => $this->mascota_id,
            'nota_cliente' => $this->nota_cliente,
            'codigo' => $this->codigo,
            'reagendada_de_id' => $this->reagendada_de_id,
            'expira_at' => $this->expira_at?->toIso8601String(),
            'confirmada_at' => $this->confirmada_at?->toIso8601String(),
            'cancelada_at' => $this->cancelada_at?->toIso8601String(),
            'completada_at' => $this->completada_at?->toIso8601String(),
            'items' => CitaItemResource::collection($this->whenLoaded('items')),
            'productos' => CitaProductoResource::collection($this->whenLoaded('productos')),
        ];
    }
}
