<?php

namespace App\Modules\Identity\Application;

use App\Models\Usuario;

/**
 * Máximo `fullpinta.max_dispositivos_activos` sesiones a la vez (2 por
 * defecto, §4.3): al iniciar sesión por encima del límite, se cierra la más
 * antigua — nunca se rechaza el login nuevo. Compartido por los cuatro
 * métodos de login (OTP, Google, correo), en vez de repetirse en cada uno.
 */
final readonly class LimitarSesionesActivas
{
    public function __invoke(Usuario $usuario): void
    {
        $maximo = (int) config('fullpinta.max_dispositivos_activos');

        // Por `id`, NO por `created_at`: `personal_access_tokens.created_at`
        // guarda solo segundos enteros (sin fracción), así que dos logins
        // seguidos caen fácil en el mismo segundo y Postgres no puede
        // desempatar el orden de forma confiable. `id` es autoincremental y
        // nunca empata.
        $usuario->tokens()
            ->orderByDesc('id')
            ->get()
            ->slice(max(0, $maximo - 1))
            ->each->delete();
    }
}
