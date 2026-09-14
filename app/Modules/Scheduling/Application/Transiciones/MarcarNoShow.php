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
 * `confirmada|en_curso` → `no_show` (§6) — métrica clave para el local y para
 * el score del cliente (§5.6). Al 3er no-show, `requiere_confirmacion = true`
 * (columna que ya existe para esto, §4.3) — desde ahí `CitaService` no le da
 * hold sin confirmar.
 */
final readonly class MarcarNoShow
{
    use RegistraEventoCita;

    private const NO_SHOWS_PARA_REQUERIR_CONFIRMACION = 3;

    public function __construct(private EsperaService $esperas) {}

    public function __invoke(Cita $cita, Usuario $actor): Cita
    {
        if (! in_array($cita->estado, ['confirmada', 'en_curso'], true)) {
            throw_validacion('Solo se puede marcar no-show una cita "confirmada" o "en_curso".', 'estado');
        }

        $estadoAnterior = $cita->estado;

        DB::transaction(function () use ($cita) {
            $cita->update(['estado' => 'no_show']);

            $clientePerfil = ClientePerfil::where('usuario_id', $cita->cliente_id)->first();

            if ($clientePerfil !== null) {
                $clientePerfil->increment('no_shows');

                if ($clientePerfil->no_shows >= self::NO_SHOWS_PARA_REQUERIR_CONFIRMACION) {
                    $clientePerfil->update(['requiere_confirmacion' => true]);
                }
            }
        });

        $this->registrarEvento($cita, $estadoAnterior, 'no_show', $actor);

        CitaCancelada::dispatch($cita->id, 'no_show');

        $this->esperas->buscarCandidatas($cita);

        return $cita;
    }
}
