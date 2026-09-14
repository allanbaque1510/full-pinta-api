<?php

namespace App\Modules\Directory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\HorarioLocal;
use App\Models\Local;
use App\Modules\Directory\Application\HorarioLocalService;
use App\Modules\Directory\Http\Requests\ActualizarHorarioRequest;
use App\Modules\Directory\Http\Requests\CrearHorarioRequest;
use App\Modules\Directory\Http\Resources\HorarioLocalResource;
use Illuminate\Http\JsonResponse;

class HorarioLocalController extends Controller
{
    public function index(Local $local, HorarioLocalService $horarios): JsonResponse
    {
        return $this->ejecutar(function () use ($local, $horarios) {
            $this->authorize('ver', $local);

            return HorarioLocalResource::collection($horarios->listar($local));
        });
    }

    public function store(CrearHorarioRequest $request, Local $local, HorarioLocalService $horarios): JsonResponse
    {
        return $this->ejecutar(
            fn () => HorarioLocalResource::make($horarios->crear($local, $request->validated())),
            201,
        );
    }

    public function update(ActualizarHorarioRequest $request, HorarioLocal $horario, HorarioLocalService $horarios): JsonResponse
    {
        return $this->ejecutar(fn () => HorarioLocalResource::make($horarios->actualizar($horario, $request->validated())));
    }

    public function destroy(HorarioLocal $horario, HorarioLocalService $horarios): JsonResponse
    {
        return $this->ejecutar(function () use ($horario, $horarios) {
            $this->authorize('gestionarCatalogo', $horario->local);

            $horarios->eliminar($horario);

            return response()->json(status: 204);
        });
    }
}
