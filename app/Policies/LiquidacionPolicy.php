<?php

namespace App\Policies;

use App\Models\Liquidacion;
use App\Models\Local;
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
}
