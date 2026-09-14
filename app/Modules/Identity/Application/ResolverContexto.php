<?php

namespace App\Modules\Identity\Application;

use App\Models\Usuario;

/**
 * Arma la lista de "contextos" con los que este usuario puede entrar a la app.
 *
 * El rol NO es un campo de `usuario`: un mismo teléfono puede ser cliente,
 * dueño de un negocio, y barbero en otro local, todo a la vez (§3.2). Este
 * caso de uso es la única fuente de esa respuesta — el front nunca infiere
 * roles combinando tablas por su cuenta.
 *
 * ## Contrato con el front (Flutter)
 *
 * `GET /api/v1/auth/contexto` (autenticado) devuelve:
 *
 * ```json
 * {
 *   "usuario_id": "uuid",
 *   "es_cliente": true,
 *   "requiere_seleccion": true,
 *   "contextos": [
 *     { "tipo": "negocio", "rol": "propietario", "negocio_id": "uuid",
 *       "negocio_nombre": "Barbería Kevin", "local_id": null, "local_nombre": null },
 *     { "tipo": "profesional", "rol": "barbero", "negocio_id": null,
 *       "negocio_nombre": null, "local_id": "uuid", "local_nombre": "Alborada" }
 *   ]
 * }
 * ```
 *
 * - `es_cliente` es siempre `true`: cualquier cuenta puede agendar para sí
 *   misma (fila "Agendar para sí" de la matriz del §3.2). No es un contexto
 *   seleccionable, es una capacidad de fondo.
 * - `requiere_seleccion`: si es `false` (0 o 1 contexto), el front entra
 *   directo sin mostrar el selector — solo hay una pantalla posible.
 * - `contexto.tipo = "negocio"` viene de `negocio_miembro` vigente:
 *   `local_id = null` significa **todos los locales de ese negocio**, no
 *   "ningún local". `rol` es uno de `propietario`, `admin`, `recepcion`.
 * - `contexto.tipo = "profesional"` viene de `asignacion` vigente: el usuario
 *   tiene perfil de `profesional` y trabaja en ese `local_id` con ese `rol`
 *   (`barbero`, `estilista`, `manicurista`, `groomer`).
 * - Un mismo `local_id` puede aparecer dos veces con `tipo` distinto (p. ej.
 *   el dueño que también corta pelo ahí) — son selecciones independientes,
 *   no se deduplican.
 *
 * El front guarda el contexto elegido y lo manda en cada petición que lo
 * necesite (agenda del local, comisiones, etc.) — este endpoint no fija
 * ninguna sesión de servidor; ver skill `endpoint` para cómo se autoriza cada
 * acción una vez elegido el contexto.
 */
final readonly class ResolverContexto
{
    public function __invoke(Usuario $usuario): array
    {
        $comoNegocio = $usuario->membresias()
            ->vigente()
            ->with(['negocio', 'local'])
            ->get()
            ->map(fn ($m) => [
                'tipo' => 'negocio',
                'rol' => $m->rol,
                'negocio_id' => $m->negocio_id,
                'negocio_nombre' => $m->negocio->nombre_marca,
                'local_id' => $m->local_id,
                'local_nombre' => $m->local?->nombre,
            ]);

        $profesional = $usuario->profesional;

        $comoProfesional = $profesional
            ? $profesional->asignaciones()
                ->vigente()
                ->with('local')
                ->get()
                ->map(fn ($a) => [
                    'tipo' => 'profesional',
                    'rol' => $a->rol,
                    'negocio_id' => null,
                    'negocio_nombre' => null,
                    'local_id' => $a->local_id,
                    'local_nombre' => $a->local->nombre,
                ])
            : collect();

        $contextos = $comoNegocio->concat($comoProfesional)->values();

        return [
            'usuario_id' => $usuario->id,
            'es_cliente' => true,
            'requiere_seleccion' => $contextos->count() > 1,
            'contextos' => $contextos,
        ];
    }
}
