<?php

namespace App\Support\Auth;

use App\Models\Local;
use App\Models\Negocio;
use App\Models\Usuario;

/**
 * Resuelve el rol de un usuario en un local concreto y aplica la matriz de
 * permisos del §3.2. Las Policies de cada módulo (Fase 3 en adelante) delegan
 * aquí en vez de repetir la lógica de "qué rol tiene este usuario en este
 * local" en cada una.
 *
 * ## Supuesto documentado — el rol `admin`
 *
 * La matriz del §3.2 solo tiene cuatro columnas: Cliente, Profesional,
 * Recepción, Propietario. El campo `negocio_miembro.rol` (§4.4) admite un
 * quinto valor, `admin`, que la matriz no cubre. Se asume aquí que `admin`
 * tiene los mismos permisos que `propietario` **excepto** la gestión de la
 * suscripción y facturación, que se reserva al dueño legal del negocio
 * (`negocio.propietario_id`). Confirmar con producto si esto no es correcto —
 * no es una regla que la especificación fije, es una lectura razonable de un
 * vacío del documento.
 *
 * ## Lo que este servicio NO resuelve
 *
 * Las filas de la matriz que dependen de una relación de propiedad puntual —
 * "ver agenda propia", "bloquear su horario", "ver sus propias comisiones" —
 * no son "¿qué rol tiene X?" sino "¿es X el dueño de ESTE registro?". Esas se
 * resuelven comparando `profesional_id` directamente en la Policy del recurso
 * (Fase 4 en adelante), no aquí.
 */
final readonly class ContextoAcceso
{
    public function __construct(private Usuario $usuario) {}

    /**
     * `null` si el usuario no tiene membresía vigente en el negocio de ese
     * local (ni general, con `local_id IS NULL`, ni específica a ese local).
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
            ->value('rol');
    }

    public function esPropietarioOAdmin(Local $local): bool
    {
        return in_array($this->rolEnLocal($local), ['propietario', 'admin'], true);
    }

    /** "Editar precios, servicios, asignaciones" a nivel de negocio — usado para crear el primer local. */
    public function puedeGestionarNegocio(Negocio $negocio): bool
    {
        return in_array($this->rolEnNegocio($negocio->id), ['propietario', 'admin'], true);
    }

    /** "Ver agenda completa del local" — propietario, admin y recepción (§3.2). */
    public function puedeVerAgendaCompleta(Local $local): bool
    {
        return in_array($this->rolEnLocal($local), ['propietario', 'admin', 'recepcion'], true);
    }

    /**
     * "Ver comisiones de todos" — recepción está explícitamente excluida
     * (✗ marcado, no solo "—", en la tabla del §3.2). Es la razón de ser del
     * rol: agenda y cobra, pero no ve el negocio de comisiones.
     */
    public function puedeVerComisionesDeTodos(Local $local): bool
    {
        return in_array($this->rolEnLocal($local), ['propietario', 'admin'], true);
    }

    /** "Editar precios, servicios, asignaciones" — supuesto de `admin` arriba. */
    public function puedeEditarCatalogoYAsignaciones(Local $local): bool
    {
        return in_array($this->rolEnLocal($local), ['propietario', 'admin'], true);
    }

    /** "Responder reseñas" — supuesto de `admin` arriba. */
    public function puedeResponderResenas(Local $local): bool
    {
        return in_array($this->rolEnLocal($local), ['propietario', 'admin'], true);
    }

    /**
     * "Suscripción y facturación" — únicamente el dueño legal
     * (`negocio.propietario_id`), ni siquiera `admin`. Es dinero y
     * responsabilidad fiscal, no gestión operativa del día a día.
     */
    public function puedeGestionarSuscripcion(Local $local): bool
    {
        return $local->negocio->propietario_id === $this->usuario->id;
    }
}
