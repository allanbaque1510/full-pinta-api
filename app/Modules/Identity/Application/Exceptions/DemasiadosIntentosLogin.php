<?php

namespace App\Modules\Identity\Application\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Protege el login por correo/contraseña de fuerza bruta (mismo criterio que `DemasiadasSolicitudesOtp`). */
class DemasiadosIntentosLogin extends RuntimeException
{
    public function __construct(private readonly int $segundosDeEspera)
    {
        parent::__construct('Demasiados intentos. Intenta de nuevo más tarde.');
    }

    public function render(Request $request): JsonResponse
    {
        return response_error(
            429,
            'demasiados_intentos_login',
            $this->getMessage(),
            extra: ['reintentar_en_segundos' => $this->segundosDeEspera],
            headers: ['Retry-After' => (string) $this->segundosDeEspera],
        );
    }
}
