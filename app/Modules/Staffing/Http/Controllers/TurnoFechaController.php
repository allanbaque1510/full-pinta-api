<?php

namespace App\Modules\Staffing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Local;
use App\Models\Profesional;
use App\Models\TurnoFecha;
use App\Modules\Staffing\Application\TurnoFechaService;
use App\Modules\Staffing\Http\Requests\CrearTurnoFechaRequest;
use App\Modules\Staffing\Http\Resources\TurnoFechaResource;
use App\Support\Auth\ContextoAcceso;
use Illuminate\Http\JsonResponse;

class TurnoFechaController extends Controller
{
    public function index(Profesional $profesional, TurnoFechaService $turnoFechas): JsonResponse
    {
        return $this->ejecutar(fn () => TurnoFechaResource::collection($turnoFechas->listarPorProfesional($profesional)));
    }

    public function store(CrearTurnoFechaRequest $request, Profesional $profesional, TurnoFechaService $turnoFechas): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $profesional, $turnoFechas) {
            $local = Local::findOrFail($request->validated('local_id'));
            $contexto = new ContextoAcceso(request()->user());

            abort_unless($contexto->tienePermiso($local, 'profesionales.turno-fechas.store'), 403, 'No tienes permiso para esto.');

            return TurnoFechaResource::make($turnoFechas->crear($profesional, $local, $request->validated()));
        }, 201);
    }

    public function destroy(Profesional $profesional, TurnoFecha $turnoFecha, TurnoFechaService $turnoFechas): JsonResponse
    {
        return $this->ejecutar(function () use ($profesional, $turnoFecha, $turnoFechas) {
            $contexto = new ContextoAcceso(request()->user());
            $autorizado = $contexto->tienePermisoSobreProfesional($profesional, 'profesionales.turno-fechas.destroy')
                || request()->user()->profesional?->id === $profesional->id;

            abort_unless($autorizado, 403, 'No tienes permiso para esto.');

            $turnoFechas->eliminar($turnoFecha);

            return response()->json(status: 204);
        });
    }
}
