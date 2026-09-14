<?php

namespace App\Modules\Scheduling\Application\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Camino optimista de agendamiento (§5.3): se intenta el `INSERT` y se
 * captura la violación del constraint `EXCLUDE`, en vez de bloquear con
 * `SELECT ... FOR UPDATE`. Cero locks, correcto bajo cualquier concurrencia.
 */
class SlotYaOcupado extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ese horario acaba de ocuparse. Elige otro.');
    }

    public function render(Request $request): JsonResponse
    {
        return response_error(409, 'slot_ya_ocupado', $this->getMessage());
    }
}
