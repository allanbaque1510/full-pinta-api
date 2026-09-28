<?php

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Application\Contracts\EnviadorCodigoEmail;

/**
 * Doble de prueba: guarda el último código enviado por correo en memoria en
 * vez de mandarlo a ningún lado. Se enlaza automáticamente en el entorno
 * `testing` (ver `IdentityServiceProvider::register()`), mismo patrón que
 * `FakeEnviadorOtp`.
 */
class FakeEnviadorCodigoEmail implements EnviadorCodigoEmail
{
    /** @var array<string,string> */
    private static array $codigosPorEmail = [];

    public function enviar(string $email, string $codigo): void
    {
        self::$codigosPorEmail[$email] = $codigo;
    }

    public static function ultimoCodigoPara(string $email): ?string
    {
        return self::$codigosPorEmail[$email] ?? null;
    }

    /** Los tests no comparten estado: llamar en `setUp()` o entre casos. */
    public static function reset(): void
    {
        self::$codigosPorEmail = [];
    }
}
