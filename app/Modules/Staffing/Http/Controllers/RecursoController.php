<?php

namespace App\Modules\Staffing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Local;
use App\Models\Recurso;
use App\Modules\Staffing\Application\RecursoService;
use App\Modules\Staffing\Http\Requests\ActualizarRecursoRequest;
use App\Modules\Staffing\Http\Requests\CrearRecursoRequest;
use App\Modules\Staffing\Http\Resources\RecursoResource;
use Illuminate\Http\JsonResponse;

class RecursoController extends Controller
{
    public function index(Local $local, RecursoService $recursos): JsonResponse
    {
        return $this->ejecutar(function () use ($local, $recursos) {
            $this->authorize('ver', $local);

            return RecursoResource::collection($recursos->listar($local));
        });
    }

    public function store(CrearRecursoRequest $request, Local $local, RecursoService $recursos): JsonResponse
    {
        return $this->ejecutar(fn () => RecursoResource::make($recursos->crear($local, $request->validated())), 201);
    }

    public function update(ActualizarRecursoRequest $request, Recurso $recurso, RecursoService $recursos): JsonResponse
    {
        return $this->ejecutar(fn () => RecursoResource::make($recursos->actualizar($recurso, $request->validated())));
    }

    public function destroy(Recurso $recurso, RecursoService $recursos): JsonResponse
    {
        return $this->ejecutar(function () use ($recurso, $recursos) {
            $this->authorize('gestionarCatalogo', $recurso->local);

            $recursos->desactivar($recurso);

            return response()->json(status: 204);
        });
    }
}
