<?php

namespace Tests\Concerns;

use App\Models\Local;
use App\Models\Negocio;
use App\Models\NegocioMiembro;
use App\Models\Usuario;

/**
 * Arma un propietario con negocio y (opcionalmente) local, con la membresía
 * `negocio_miembro` real que `ContextoAcceso` necesita para autorizar — el
 * mismo fixture lo usan los tests de horarios, amenidades, servicios,
 * productos y solicitudes.
 */
trait CreaNegocioDePrueba
{
    /** @return array{0: Usuario, 1: string, 2: Negocio} */
    protected function propietarioConNegocio(): array
    {
        $usuario = Usuario::factory()->create();
        $token = $usuario->createToken('t')->plainTextToken;

        $negocio = Negocio::factory()->create(['propietario_id' => $usuario->id]);
        NegocioMiembro::factory()->propietario()->create([
            'usuario_id' => $usuario->id,
            'negocio_id' => $negocio->id,
            'local_id' => null,
        ]);

        return [$usuario, $token, $negocio];
    }

    /** @return array{0: Usuario, 1: string, 2: Negocio, 3: Local} */
    protected function propietarioConLocal(): array
    {
        [$usuario, $token, $negocio] = $this->propietarioConNegocio();
        $local = Local::factory()->create(['negocio_id' => $negocio->id]);

        return [$usuario, $token, $negocio, $local];
    }

    /**
     * Un recepcionista del mismo negocio — para probar que ve pero no edita.
     *
     * @return array{0: Usuario, 1: string}
     */
    protected function recepcionEnNegocio(Negocio $negocio): array
    {
        $usuario = Usuario::factory()->create();
        NegocioMiembro::factory()->recepcion()->create([
            'usuario_id' => $usuario->id,
            'negocio_id' => $negocio->id,
            'local_id' => null,
        ]);

        return [$usuario, $usuario->createToken('t')->plainTextToken];
    }
}
