<?php

namespace App\Modules\Identity\Http\Resources;

use App\Models\Favorito;
use App\Modules\Directory\Http\Resources\LocalPublicoResource;
use App\Modules\Staffing\Http\Resources\ProfesionalPublicoResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un favorito puede ser de un local ajeno al usuario: nunca el Resource
 * administrativo (`LocalResource`/`ProfesionalResource`), siempre el público.
 *
 * @mixin Favorito
 */
class FavoritoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'local' => $this->whenLoaded('local', fn () => $this->local ? LocalPublicoResource::make($this->local) : null),
            'profesional' => $this->whenLoaded('profesional', fn () => $this->profesional ? ProfesionalPublicoResource::make($this->profesional) : null),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
