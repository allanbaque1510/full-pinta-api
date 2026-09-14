<?php

namespace App\Modules\Scheduling\Application\Transiciones;

use App\Models\Cita;
use App\Models\Usuario;
use App\Modules\Scheduling\Application\CitaService;
use App\Modules\Scheduling\Application\Transiciones\Concerns\RegistraEventoCita;
use App\Modules\Scheduling\Events\CitaReagendada;
use Illuminate\Support\Facades\DB;

/**
 * Reagendar NO es cancelar + crear (§4.7): eso le contaría una cancelación al
 * cliente que sí avisó y pierde la trazabilidad. Crea una cita nueva
 * (mismo camino que `CitaService::reservar()`, incluido el constraint
 * `EXCLUDE`) enlazada por `reagendada_de_id`, y dEja la vieja en `reagendada`
 * — un estado terminal que NO pasa por `cancelada_*` ni toca los contadores
 * de confiabilidad del cliente (§6: "no penaliza").
 */
final readonly class ReagendarCita
{
    use RegistraEventoCita;

    public function __construct(private CitaService $citas) {}

    /**
     * @param  array{profesional_id: string, servicios: array<int,string>, inicio: string, recurso_id?: ?string}  $datosNuevaCita
     */
    public function __invoke(Cita $citaVieja, Usuario $actor, array $datosNuevaCita): Cita
    {
        if ($citaVieja->esTerminal()) {
            throw_validacion('Esta cita ya no se puede reagendar.', 'estado');
        }

        $citaVieja->loadMissing('cliente', 'local');
        $estadoAnterior = $citaVieja->estado;

        $citaNueva = DB::transaction(function () use ($citaVieja, $datosNuevaCita) {
            $nueva = $this->citas->reservar(
                $citaVieja->local,
                $citaVieja->cliente,
                [...$datosNuevaCita, 'reagendada_de_id' => $citaVieja->id],
            );

            $citaVieja->update(['estado' => 'reagendada']);

            return $nueva;
        });

        $this->registrarEvento($citaVieja, $estadoAnterior, 'reagendada', $actor, ['cita_nueva_id' => $citaNueva->id]);

        CitaReagendada::dispatch($citaVieja->id, $citaNueva->id);

        return $citaNueva;
    }
}
