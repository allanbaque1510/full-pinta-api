<?php

namespace App\Modules\Notifications\Http\Resources;

use App\Models\PreferenciaNotificacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PreferenciaNotificacion
 */
class PreferenciaNotificacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'categoria' => $this->categoria->codigo,
            'push' => $this->push,
            'whatsapp' => $this->whatsapp,
        ];
    }
}
