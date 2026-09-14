<?php

namespace App\Modules\Directory\Http\Resources;

use App\Models\Local;
use App\Modules\Catalog\Http\Resources\ServicioLocalResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Perfil público del local (§7.4): lo que un cliente necesita para decidir.
 * Sin nada administrativo (`negocio_id`, `politica_cancelacion_horas`...) que
 * no le aporte — `lead_time_min`/`horizonte_dias` sí importan porque explican
 * "próximo turno disponible".
 *
 * @mixin Local
 */
class LocalPublicoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'direccion' => $this->direccion,
            'referencia' => $this->referencia,
            'lat' => $this->ubicacion['lat'] ?? null,
            'lng' => $this->ubicacion['lng'] ?? null,
            'telefono' => $this->telefono,
            'whatsapp' => $this->whatsapp,
            'verificado' => $this->verificado,
            'score_ranking' => $this->score_ranking,
            'lead_time_min' => $this->lead_time_min,
            'horizonte_dias' => $this->horizonte_dias,
            'horarios' => HorarioLocalResource::collection($this->whenLoaded('horarios')),
            'servicios' => ServicioLocalResource::collection($this->whenLoaded('servicios')),
            'amenidades' => AmenidadResource::collection($this->whenLoaded('amenidades')),
            'fotos' => LocalFotoResource::collection($this->whenLoaded('fotos')),
            'resenas' => [
                'promedio' => $this->whenLoaded('resenas', fn () => round($this->resenas->avg('puntaje_local') ?? 0, 1)),
                'total' => $this->whenLoaded('resenas', fn () => $this->resenas->count()),
            ],
        ];
    }
}
