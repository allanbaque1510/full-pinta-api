<?php

namespace App\Modules\Identity\Application;

use App\Models\Usuario;

/**
 * Envía un código de un solo uso al correo registrado del usuario, para
 * probar que lo controla (§13.1, pregunta 12 de `plan-revision-bd.md`) — el
 * `UNIQUE` de `usuario.email` solo evita duplicados, no prueba propiedad.
 */
final readonly class SolicitarVerificacionEmail
{
    public function __construct(private CodigoVerificacion $codigos) {}

    public function __invoke(Usuario $usuario): void
    {
        if ($usuario->email === null) {
            throw_validacion('No tienes un correo registrado.', 'email');
        }

        if ($usuario->email_verificado) {
            throw_validacion('Este correo ya está verificado.', 'email');
        }

        $this->codigos->enviarAEmail($usuario->email);
    }
}
