<?php

namespace App\Modules\Scheduling\Jobs;

use App\Models\Cita;
use App\Modules\Scheduling\Application\Transiciones\ExpirarHold;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * "No confiar en que el job corrió" (§5.4) es la razón de que el motor de
 * disponibilidad filtre `expira_at` en cada consulta — pero el job igual
 * tiene que existir para que los holds vencidos no se queden `reservada`
 * para siempre y bloqueando lecturas directas de `cita`. Cola `critica`,
 * cada minuto (§6.8) — agendado en `bootstrap/app.php`.
 */
class ExpirarHoldsVencidos implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ExpirarHold $expirar): void
    {
        Cita::query()->holdsVencidos()->get()->each($expirar);
    }
}
