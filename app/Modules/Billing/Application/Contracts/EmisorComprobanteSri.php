<?php

namespace App\Modules\Billing\Application\Contracts;

use App\Models\Cobro;

/**
 * Puerto de emisión del comprobante electrónico (§10.3, §10.4).
 *
 * Sin RUC de facturación, firma electrónica ni proveedor contratado todavía
 * (bloqueador externo, ver `context/plan-implementacion.md`) — hasta que
 * exista, `LogEmisorComprobanteSri` registra en el log y
 * `FakeEmisorComprobanteSri` en memoria para los tests. Mismo patrón que
 * `App\Modules\Notifications\Application\Contracts\EnviadorWhatsApp`.
 */
interface EmisorComprobanteSri
{
    /** @return string la clave de acceso de la factura electrónica */
    public function emitir(Cobro $cobro): string;
}
