<?php

namespace App\Modules\Staffing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Asignacion;
use App\Models\Local;
use App\Models\Profesional;
use App\Modules\Staffing\Application\AsignacionService;
use App\Modules\Staffing\Http\Requests\ActualizarAsignacionRequest;
use App\Modules\Staffing\Http\Requests\CrearAsignacionRequest;
use App\Modules\Staffing\Http\Requests\TerminarAsignacionRequest;
use App\Modules\Staffing\Http\Resources\AsignacionResource;
use Illuminate\Http\JsonResponse;

class AsignacionController extends Controller
{
    public function index(Local $local, AsignacionService $asignaciones): JsonResponse
    {
        return $this->ejecutar(function () use ($local, $asignaciones) {
            $this->authorize('ver', $local);

            return AsignacionResource::collection($asignaciones->listarPorLocal($local));
        });
    }

    public function store(CrearAsignacionRequest $request, Local $local, AsignacionService $asignaciones): JsonResponse
    {
        return $this->ejecutar(function () use ($request, $local, $asignaciones) {
            $profesional = Profesional::findOrFail($request->validated('profesional_id'));

            return AsignacionResource::make($asignaciones->crear($local, $profesional, $request->validated()));
        }, 201);
    }

    public function update(ActualizarAsignacionRequest $request, Asignacion $asignacion, AsignacionService $asignaciones): JsonResponse
    {
        return $this->ejecutar(fn () => AsignacionResource::make($asignaciones->actualizar($asignacion, $request->validated())));
    }

    public function terminar(TerminarAsignacionRequest $request, Asignacion $asignacion, AsignacionService $asignaciones): JsonResponse
    {
        return $this->ejecutar(fn () => AsignacionResource::make($asignaciones->terminar($asignacion, $request->validated('hasta'))));
    }
}
