<?php

namespace App\Modules\Identity\Http\Resources;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Usuario
 */
class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'telefono' => $this->telefono,
            'telefono_verificado' => $this->telefono_verificado,
            'nombre' => $this->nombre,
            'email' => $this->email,
            'foto_url' => $this->foto_url,
        ];
    }
}
