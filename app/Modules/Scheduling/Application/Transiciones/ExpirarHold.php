<?php

namespace App\Modules\Scheduling\Application\Transiciones;

use App\Models\Cita;
use App\Modules\Scheduling\Application\EsperaService;
use App\Modules\Scheduling\Application\Transiciones\Concerns\RegistraEventoCita;
use App\Modules\Scheduling\Events\CitaCancelada;

/**
 * `reservada` → `expirada` (§5.4, §6): el cliente no confirmó el hold antes
 * de `expira_at`. La dispara el job de la cola `critica` (§6.8), nunca un
 * actor humano — por eso no recibe `Usuario $actor`.
 */
final readonly class ExpirarHold
{
    use RegistraEventoCita;

    public function __construct(private EsperaService $esperas) {}

    public function __invoke(Cita $cita): Cita
    {
        if ($cita->estado !== 'reservada' || $cita->expira_at === null || $cita->expira_at->isFuture()) {
            throw_validacion('Esta cita no tiene un hold vencido.', 'estado');
        }

        $cita->update(['estado' => 'expirada']);

        $this->registrarEvento($cita, 'reservada', 'expirada', null);

        CitaCancelada::dispatch($cita->id, 'expirada');

        $this->esperas->buscarCandidatas($cita);

        return $cita;
    }
}
