<?php

namespace App\Modules\Notifications\Application;

use App\Models\DeviceToken;
use App\Models\Usuario;

/**
 * Ciclo de vida del token de dispositivo (§11.7, §11.9): escribir en cuatro
 * momentos (login, permiso aceptado, `onTokenRefresh`, reinstalación) y
 * borrar al cerrar sesión — todos resueltos por el mismo `registrar()`
 * (upsert por `token`) y `eliminar()`.
 */
final readonly class DeviceTokenService
{
    /**
     * @param  array{token: string, plataforma: string, apns_token?: ?string, app_version?: ?string}  $datos
     */
    public function registrar(Usuario $usuario, array $datos): DeviceToken
    {
        return DeviceToken::updateOrCreate(
            ['token' => $datos['token']],
            [
                'usuario_id' => $usuario->id,
                'plataforma' => $datos['plataforma'],
                'apns_token' => $datos['apns_token'] ?? null,
                'app_version' => $datos['app_version'] ?? null,
                'activo' => true,
                'ultimo_uso_at' => now(),
            ],
        );
    }

    public function eliminar(DeviceToken $deviceToken): void
    {
        $deviceToken->delete();
    }
}
