<?php

namespace App\Modules\Directory\Application;

use App\Models\Negocio;
use App\Models\NegocioMiembro;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;

/**
 * Todo lo que se puede hacer con `NegocioMiembro` (§4.4, §3.2): quién, además
 * del propietario, tiene acceso real a la app para este negocio — admin o
 * recepción. Distinto de `Profesional`/`Asignacion` (Staffing): eso es la
 * ficha de trabajo de quien atiende, esto es acceso a la plataforma. Un mismo
 * negocio puede necesitar las dos cosas para la misma persona, por separado.
 */
final readonly class NegocioMiembroService
{
    public function listar(Negocio $negocio): Collection
    {
        return $negocio->miembros()->vigente()->with('usuario', 'local')->get();
    }

    /**
     * Resuelve por `telefono` una cuenta YA registrada — sin invitación por
     * link en esta v1. `propietario` no es un `rol` asignable por acá: es
     * fijo desde `NegocioService::crear()` y no se otorga dos veces.
     */
    public function agregar(Negocio $negocio, string $telefono, string $rol, ?string $localId): NegocioMiembro
    {
        $usuario = Usuario::where('telefono', $telefono)->first();

        if ($usuario === null) {
            throw_validacion('Esa persona debe registrarse en la app primero.', 'telefono');
        }

        return NegocioMiembro::create([
            'usuario_id' => $usuario->id,
            'negocio_id' => $negocio->id,
            'local_id' => $localId,
            'rol_personal' => $rol,
            'desde' => now()->toDateString(),
        ])->load('usuario', 'local');
    }

    /**
     * Revoca el acceso — nunca se borra la fila (mismo criterio que
     * `asignacion`/`turno`: es historial). El propietario legal no puede
     * perder su membresía por este camino, ni siquiera por error propio.
     */
    public function terminar(NegocioMiembro $miembro): NegocioMiembro
    {
        $miembro->loadMissing('negocio');

        if ($miembro->usuario_id === $miembro->negocio->propietario_id) {
            throw_validacion('No se puede terminar la membresía del propietario legal del negocio.', 'miembro');
        }

        $miembro->update(['hasta' => now()->toDateString()]);

        return $miembro;
    }
}
