<?php

namespace App\Modules\Notifications\Application\Contracts;

use App\Models\PlantillaWhatsapp;

/**
 * Puerto de envío por WhatsApp Cloud API (§11.2, §11.3).
 *
 * Sin plantillas aprobadas por Meta todavía (bloqueador externo) — hasta
 * entonces, `LogEnviadorWhatsApp`/`FakeEnviadorWhatsApp`. Mismo patrón que
 * `EnviadorPush`.
 */
interface EnviadorWhatsApp
{
    /**
     * @param  array<string,string>  $variables
     * @return string el id del mensaje en Meta (`proveedor_id`)
     */
    public function enviar(string $telefono, PlantillaWhatsapp $plantilla, array $variables): string;
}
