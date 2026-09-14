<?php

namespace App\Modules\Identity\Application;

use Laravel\Sanctum\PersonalAccessToken;

/**
 * Revoca únicamente el token con el que se autenticó la petición actual — no
 * todos los dispositivos del usuario. Cerrar sesión en el teléfono no debe
 * desloguear la tablet de recepción.
 */
final readonly class CerrarSesion
{
    public function __invoke(PersonalAccessToken $tokenActual): void
    {
        $tokenActual->delete();
    }
}
