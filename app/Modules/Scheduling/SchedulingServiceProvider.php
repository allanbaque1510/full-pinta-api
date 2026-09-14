<?php

namespace App\Modules\Scheduling;

use App\Modules\ModuleServiceProvider;
use App\Modules\Scheduling\Listeners\InvalidarCacheDisponibilidad;
use App\Modules\Staffing\Events\ExcepcionModificada;
use App\Modules\Staffing\Events\TurnoModificado;
use Illuminate\Support\Facades\Event;

class SchedulingServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // Scheduling escucha a Staffing, nunca al revés (§12.3) — la
        // invalidación de la caché de disponibilidad reacciona por evento.
        Event::listen(TurnoModificado::class, [InvalidarCacheDisponibilidad::class, 'handleTurnoModificado']);
        Event::listen(ExcepcionModificada::class, [InvalidarCacheDisponibilidad::class, 'handleExcepcionModificada']);
    }
}
