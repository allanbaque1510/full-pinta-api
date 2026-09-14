<?php

namespace App\Modules\Notifications\Application;

use App\Models\DeviceToken;
use App\Modules\Notifications\Application\Contracts\EnviadorPush;
use Illuminate\Support\Str;

/**
 * Doble de prueba: guarda cada envío en memoria en vez de mandarlo a ningún
 * lado. Enlazado automáticamente en el entorno `testing` (ver
 * `NotificationsServiceProvider::register()`).
 */
class FakeEnviadorPush implements EnviadorPush
{
    /** @var list<array{device_token_id:string, payload:array<string,mixed>}> */
    private static array $enviados = [];

    public function enviar(DeviceToken $token, array $payload): ?string
    {
        self::$enviados[] = ['device_token_id' => $token->id, 'payload' => $payload];

        return (string) Str::uuid();
    }

    /** @return list<array{device_token_id:string, payload:array<string,mixed>}> */
    public static function enviados(): array
    {
        return self::$enviados;
    }

    public static function reset(): void
    {
        self::$enviados = [];
    }
}
