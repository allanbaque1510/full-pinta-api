<?php

namespace App\Policies;

use App\Models\Profesional;
use App\Models\Usuario;
use App\Support\Auth\ContextoAcceso;

/**
 * Un `Profesional` no pertenece a un solo local — puede trabajar en varios a
 * la vez (§4.6: "Kevin trabaja en dos locales"). Por eso la autorización no
 * es "¿sos propietario/admin del local de este profesional?" (no hay UNO),
 * sino "¿sos propietario/admin de AL MENOS UN local donde este profesional
 * tiene una asignación vigente?".
 */
class ProfesionalPolicy
{
    public function ver(Usuario $usuario, Profesional $profesional): bool
    {
        return $this->loAdministra($usuario, $profesional);
    }

    public function actualizar(Usuario $usuario, Profesional $profesional): bool
    {
        return $this->loAdministra($usuario, $profesional);
    }

    private function loAdministra(Usuario $usuario, Profesional $profesional): bool
    {
        $contexto = new ContextoAcceso($usuario);

        return $profesional->asignaciones()->vigente()->with('local')->get()
            ->contains(fn ($asignacion) => $contexto->esPropietarioOAdmin($asignacion->local));
    }
}
