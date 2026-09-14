<?php

namespace App\Modules\Identity\Application\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CodigoOtpIncorrecto extends RuntimeException
{
    public function __construct(private readonly int $intentosRestantes)
    {
        parent::__construct('El código ingresado es incorrecto.');
    }

    public function render(Request $request): JsonResponse
    {
        return response_error(422, 'otp_incorrecto', $this->getMessage(), [
            'intentos_restantes' => max(0, $this->intentosRestantes),
        ]);
    }
}
