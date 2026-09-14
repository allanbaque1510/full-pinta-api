<?php

namespace App\Modules\Scheduling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Local;
use App\Modules\Scheduling\Application\DisponibilidadService;
use App\Modules\Scheduling\Http\Requests\BuscarDisponibilidadRequest;
use App\Modules\Scheduling\Http\Resources\SlotResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * Motor de disponibilidad (§5). Público, sin autenticación — el cliente
 * necesita ver horarios antes de tener cuenta, igual que el catálogo.
 */
class DisponibilidadController extends Controller
{
    public function index(BuscarDisponibilidadRequest $request, Local $local, DisponibilidadService $disponibilidad): JsonResponse
    {
        return $this->ejecutar(fn () => SlotResource::collection($disponibilidad->slots(
            $local,
            CarbonImmutable::parse($request->validated('fecha'))->startOfDay(),
            $request->validated('servicios'),
            $request->validated('profesional_id'),
        )));
    }
}
