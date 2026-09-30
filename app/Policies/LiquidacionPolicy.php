<?php

namespace App\Policies;

use App\Models\Liquidacion;
use App\Models\Local;
use App\Models\Profesional;
use App\Models\Usuario;
use App\Support\Auth\ContextoAcceso;

/**
 * Comisiones: propietario/admin, nunca recepción (§3.2, mismo criterio que
 * `ContextoAcceso::puedeVerComisionesDeTodos`) — es la razón de ser del rol
 * de recepción: agenda y cobra, pero no ve el negocio de comisiones.
 */
class LiquidacionPolicy
{
    /**
     * Listar y generar borradores: todavía no hay una `Liquidacion` que
     * autorizar, así que se llama como `$this->authorize('gestionar',
     * [Liquidacion::class, $local])` — mismo patrón que `ResenaPolicy::crear`.
     */
    public function gestionar(Usuario $usuario, Local $local): bool
    {
        return (new ContextoAcceso($usuario))->puedeVerComisionesDeTodos($local);
    }

    /** Cerrar o marcar pagada una liquidación que ya existe. */
    public function actualizar(Usuario $usuario, Liquidacion $liquidacion): bool
    {
        $liquidacion->loadMissing('local');

        return (new ContextoAcceso($usuario))->puedeVerComisionesDeTodos($liquidacion->local);
    }

    /**
     * Ver las propias (§3.2) — deliberadamente SOLO el propio profesional, no
     * propietario/admin: eso ya lo tienen cubierto con `GET
     * /locales/{local}/liquidaciones`, scopeado a su local. Abrir este
     * endpoint también a ellos filtrando por profesional cruzaría locales de
     * negocios distintos que comparten el mismo profesional — la misma fuga
     * de privacidad entre locales que el §3.3 ya prohíbe para otros datos.
     */
    public function verPropias(Usuario $usuario, Profesional $profesional): bool
    {
        return $usuario->profesional?->id === $profesional->id;
    }
}
