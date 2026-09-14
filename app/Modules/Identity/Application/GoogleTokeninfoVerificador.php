<?php

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Application\Contracts\VerificadorTokenGoogle;
use App\Modules\Identity\Application\Exceptions\CredencialGoogleInvalida;
use Illuminate\Support\Facades\Http;

/**
 * Verificación real contra el endpoint público de Google, sin SDK ni
 * dependencia nueva — el `Http` facade ya viene con el framework (Guzzle).
 *
 * Nota: `tokeninfo` es el mecanismo que Google documenta para verificar un ID
 * token sin librería, pero tiene límite de tasa pensado para depuración, no
 * para volumen alto de producción. Si el volumen de logins con Google crece
 * mucho, la alternativa es verificar la firma del JWT contra las claves
 * públicas de Google (JWKS) en el propio backend — requiere una librería de
 * JWT, fuera de alcance mientras no haga falta.
 */
class GoogleTokeninfoVerificador implements VerificadorTokenGoogle
{
    public function verificar(string $idToken): array
    {
        $respuesta = Http::get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);

        if ($respuesta->failed()) {
            throw new CredencialGoogleInvalida;
        }

        $claims = $respuesta->json();
        $clientId = config('services.google.client_id');

        if ($clientId === null || ($claims['aud'] ?? null) !== $clientId) {
            throw new CredencialGoogleInvalida;
        }

        if (($claims['email_verified'] ?? 'false') !== 'true') {
            throw new CredencialGoogleInvalida;
        }

        return [
            'sub' => $claims['sub'],
            'email' => $claims['email'],
            'email_verified' => true,
            'name' => $claims['name'] ?? '',
            'picture' => $claims['picture'] ?? null,
        ];
    }
}
