<?php

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Application\Contracts\VerificadorTokenGoogle;
use App\Modules\Identity\Application\Exceptions\CredencialGoogleInvalida;

/**
 * Doble de prueba: los tests fijan a mano las claims que un `id_token` dado
 * "resuelve", sin llamar a Google. Enlazado automáticamente en el entorno
 * `testing` (ver `IdentityServiceProvider::register()`), mismo criterio que
 * `FakeEnviadorOtp`.
 */
class FakeVerificadorTokenGoogle implements VerificadorTokenGoogle
{
    /** @var array<string, array{sub:string, email:string, email_verified:bool, name:string, picture:?string}> */
    private static array $claimsPorToken = [];

    public function verificar(string $idToken): array
    {
        return self::$claimsPorToken[$idToken] ?? throw new CredencialGoogleInvalida;
    }

    /**
     * @param  array{sub:string, email:string, email_verified?:bool, name:string, picture?:?string}  $claims
     */
    public static function registrarToken(string $idToken, array $claims): void
    {
        self::$claimsPorToken[$idToken] = [
            'sub' => $claims['sub'],
            'email' => $claims['email'],
            'email_verified' => $claims['email_verified'] ?? true,
            'name' => $claims['name'],
            'picture' => $claims['picture'] ?? null,
        ];
    }

    public static function reset(): void
    {
        self::$claimsPorToken = [];
    }
}
