<?php

namespace App\Modules\Reviews\Http\Resources;

use App\Models\Resena;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Resena
 */
class ResenaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cita_id' => $this->cita_id,
            'local_id' => $this->local_id,
            'profesional_id' => $this->profesional_id,
            'puntaje_local' => $this->puntaje_local,
            'puntaje_profesional' => $this->puntaje_profesional,
            'puntualidad' => $this->puntualidad,
            'limpieza' => $this->limpieza,
            'comentario' => $this->comentario,
            'estado' => $this->estado,
            'respuesta_local' => $this->respuesta_local,
            'respuesta_at' => $this->respuesta_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
