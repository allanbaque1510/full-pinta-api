<?php

namespace App\Modules\Identity\Application\Contracts;

/**
 * Puerto de envío del código OTP.
 *
 * La especificación dice que el OTP viaja por WhatsApp Cloud API, con SMS como
 * fallback (§11.2, §11.3) — eso se conecta en la Fase 9 de
 * `context/plan-implementacion.md`. Hasta entonces se usa una implementación
 * de registro (`LogEnviadorOtp`) para no bloquear el resto de Identity, y una
 * de prueba (`FakeEnviadorOtp`) para los tests.
 *
 * El código nunca se devuelve en la respuesta HTTP: por eso este puerto existe
 * en primer lugar, para que el caso de uso no necesite saber cómo se entrega.
 */
interface EnviadorOtp
{
    public function enviar(string $telefono, string $codigo): void;
}
