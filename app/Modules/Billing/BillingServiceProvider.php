<?php

namespace App\Modules\Billing;

use App\Modules\Billing\Application\Contracts\EmisorComprobanteSri;
use App\Modules\Billing\Application\FakeEmisorComprobanteSri;
use App\Modules\Billing\Application\LogEmisorComprobanteSri;
use App\Modules\ModuleServiceProvider;

class BillingServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // Sin proveedor de facturación electrónica del SRI todavía
        // (bloqueador externo, ver `context/plan-implementacion.md`): log en
        // cualquier entorno normal, doble en memoria en tests — mismo patrón
        // que `EnviadorOtp`/`EnviadorWhatsApp`.
        $this->app->singleton(
            EmisorComprobanteSri::class,
            fn () => $this->app->environment('testing') ? new FakeEmisorComprobanteSri : new LogEmisorComprobanteSri,
        );
    }
}
