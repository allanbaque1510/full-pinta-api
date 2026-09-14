<?php

namespace App\Modules\Scheduling\Jobs;

use App\Models\DisponibilidadDia;
use App\Models\Local;
use App\Modules\Scheduling\Application\DisponibilidadService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Reconstruye la proyección `disponibilidad_dia` (§4.7, §8, §12.5) para un
 * (local, fecha): read-model grueso que la búsqueda consulta para el filtro
 * "disponible hoy" — correr el motor de slots por cada local del resultado,
 * en cada scroll, es insostenible.
 *
 * Se despacha desde `InvalidarCacheDisponibilidad` en la cola `proyecciones`.
 */
class ReconstruirDisponibilidadDia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Local $local,
        public CarbonImmutable $fecha,
    ) {}

    public function handle(DisponibilidadService $disponibilidad): void
    {
        $resumen = $disponibilidad->resumenDia($this->local, $this->fecha);

        DisponibilidadDia::updateOrCreate(
            ['local_id' => $this->local->id, 'fecha' => $this->fecha->toDateString()],
            [
                // `slots_libres` es smallint en Postgres.
                'slots_libres' => min($resumen['slots_libres'], 32767),
                'primer_slot' => $resumen['primer_slot']?->format('H:i:s'),
                'ultimo_slot' => $resumen['ultimo_slot']?->format('H:i:s'),
                'recalculado_at' => now(),
            ],
        );
    }
}
