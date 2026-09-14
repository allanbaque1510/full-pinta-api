<?php

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Application\Contracts\EnviadorOtp;

/**
 * Doble de prueba: guarda el último código enviado por teléfono en memoria en
 * vez de mandarlo a ningún lado. Se enlaza automáticamente en el entorno
 * `testing` (ver `IdentityServiceProvider::register()`), así los tests pueden
 * leer el código sin depender de WhatsApp ni parsear logs.
 */
class FakeEnviadorOtp implements EnviadorOtp
{
    /** @var array<string,string> */
    private static array $codigosPorTelefono = [];

    public function enviar(string $telefono, string $codigo): void
    {
        self::$codigosPorTelefono[$telefono] = $codigo;
    }

    public static function ultimoCodigoPara(string $telefono): ?string
    {
        return self::$codigosPorTelefono[$telefono] ?? null;
    }

    /** Los tests no comparten estado: llamar en `setUp()` o entre casos. */
    public static function reset(): void
    {
        self::$codigosPorTelefono = [];
    }
}
