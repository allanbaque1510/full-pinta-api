<?php

namespace App\Policies;

use App\Models\Negocio;
use App\Models\Usuario;
use App\Support\Auth\ContextoAcceso;

class NegocioPolicy
{
    public function ver(Usuario $usuario, Negocio $negocio): bool
    {
        return (new ContextoAcceso($usuario))->puedeGestionarNegocio($negocio);
    }

    public function actualizar(Usuario $usuario, Negocio $negocio): bool
    {
        return $this->ver($usuario, $negocio);
    }

    public function crearLocal(Usuario $usuario, Negocio $negocio): bool
    {
        return $this->ver($usuario, $negocio);
    }

    /**
     * Listar los locales del negocio: cualquier miembro con rol vigente
     * (propietario, admin **o recepción**) — a diferencia de `ver`/`actualizar`,
     * que son solo para quien administra el negocio en sí.
     */
    public function verLocales(Usuario $usuario, Negocio $negocio): bool
    {
        return (new ContextoAcceso($usuario))->rolEnNegocio($negocio->id) !== null;
    }

    /** Dinero y responsabilidad fiscal: ni siquiera `admin`, solo el dueño legal. */
    public function gestionarSuscripcion(Usuario $usuario, Negocio $negocio): bool
    {
        return $negocio->propietario_id === $usuario->id;
    }
}
