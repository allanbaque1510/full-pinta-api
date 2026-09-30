<?php

namespace App\Modules\Scheduling\Http\Resources;

use App\Modules\Scheduling\Application\Slot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Slot
 */
class SlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'profesional_id' => $this->profesionalId,
            'profesional_nombre' => $this->profesionalNombre,
            'profesional_alias' => $this->profesionalAlias,
            'profesional_foto_url' => $this->profesionalFotoUrl,
            'recurso_id' => $this->recursoId,
            'inicio' => $this->inicio->toIso8601String(),
            'fin' => $this->fin->toIso8601String(),
        ];
    }
}
