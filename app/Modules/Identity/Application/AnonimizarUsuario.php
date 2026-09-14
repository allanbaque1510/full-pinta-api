<?php

namespace App\Modules\Identity\Application;

use App\Models\Usuario;

/**
 * Derecho de eliminación (§13.1, LOPDP).
 *
 * No se borra la fila: `cita`, `cita_evento`, `resena` y `liquidacion` cuelgan
 * de este `usuario_id` y tienen que seguir cuadrando (la especificación es
 * explícita en §4.2: "no se borran locales, profesionales ni citas"). En su
 * lugar se destruye lo identificable y se marca `anonimizado_at`.
 *
 * Lo que SÍ sobrevive a propósito: el `id` (para no romper las relaciones), los
 * datos operativos ya congelados en citas pasadas (precios, comisiones) —
 * anonimizar al cliente no debe descuadrar la liquidación de un profesional— y
 * `password_hash`, que en este sistema nunca es una contraseña real (§4.3: se
 * autentica solo por OTP) sino el hash aleatorio que marca la cuenta como
 * reclamada; tocarlo no protege nada y solo confundiría ese estado.
 */
final readonly class AnonimizarUsuario
{
    public function __invoke(Usuario $usuario): void
    {
        $usuario->update([
            // Se garabatea el teléfono, no se deja vacío: `telefono` es
            // UNIQUE, y si el número se reasigna a otra persona, esa persona
            // debe registrarse como alguien nuevo, no reclamar esta cuenta.
            // `telefono` es varchar(20): el uuid completo no cabe, así que se
            // usa un recorte determinístico del propio id (sigue siendo único).
            'telefono' => 'anon_'.substr(str_replace('-', '', $usuario->id), 0, 15),
            'telefono_verificado' => false,
            'email' => null,
            'nombre' => 'Usuario eliminado',
            'foto_url' => null,
            'anonimizado_at' => now(),
        ]);

        // Los tokens vigentes quedan revocados: nadie sigue autenticado como
        // una cuenta que acaba de pedir su eliminación.
        $usuario->tokens()->delete();
    }
}
