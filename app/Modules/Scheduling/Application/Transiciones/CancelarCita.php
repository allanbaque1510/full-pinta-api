<?php

namespace App\Modules\Scheduling\Application\Transiciones;

use App\Models\Cita;
use App\Models\ClientePerfil;
use App\Models\Usuario;
use App\Modules\Scheduling\Application\EsperaService;
use App\Modules\Scheduling\Application\Transiciones\Concerns\RegistraEventoCita;
use App\Modules\Scheduling\Events\CitaCancelada;
use Illuminate\Support\Facades\DB;

/**
 * `reservada|confirmada|en_curso` → `cancelada_cliente` o `cancelada_local`
 * (§6), según quién cancela — no hay dos endpoints, se resuelve comparando
 * `cita->cliente_id` contra el actor.
 *
 * "Cancelada_tarde" del §5.6 NO es un `estado` nuevo (el §6 no lo lista): es
 * una clasificación derivada. Si cancela el cliente dentro de la ventana de
 * `local.politica_cancelacion_horas`, el estado sigue siendo
 * `cancelada_cliente`, pero se incrementa `cliente_perfil.cancelaciones_tardias`
 * (columna que ya existe para exactamente esto) y se anota `tardia: true` en
 * el evento — sin inventar esquema nuevo.
 */
final readonly class CancelarCita
{
    use RegistraEventoCita;

    public function __construct(private EsperaService $esperas) {}

    public function __invoke(Cita $cita, Usuario $actor, ?string $motivo = null): Cita
    {
        if ($cita->esTerminal()) {
            throw_validacion('Esta cita ya no se puede cancelar.', 'estado');
        }

        $esCliente = $actor->id === $cita->cliente_id;
        $estadoNuevo = $esCliente ? 'cancelada_cliente' : 'cancelada_local';
        $estadoAnterior = $cita->estado;
        $tardia = false;

        if ($esCliente) {
            $cita->loadMissing('local');
            $tardia = now()->diffInHours($cita->inicio, absolute: true) < $cita->local->politica_cancelacion_horas;
        }

        DB::transaction(function () use ($cita, $estadoNuevo, $esCliente, $tardia) {
            $cita->update(['estado' => $estadoNuevo, 'cancelada_at' => now()]);

            if ($esCliente && $tardia) {
                ClientePerfil::where('usuario_id', $cita->cliente_id)->increment('cancelaciones_tardias');
            }
        });

        $this->registrarEvento($cita, $estadoAnterior, $estadoNuevo, $actor, array_filter([
            'motivo' => $motivo,
            'tardia' => $esCliente ? $tardia : null,
        ], fn ($v) => $v !== null));

        CitaCancelada::dispatch($cita->id, $estadoNuevo, $tardia);

        $this->esperas->buscarCandidatas($cita);

        return $cita;
    }
}
