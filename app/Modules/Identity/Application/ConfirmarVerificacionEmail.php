<?php

namespace App\Modules\Identity\Application;

use App\Models\Usuario;

/**
 * Confirma el código enviado por `SolicitarVerificacionEmail` y marca
 * `email_verificado` (§13.1).
 */
final readonly class ConfirmarVerificacionEmail
{
    public function __construct(private CodigoVerificacion $codigos) {}

    public function __invoke(Usuario $usuario, string $codigo): void
    {
        if ($usuario->email === null) {
            throw_validacion('No tienes un correo registrado.', 'email');
        }

        $this->codigos->verificarEmail($usuario->email, $codigo);

        $usuario->update(['email_verificado' => true]);
    }
}
