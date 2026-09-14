<?php

namespace App\Modules\Staffing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Asignacion;
use App\Models\Turno;
use App\Modules\Staffing\Application\TurnoService;
use App\Modules\Staffing\Http\Requests\ActualizarTurnoRequest;
use App\Modules\Staffing\Http\Requests\CrearTurnoRequest;
use App\Modules\Staffing\Http\Resources\TurnoResource;
use Illuminate\Http\JsonResponse;

class TurnoController extends Controller
{
    public function index(Asignacion $asignacion): JsonResponse
    {
        return $this->ejecutar(function () use ($asignacion) {
            $this->authorize('ver', $asignacion->local);

            return TurnoResource::collection($asignacion->turnos);
        });
    }

    public function store(CrearTurnoRequest $request, Asignacion $asignacion, TurnoService $turnos): JsonResponse
    {
        return $this->ejecutar(fn () => TurnoResource::make($turnos->crear($asignacion, $request->validated())), 201);
    }

    public function update(ActualizarTurnoRequest $request, Turno $turno, TurnoService $turnos): JsonResponse
    {
        return $this->ejecutar(fn () => TurnoResource::make($turnos->actualizar($turno, $request->validated())));
    }

    public function destroy(Turno $turno, TurnoService $turnos): JsonResponse
    {
        return $this->ejecutar(function () use ($turno, $turnos) {
            $this->authorize('gestionarCatalogo', $turno->local);

            $turnos->eliminar($turno);

            return response()->json(status: 204);
        });
    }
}
