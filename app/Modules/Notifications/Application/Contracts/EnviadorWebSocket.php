<?php

namespace App\Modules\Notifications\Application\Contracts;

/**
 * Puerto de envío por WebSocket (Reverb, §11.10).
 *
 * Sin el paquete `laravel/reverb` instalado todavía (cambiar dependencias
 * requiere aprobación, CLAUDE.md) — hasta entonces,
 * `LogEnviadorWebSocket`/`FakeEnviadorWebSocket`. Solo empuja cambios a
 * pantallas abiertas; nunca participa en la concurrencia de agendamiento
 * (skill `disponibilidad`).
 */
interface EnviadorWebSocket
{
    /** @param  array<string,mixed>  $payload */
    public function emitir(string $canal, array $payload): void;
}
