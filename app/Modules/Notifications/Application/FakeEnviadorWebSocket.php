<?php

namespace App\Modules\Notifications\Application;

use App\Modules\Notifications\Application\Contracts\EnviadorWebSocket;

/**
 * Doble de prueba de `EnviadorWebSocket`, mismo criterio que `FakeEnviadorPush`.
 */
class FakeEnviadorWebSocket implements EnviadorWebSocket
{
    /** @var list<array{canal:string, payload:array<string,mixed>}> */
    private static array $emitidos = [];

    public function emitir(string $canal, array $payload): void
    {
        self::$emitidos[] = ['canal' => $canal, 'payload' => $payload];
    }

    /** @return list<array{canal:string, payload:array<string,mixed>}> */
    public static function emitidos(): array
    {
        return self::$emitidos;
    }

    public static function reset(): void
    {
        self::$emitidos = [];
    }
}
