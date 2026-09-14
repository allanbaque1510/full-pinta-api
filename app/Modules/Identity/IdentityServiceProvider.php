<?php

namespace App\Modules\Identity;

use App\Modules\Identity\Application\Contracts\EnviadorOtp;
use App\Modules\Identity\Application\Contracts\VerificadorTokenGoogle;
use App\Modules\Identity\Application\FakeEnviadorOtp;
use App\Modules\Identity\Application\FakeVerificadorTokenGoogle;
use App\Modules\Identity\Application\GoogleTokeninfoVerificador;
use App\Modules\Identity\Application\LogEnviadorOtp;
use App\Modules\ModuleServiceProvider;

class IdentityServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // El envío real de WhatsApp llega en la Fase 9 del plan. Hasta
        // entonces: log en cualquier entorno normal, y un doble en memoria
        // durante los tests, para que puedan leer el código sin parsear logs.
        $this->app->singleton(
            EnviadorOtp::class,
            fn () => $this->app->environment('testing') ? new FakeEnviadorOtp : new LogEnviadorOtp,
        );

        // Mismo criterio: verificación real contra Google fuera de tests, un
        // doble en memoria dentro (sin red).
        $this->app->singleton(
            VerificadorTokenGoogle::class,
            fn () => $this->app->environment('testing') ? new FakeVerificadorTokenGoogle : new GoogleTokeninfoVerificador,
        );
    }
}
