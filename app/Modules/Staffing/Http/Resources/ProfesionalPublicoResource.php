<?php

namespace App\Modules\Staffing\Http\Resources;

use App\Models\Profesional;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Perfil público del profesional (§7.5). Sin `traslado_min` ni nada
 * operativo — eso es del motor de disponibilidad, no del perfil que ve el
 * cliente.
 *
 * @mixin Profesional
 */
class ProfesionalPublicoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'alias' => $this->alias,
            'bio' => $this->bio,
            'foto_url' => $this->foto_url,
            'fotos' => ProfesionalFotoResource::collection($this->whenLoaded('fotos')),
            'servicios' => HabilidadResource::collection($this->whenLoaded('habilidades')),
            'resenas' => [
                'promedio' => $this->whenLoaded('resenas', fn () => round($this->resenas->avg('puntaje_profesional') ?? 0, 1)),
                'total' => $this->whenLoaded('resenas', fn () => $this->resenas->count()),
            ],
        ];
    }
}
