<?php

namespace App\Modules\Staffing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Habilidad;
use App\Models\Profesional;
use App\Modules\Staffing\Application\HabilidadService;
use App\Modules\Staffing\Http\Requests\CrearHabilidadRequest;
use App\Modules\Staffing\Http\Resources\HabilidadResource;
use Illuminate\Http\JsonResponse;

class HabilidadController extends Controller
{
    public function index(Profesional $profesional, HabilidadService $habilidades): JsonResponse
    {
        return $this->ejecutar(function () use ($profesional, $habilidades) {
            $this->authorize('ver', $profesional);

            return HabilidadResource::collection($habilidades->listarPorProfesional($profesional));
        });
    }

    public function store(CrearHabilidadRequest $request, Profesional $profesional, HabilidadService $habilidades): JsonResponse
    {
        return $this->ejecutar(fn () => HabilidadResource::make($habilidades->crear($profesional, $request->validated())), 201);
    }

    public function destroy(Profesional $profesional, Habilidad $habilidad, HabilidadService $habilidades): JsonResponse
    {
        return $this->ejecutar(function () use ($habilidad, $habilidades) {
            $this->authorize('actualizar', $habilidad->profesional);

            $habilidades->eliminar($habilidad);

            return response()->json(status: 204);
        });
    }
}
