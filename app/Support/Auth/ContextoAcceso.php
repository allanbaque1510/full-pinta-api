<?php

namespace App\Support\Auth;

use App\Models\Asignacion;
use App\Models\Local;
use App\Models\Negocio;
use App\Models\Profesional;
use App\Models\Rol;
use App\Models\Usuario;

/**
 * Resuelve el rol de acceso de un usuario en un local/negocio concreto y
 * consulta sus permisos (`rol`/`permiso`/`rol_permiso`, §3.2). El middleware
 * `permiso` (`App\Http\Middleware\VerificarPermiso`) delega aquí para cada
 * ruta protegida por rol, en vez de repetir "qué rol tiene este usuario en
 * este local" o "qué puede hacer ese rol" en cada una. No hay Policies ni
 * Gates en este proyecto para esto — ver el middleware.
 *
 * ## Supuesto documentado — el rol `admin`
 *
 * La matriz del §3.2 solo tiene cuatro columnas: Cliente, Profesional,
 * Recepción, Propietario. El campo `negocio_miembro.rol_personal` (§4.4)
 * admite un quinto valor, `admin`, que la matriz no cubre. Se asume aquí (y
 * en el seeder `RolPermisoSeeder`) que `admin` tiene los mismos permisos que
 * `propietario` **excepto** la gestión de la suscripción y facturación, que
 * se reserva al dueño legal del negocio (`negocio.propietario_id`). Confirmar
 * con producto si esto no es correcto — no es una regla que la especificación
 * fije, es una lectura razonable de un vacío del documento.
 *
 * ## De dónde sale cada permiso — `rol_permiso` (revisión de base de datos,
 * 2026-10-05)
 *
 * `tienePermiso()`/`tienePermisoEnNegocio()` consultan la tabla `permiso`,
 * vía `rol_permiso`, en vez de un `in_array([...])` escrito a mano. Cambiar
 * quién puede ver comisiones o responder reseñas es ahora un ajuste de datos
 * (`RolPermisoSeeder`), no un despliegue.
 *
 * A diferencia de `rolEnLocal()`/`rolEnNegocio()` (que SOLO resuelven
 * membresía en `negocio_miembro`: propietario/admin/recepción/`null`),
 * `tienePermiso()` resuelve el rol de acceso COMPLETO de la matriz —
 * incluyendo `profesional` (asignación vigente en ESE local concreto) y
 * `cliente` (default si no es ninguna de las anteriores) — porque la tabla
 * `rol_permiso` sí declara permisos para esos dos roles.
 *
 * ## Lo que esta tabla NO reemplaza
 *
 * La pregunta "¿es este registro específicamente SUYO?" (es esta SU cita, es
 * este profesional él mismo) no es "qué rol tiene X" — sigue resuelta por
 * comparación directa de id, en el controller de cada recurso, nunca aquí.
 * Un profesional con permiso de agenda en general no implica que pueda ver
 * CUALQUIER cita de CUALQUIER profesional — ambas preguntas se combinan en
 * el controller (`abort_unless($esSuyo || $contexto->tienePermiso(...))`),
 * cada una resuelta por la herramienta correcta.
 *
 * Tampoco la gestión de suscripción/facturación: es del dueño legal
 * (`negocio.propietario_id`), ni siquiera `admin` — ver
 * `puedeGestionarSuscripcion()`.
 */
final readonly class ContextoAcceso
{
    public function __construct(private Usuario $usuario) {}

    /**
     * `null` si el usuario no tiene membresía vigente en el negocio de ese
     * local (ni general, con `local_id IS NULL`, ni específica a ese local).
     * Solo mira `negocio_miembro` — no resuelve `profesional`/`cliente`, ver
     * `tienePermiso()` para eso.
     */
    public function rolEnLocal(Local $local): ?string
    {
        return $this->rolEnNegocio($local->negocio_id, $local->id);
    }

    /**
     * Igual que `rolEnLocal`, pero sin necesitar que el local ya exista —
     * hace falta para autorizar la creación del **primer** local de un
     * negocio, donde todavía no hay `Local` al que preguntarle.
     */
    public function rolEnNegocio(string $negocioId, ?string $localId = null): ?string
    {
        return $this->usuario->membresias()
            ->vigente()
            ->where('negocio_id', $negocioId)
            ->where(function ($q) use ($localId) {
                $q->whereNull('local_id');

                if ($localId !== null) {
                    $q->orWhere('local_id', $localId);
                }
            })
            ->orderByRaw('local_id IS NULL') // específico del local antes que el general
            ->value('rol_personal');
    }

    /**
     * El rol de acceso completo de la matriz (§3.2) para este local: la
     * membresía si existe, si no "¿tiene asignación vigente como profesional
     * EN ESTE LOCAL?", si no `cliente` por default — todo usuario autenticado
     * es, como mínimo, cliente.
     */
    private function rolDeAccesoEnLocal(Local $local): string
    {
        $rolMembresia = $this->rolEnLocal($local);

        if ($rolMembresia !== null) {
            return $rolMembresia;
        }

        return $this->esProfesionalVigenteEnLocal($local) ? 'profesional' : 'cliente';
    }

    private function esProfesionalVigenteEnLocal(Local $local): bool
    {
        $profesional = $this->usuario->profesional;

        if ($profesional === null) {
            return false;
        }

        return Asignacion::where('local_id', $local->id)
            ->where('profesional_id', $profesional->id)
            ->vigente()
            ->exists();
    }

    /** ¿Tiene el rol de acceso de este usuario en este local el permiso `$permisoCodigo` (`rol_permiso`, §3.2)? */
    public function tienePermiso(Local $local, string $permisoCodigo): bool
    {
        return $this->tienePermisoParaRol($this->rolDeAccesoEnLocal($local), $permisoCodigo);
    }

    /**
     * Igual que `tienePermiso`, pero a nivel del negocio completo (sin un
     * local concreto todavía) — solo resuelve membresía: ningún permiso de
     * `cliente`/`profesional` aplica a nivel de negocio en la matriz actual.
     */
    public function tienePermisoEnNegocio(Negocio $negocio, string $permisoCodigo): bool
    {
        return $this->tienePermisoParaRol($this->rolEnNegocio($negocio->id), $permisoCodigo);
    }

    private function tienePermisoParaRol(?string $rolCodigo, string $permisoCodigo): bool
    {
        if ($rolCodigo === null) {
            return false;
        }

        return Rol::query()
            ->where('codigo', $rolCodigo)
            ->where('activo', true)
            ->whereHas('permisos', fn ($q) => $q->where('codigo', $permisoCodigo)->where('activo', true))
            ->exists();
    }

    /**
     * Igual que `tienePermiso`, pero para un `Profesional` que no cuelga de
     * un solo local (§4.6: puede trabajar en varios a la vez) — concede si
     * el usuario tiene el permiso en AL MENOS UNO de los locales donde el
     * profesional tiene asignación vigente.
     */
    public function tienePermisoSobreProfesional(Profesional $profesional, string $permisoCodigo): bool
    {
        return $profesional->asignaciones()->vigente()->with('local')->get()
            ->contains(fn ($asignacion) => $this->tienePermiso($asignacion->local, $permisoCodigo));
    }

    /**
     * "Suscripción y facturación" — únicamente el dueño legal
     * (`negocio.propietario_id`), ni siquiera `admin`. Es dinero y
     * responsabilidad fiscal, no gestión operativa del día a día. No pasa
     * por `rol_permiso`: no es un permiso de rol, es propiedad puntual.
     */
    public function puedeGestionarSuscripcion(Negocio $negocio): bool
    {
        return $negocio->propietario_id === $this->usuario->id;
    }
}
