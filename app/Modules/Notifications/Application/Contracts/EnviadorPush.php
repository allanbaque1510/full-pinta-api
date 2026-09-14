<?php

namespace App\Modules\Notifications\Application\Contracts;

use App\Models\DeviceToken;

/**
 * Puerto de envío por push (FCM, §11.4).
 *
 * Sin proyecto de Firebase todavía (bloqueador externo, ver
 * `context/plan-implementacion.md`) — hasta que exista, `LogEnviadorPush`
 * registra en el log y `FakeEnviadorPush` en memoria para los tests. Mismo
 * patrón que `App\Modules\Identity\Application\Contracts\EnviadorOtp`.
 */
interface EnviadorPush
{
    /**
     * @param  array<string,mixed>  $payload
     * @return string|null el id del mensaje que devuelva el proveedor (`proveedor_id`)
     */
    public function enviar(DeviceToken $token, array $payload): ?string;
}
