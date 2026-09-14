<?php

namespace App\Modules\Billing\Application;

use App\Models\Cobro;
use App\Modules\Billing\Application\Contracts\EmisorComprobanteSri;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Implementación provisional: escribe al log y devuelve una clave de acceso
 * falsa en vez de emitir un comprobante real ante el SRI. NUNCA usar en
 * producción. Ver docblock de `EmisorComprobanteSri`.
 */
class LogEmisorComprobanteSri implements EmisorComprobanteSri
{
    public function emitir(Cobro $cobro): string
    {
        Log::info('[SRI-PROVISIONAL] Emisión real de comprobante electrónico aún no implementada.', [
            'cobro_id' => $cobro->id, 'monto' => $cobro->monto,
        ]);

        return 'PROVISIONAL-'.Str::upper(Str::random(16));
    }
}
