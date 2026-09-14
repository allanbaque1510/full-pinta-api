<?php

namespace App\Modules\Identity\Application\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** El `id_token` de Google no es válido, expiró, o no es para esta app (`aud` no coincide). */
class CredencialGoogleInvalida extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La credencial de Google no es válida o expiró.');
    }

    public function render(Request $request): JsonResponse
    {
        return response_error(401, 'credencial_google_invalida', $this->getMessage());
    }
}
