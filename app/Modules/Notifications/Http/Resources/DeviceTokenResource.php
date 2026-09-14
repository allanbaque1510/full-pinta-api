<?php

namespace App\Modules\Notifications\Http\Resources;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeviceToken
 */
class DeviceTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plataforma' => $this->plataforma,
            'app_version' => $this->app_version,
            'activo' => $this->activo,
            'ultimo_uso_at' => $this->ultimo_uso_at?->toIso8601String(),
        ];
    }
}
