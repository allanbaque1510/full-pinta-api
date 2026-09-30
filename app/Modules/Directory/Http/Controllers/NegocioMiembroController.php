<?php

namespace App\Modules\Directory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Negocio;
use App\Models\NegocioMiembro;
use App\Modules\Directory\Application\NegocioMiembroService;
use App\Modules\Directory\Http\Requests\AgregarNegocioMiembroRequest;
use App\Modules\Directory\Http\Resources\NegocioMiembroResource;
use Illuminate\Http\JsonResponse;

/**
 * Acceso real a la app para un negocio — admin o recepción (§3.2, §4.4).
 * Distinto de `ProfesionalController`: ese es la ficha de trabajo de quien
 * atiende, este es el login/permisos de quien administra.
 */
class NegocioMiembroController extends Controller
{
    public function index(Negocio $negocio, NegocioMiembroService $miembros): JsonResponse
    {
        return $this->ejecutar(function () use ($negocio, $miembros) {
            $this->authorize('gestionarMiembros', $negocio);

            return NegocioMiembroResource::collection($miembros->listar($negocio));
        });
    }

    public function store(AgregarNegocioMiembroRequest $request, Negocio $negocio, NegocioMiembroService $miembros): JsonResponse
    {
        return $this->ejecutar(fn () => NegocioMiembroResource::make($miembros->agregar(
            $negocio,
            $request->validated('telefono'),
            $request->validated('rol'),
            $request->validated('local_id'),
        )), 201);
    }

    public function terminar(NegocioMiembro $miembro, NegocioMiembroService $miembros): JsonResponse
    {
        return $this->ejecutar(function () use ($miembro, $miembros) {
            $miembro->loadMissing('negocio');
            $this->authorize('gestionarMiembros', $miembro->negocio);

            return NegocioMiembroResource::make($miembros->terminar($miembro));
        });
    }
}
