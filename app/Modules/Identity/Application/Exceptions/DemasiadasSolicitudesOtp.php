<?php

namespace App\Modules\Identity\Application\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Protege el presupuesto de WhatsApp (§11.3): sin límite, alguien puede pedir
 * cientos de códigos al mismo número y la plataforma paga cada uno.
 */
class DemasiadasSolicitudesOtp extends RuntimeException
{
    public function __construct(private readonly int $segundosDeEspera)
    {
        parent::__construct('Demasiadas solicitudes de código. Intenta de nuevo más tarde.');
    }

    public function render(Request $request): JsonResponse
    {
        return response_error(
            429,
            'otp_demasiadas_solicitudes',
            $this->getMessage(),
            extra: ['reintentar_en_segundos' => $this->segundosDeEspera],
            headers: ['Retry-After' => (string) $this->segundosDeEspera],
        );
    }
}
