<?php

namespace App\Modules\Billing\Application;

use App\Models\Cobro;
use App\Modules\Billing\Application\Contracts\EmisorComprobanteSri;
use Illuminate\Support\Str;

/**
 * Doble de prueba de `EmisorComprobanteSri`, mismo criterio que
 * `App\Modules\Notifications\Application\FakeEnviadorWhatsApp`.
 */
class FakeEmisorComprobanteSri implements EmisorComprobanteSri
{
    /** @var list<string> */
    private static array $emitidos = [];

    public function emitir(Cobro $cobro): string
    {
        $clave = 'FAKE-'.Str::upper(Str::random(16));
        self::$emitidos[] = $clave;

        return $clave;
    }

    /** @return list<string> */
    public static function emitidos(): array
    {
        return self::$emitidos;
    }

    public static function reset(): void
    {
        self::$emitidos = [];
    }
}
