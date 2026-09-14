<?php

namespace App\Policies;

use App\Models\Cita;
use App\Models\Resena;
use App\Models\Usuario;
use App\Support\Auth\ContextoAcceso;

class ResenaPolicy
{
    /** Solo el cliente dueño de la cita puede reseñarla — nunca el staff. */
    public function crear(Usuario $usuario, Cita $cita): bool
    {
        return $cita->cliente_id === $usuario->id;
    }

    public function responder(Usuario $usuario, Resena $resena): bool
    {
        $resena->loadMissing('local');

        return (new ContextoAcceso($usuario))->puedeResponderResenas($resena->local);
    }
}
