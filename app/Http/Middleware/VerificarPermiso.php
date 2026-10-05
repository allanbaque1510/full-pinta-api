<?php

namespace App\Http\Middleware;

use App\Support\Auth\ContextoAcceso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autoriza una ruta por su propio nombre — sin Policy, sin Gate (§3.2).
 *
 * El código de permiso que se consulta en `rol_permiso` es, siempre,
 * `Route::currentRouteName()`: por eso toda ruta que use este middleware
 * DEBE llevar `->name(...)`, y ese nombre nunca se repite en ningún otro
 * lado — ni en la ruta, ni en un controller, ni en una Policy. Para
 * declarar un permiso nuevo alcanza con nombrar la ruta y correr
 * `php artisan permisos:sincronizar`.
 *
 * Resuelve el `Local`/`Negocio`/`Profesional` contra el que se pregunta
 * mirando los parámetros de la propia ruta, en este orden — el primero que
 * exista gana. Para un recurso HIJO sin `{local}` directo en la URL (p. ej.
 * `{horario}`, `{servicio}`), sigue la relación `->local` del modelo ya
 * resuelto por el route-model-binding.
 *
 * Solo sirve para permisos que dependen del ROL (§3.2). Las rutas donde
 * además importa si el registro es específicamente del usuario (su propia
 * cita, él mismo el profesional, el dueño legal de la suscripción) no
 * llevan este middleware — ese `OR` de propiedad puntual se resuelve en el
 * controller, con un `if` directo contra `ContextoAcceso`.
 */
class VerificarPermiso
{
    /** @var array<int, string> Parámetros de ruta cuyo valor ES el Local/Negocio/Profesional a consultar. */
    private const PARAMETROS_DIRECTOS = ['local', 'negocio', 'profesional'];

    /** @var array<int, string> Parámetros de ruta cuyo modelo tiene una relación `local()`. */
    private const PARAMETROS_CON_LOCAL = [
        'horario', 'servicio', 'producto', 'asignacion', 'turno', 'recurso', 'liquidacion', 'resena',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $codigoPermiso = $request->route()?->getName();

        abort_if($codigoPermiso === null, 500, 'Ruta protegida por el middleware `permiso` sin `->name()`.');

        $contexto = new ContextoAcceso($request->user());

        abort_unless($this->autorizado($request, $contexto, $codigoPermiso), 403, 'No tienes permiso para esto.');

        return $next($request);
    }

    private function autorizado(Request $request, ContextoAcceso $contexto, string $codigoPermiso): bool
    {
        foreach (self::PARAMETROS_DIRECTOS as $parametro) {
            $modelo = $request->route($parametro);

            if ($modelo === null) {
                continue;
            }

            return match ($parametro) {
                'local' => $contexto->tienePermiso($modelo, $codigoPermiso),
                'negocio' => $contexto->tienePermisoEnNegocio($modelo, $codigoPermiso),
                'profesional' => $contexto->tienePermisoSobreProfesional($modelo, $codigoPermiso),
            };
        }

        foreach (self::PARAMETROS_CON_LOCAL as $parametro) {
            $modelo = $request->route($parametro);

            if ($modelo !== null) {
                return $contexto->tienePermiso($modelo->local, $codigoPermiso);
            }
        }

        // `{miembro}` (NegocioMiembro) no tiene `local` propio, solo `negocio`.
        $miembro = $request->route('miembro');

        if ($miembro !== null) {
            return $contexto->tienePermisoEnNegocio($miembro->negocio, $codigoPermiso);
        }

        return false;
    }
}
