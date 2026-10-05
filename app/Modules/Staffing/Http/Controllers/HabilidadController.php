<?php

namespace App\Modules\Staffing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Habilidad;
use App\Models\Profesional;
use App\Models\ServicioLocal;
use App\Modules\Staffing\Application\HabilidadService;
use App\Modules\Staffing\Http\Requests\CrearHabilidadRequest;
use App\Modules\Staffing\Http\Resources\HabilidadResource;
use App\Support\Auth\ContextoAcceso;
use Illuminate\Http\JsonResponse;

class HabilidadController extends Controller
{
    public function index(Profesional $profesional, HabilidadService $habilidades): JsonResponse
    {
        return $this->ejecutar(fn () => HabilidadResource::collection($habilidades->listarPorProfesional($profesional)));
    }

    public function store(CrearHabilidadRequest $request, Profesional $profesional, HabilidadService $habilidades): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $profesional, $habilidades) {
            $servicio = ServicioLocal::findOrFail($request->validated('servicio_local_id'));
            $contexto = new ContextoAcceso(request()->user());
            abort_unless($contexto->tienePermiso($servicio->local, 'profesionales.habilidades.store'), 403, 'No tienes permiso para esto.');

            return HabilidadResource::make($habilidades->crear($profesional, $request->validated()));
        }, 201);
    }

    public function destroy(Profesional $profesional, Habilidad $habilidad, HabilidadService $habilidades): JsonResponse
    {
        return $this->ejecutar(function () use ($profesional, $habilidad, $habilidades) {
            // Quien administra alguno de sus locales, o el propio profesional.
            $contexto = new ContextoAcceso(request()->user());
            $autorizado = $contexto->tienePermisoSobreProfesional($profesional, 'profesionales.habilidades.destroy')
                || request()->user()->profesional?->id === $profesional->id;

            abort_unless($autorizado, 403, 'No tienes permiso para esto.');

            $habilidades->eliminar($habilidad);

            return response()->json(status: 204);
        });
    }
}
