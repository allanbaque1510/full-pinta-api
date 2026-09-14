<?php

namespace App\Modules\Directory\Http\Resources;

use App\Models\LocalFoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LocalFoto
 */
class LocalFotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'local_id' => $this->local_id,
            'url' => $this->url,
            'tipo' => $this->tipo,
            'orden' => $this->orden,
        ];
    }
}
