<?php

namespace App\Modules\Staffing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Local;
use App\Models\Profesional;
use App\Models\TurnoFecha;
use App\Modules\Staffing\Application\TurnoFechaService;
use App\Modules\Staffing\Http\Requests\CrearTurnoFechaRequest;
use App\Modules\Staffing\Http\Resources\TurnoFechaResource;
use Illuminate\Http\JsonResponse;

class TurnoFechaController extends Controller
{
    public function index(Profesional $profesional, TurnoFechaService $turnoFechas): JsonResponse
    {
        return $this->ejecutar(function () use ($profesional, $turnoFechas) {
            $this->authorize('ver', $profesional);

            return TurnoFechaResource::collection($turnoFechas->listarPorProfesional($profesional));
        });
    }

    public function store(CrearTurnoFechaRequest $request, Profesional $profesional, TurnoFechaService $turnoFechas): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $profesional, $turnoFechas) {
            $local = Local::findOrFail($request->validated('local_id'));

            return TurnoFechaResource::make($turnoFechas->crear($profesional, $local, $request->validated()));
        }, 201);
    }

    public function destroy(Profesional $profesional, TurnoFecha $turnoFecha, TurnoFechaService $turnoFechas): JsonResponse
    {
        return $this->ejecutar(function () use ($turnoFecha, $turnoFechas) {
            $this->authorize('actualizar', $turnoFecha->profesional);

            $turnoFechas->eliminar($turnoFecha);

            return response()->json(status: 204);
        });
    }
}
