<?php

namespace App\Modules\Staffing\Http\Resources;

use App\Models\ProfesionalFoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProfesionalFoto
 */
class ProfesionalFotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'profesional_id' => $this->profesional_id,
            'url' => $this->url,
            'orden' => $this->orden,
        ];
    }
}
