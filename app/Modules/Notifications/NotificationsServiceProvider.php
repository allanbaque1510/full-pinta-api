<?php

namespace App\Modules\Notifications;

use App\Modules\ModuleServiceProvider;
use App\Modules\Notifications\Application\Contracts\EnviadorPush;
use App\Modules\Notifications\Application\Contracts\EnviadorWebSocket;
use App\Modules\Notifications\Application\Contracts\EnviadorWhatsApp;
use App\Modules\Notifications\Application\FakeEnviadorPush;
use App\Modules\Notifications\Application\FakeEnviadorWebSocket;
use App\Modules\Notifications\Application\FakeEnviadorWhatsApp;
use App\Modules\Notifications\Application\LogEnviadorPush;
use App\Modules\Notifications\Application\LogEnviadorWebSocket;
use App\Modules\Notifications\Application\LogEnviadorWhatsApp;
use App\Modules\Notifications\Listeners\CancelarNotificacionesDeCitaReagendada;
use App\Modules\Notifications\Listeners\NotificarCitaCancelada;
use App\Modules\Notifications\Listeners\NotificarCitaCreada;
use App\Modules\Notifications\Listeners\NotificarEsperaNotificada;
use App\Modules\Notifications\Listeners\NotificarResenaCreada;
use App\Modules\Notifications\Listeners\NotificarResenaRespondida;
use App\Modules\Notifications\Listeners\NotificarTurnoModificado;
use App\Modules\Reviews\Events\ResenaCreada;
use App\Modules\Reviews\Events\ResenaRespondida;
use App\Modules\Scheduling\Events\CitaCancelada;
use App\Modules\Scheduling\Events\CitaCreada;
use App\Modules\Scheduling\Events\CitaReagendada;
use App\Modules\Scheduling\Events\EsperaNotificada;
use App\Modules\Staffing\Events\TurnoModificado;
use Illuminate\Support\Facades\Event;

class NotificationsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // Sin FCM/Meta/Reverb reales todavía (bloqueadores externos, ver
        // `context/plan-implementacion.md`): log en cualquier entorno normal,
        // doble en memoria en tests — mismo patrón que `EnviadorOtp` (Identity).
        $this->app->singleton(
            EnviadorPush::class,
            fn () => $this->app->environment('testing') ? new FakeEnviadorPush : new LogEnviadorPush,
        );

        $this->app->singleton(
            EnviadorWhatsApp::class,
            fn () => $this->app->environment('testing') ? new FakeEnviadorWhatsApp : new LogEnviadorWhatsApp,
        );

        $this->app->singleton(
            EnviadorWebSocket::class,
            fn () => $this->app->environment('testing') ? new FakeEnviadorWebSocket : new LogEnviadorWebSocket,
        );
    }

    public function boot(): void
    {
        parent::boot();

        // Las reacciones entre módulos van por evento de dominio (§12.3):
        // ningún módulo dueño de una acción llama a Notifications directo.
        Event::listen(CitaCreada::class, NotificarCitaCreada::class);
        Event::listen(CitaCancelada::class, NotificarCitaCancelada::class);
        Event::listen(CitaReagendada::class, CancelarNotificacionesDeCitaReagendada::class);
        Event::listen(TurnoModificado::class, NotificarTurnoModificado::class);
        Event::listen(EsperaNotificada::class, NotificarEsperaNotificada::class);
        Event::listen(ResenaCreada::class, NotificarResenaCreada::class);
        Event::listen(ResenaRespondida::class, NotificarResenaRespondida::class);
    }
}
