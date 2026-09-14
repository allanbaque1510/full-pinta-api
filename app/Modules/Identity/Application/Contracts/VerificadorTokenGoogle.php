<?php

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Application\Exceptions\CredencialGoogleInvalida;

/**
 * Puerto de verificación del ID token de Google Sign-In.
 *
 * `GoogleTokeninfoVerificador` es la implementación real (llama al endpoint
 * público de Google, sin SDK ni dependencia nueva). `FakeVerificadorTokenGoogle`
 * se usa en tests, sin red. Mismo patrón que
 * `App\Modules\Identity\Application\Contracts\EnviadorOtp`.
 */
interface VerificadorTokenGoogle
{
    /**
     * @return array{sub: string, email: string, email_verified: bool, name: string, picture: ?string}
     *
     * @throws CredencialGoogleInvalida
     */
    public function verificar(string $idToken): array;
}
