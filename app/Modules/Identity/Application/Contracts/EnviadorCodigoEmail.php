<?php

namespace App\Modules\Identity\Application\Contracts;

/**
 * Puerto de envío de un código de un solo uso por correo — verificación de
 * propiedad del email y recuperación de contraseña (§13.1, revisión de base
 * de datos, 2026-09-29).
 *
 * Sin proveedor de correo saliente contratado todavía (bloqueador externo,
 * ver `context/plan-implementacion.md`) — hasta entonces, `LogEnviadorCodigoEmail`/
 * `FakeEnviadorCodigoEmail`. Mismo patrón que `EnviadorOtp`.
 */
interface EnviadorCodigoEmail
{
    public function enviar(string $email, string $codigo): void;
}
