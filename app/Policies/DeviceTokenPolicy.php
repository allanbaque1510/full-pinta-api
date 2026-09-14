<?php

namespace App\Policies;

use App\Models\DeviceToken;
use App\Models\Usuario;

class DeviceTokenPolicy
{
    public function eliminar(Usuario $usuario, DeviceToken $deviceToken): bool
    {
        return $deviceToken->usuario_id === $usuario->id;
    }
}
