<?php

namespace App\Policies;

use App\Models\Local;
use App\Models\Usuario;
use App\Support\Auth\ContextoAcceso;

class LocalPolicy
{
    /** Cualquier miembro con rol en el local (propietario, admin o recepción) puede consultarlo. */
    public function ver(Usuario $usuario, Local $local): bool
    {
        return (new ContextoAcceso($usuario))->rolEnLocal($local) !== null;
    }

    public function actualizar(Usuario $usuario, Local $local): bool
    {
        return (new ContextoAcceso($usuario))->esPropietarioOAdmin($local);
    }

    public function cambiarEstado(Usuario $usuario, Local $local): bool
    {
        return $this->actualizar($usuario, $local);
    }

    /** Horarios, amenidades, servicios, productos, fotos: mismo permiso que editar el local. */
    public function gestionarCatalogo(Usuario $usuario, Local $local): bool
    {
        return (new ContextoAcceso($usuario))->puedeEditarCatalogoYAsignaciones($local);
    }
}
