<?php

namespace App\Policies;

use App\Models\Cita;
use App\Models\Usuario;
use App\Support\Auth\ContextoAcceso;

/**
 * Una cita es del cliente que la agendó y del local que la atiende — la
 * autorización combina "¿es esta SU cita?" (comparación directa de id, no lo
 * resuelve `ContextoAcceso`) con "¿administra este local?" (§3.2).
 */
class CitaPolicy
{
    public function ver(Usuario $usuario, Cita $cita): bool
    {
        return $this->esElCliente($usuario, $cita) || $this->esStaffDelLocal($usuario, $cita);
    }

    /** Confirmar su propio hold, o el staff confirmándolo por él (walk-in tardío, por ejemplo). */
    public function confirmar(Usuario $usuario, Cita $cita): bool
    {
        return $this->esElCliente($usuario, $cita) || $this->esStaffDelLocal($usuario, $cita);
    }

    /** El cliente cancela lo suyo; el staff cancela cualquier cita de su local. */
    public function cancelar(Usuario $usuario, Cita $cita): bool
    {
        return $this->esElCliente($usuario, $cita) || $this->esStaffDelLocal($usuario, $cita);
    }

    public function reagendar(Usuario $usuario, Cita $cita): bool
    {
        return $this->esElCliente($usuario, $cita) || $this->esStaffDelLocal($usuario, $cita);
    }

    /** Marcar que llegó, completar, no-show, agregar producto, dar de alta un walk-in: solo quien opera la agenda. */
    public function gestionar(Usuario $usuario, Cita $cita): bool
    {
        return $this->esStaffDelLocal($usuario, $cita);
    }

    private function esElCliente(Usuario $usuario, Cita $cita): bool
    {
        return $cita->cliente_id === $usuario->id;
    }

    private function esStaffDelLocal(Usuario $usuario, Cita $cita): bool
    {
        $cita->loadMissing('local');

        return (new ContextoAcceso($usuario))->puedeVerAgendaCompleta($cita->local);
    }
}
