<?php

namespace App\Modules\Scheduling\Application\Transiciones;

use App\Models\Cita;
use App\Models\ClienteLocal;
use App\Models\Usuario;
use App\Modules\Scheduling\Application\Transiciones\Concerns\RegistraEventoCita;
use App\Modules\Scheduling\Events\CitaCompletada;
use Illuminate\Support\Facades\DB;

/**
 * `en_curso` → `completada` (§6). Solo este estado habilita reseña y cuenta
 * para ranking del local y liquidación de comisiones — por eso es el único
 * momento en que se actualiza `cliente_local` (total de visitas reales, no
 * holds que expiraron o se cancelaron).
 */
final readonly class CompletarCita
{
    use RegistraEventoCita;

    public function __invoke(Cita $cita, ?Usuario $actor, ?float $propina = null): Cita
    {
        if ($cita->estado !== 'en_curso') {
            throw_validacion('Solo se puede completar una cita en estado "en_curso".', 'estado');
        }

        DB::transaction(function () use ($cita, $propina) {
            $cita->update([
                'estado' => 'completada',
                'completada_at' => now(),
                'propina' => $propina ?? $cita->propina,
            ]);

            $clienteLocal = ClienteLocal::firstOrNew([
                'usuario_id' => $cita->cliente_id,
                'local_id' => $cita->local_id,
            ]);
            $clienteLocal->primera_cita_at ??= $cita->completada_at;
            $clienteLocal->ultima_cita_at = $cita->completada_at;
            $clienteLocal->total_citas = ($clienteLocal->total_citas ?? 0) + 1;
            $clienteLocal->save();
        });

        $this->registrarEvento($cita, 'en_curso', 'completada', $actor, $propina !== null ? ['propina' => $propina] : []);

        CitaCompletada::dispatch($cita->id);

        return $cita;
    }
}
