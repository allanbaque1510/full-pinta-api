<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Local;
use App\Models\ServicioLocal;
use App\Modules\Catalog\Application\ServicioLocalService;
use App\Modules\Catalog\Http\Requests\ActualizarServicioLocalRequest;
use App\Modules\Catalog\Http\Requests\CrearServicioLocalRequest;
use App\Modules\Catalog\Http\Requests\SincronizarTamanosRequest;
use App\Modules\Catalog\Http\Resources\ServicioLocalResource;
use App\Modules\Catalog\Http\Resources\ServicioLocalTamanoResource;
use Illuminate\Http\JsonResponse;

class ServicioLocalController extends Controller
{
    public function index(Local $local, ServicioLocalService $servicios): JsonResponse
    {
        return $this->ejecutar(function () use ($local, $servicios) {
            $this->authorize('ver', $local);

            return ServicioLocalResource::collection($servicios->listar($local));
        });
    }

    public function store(CrearServicioLocalRequest $request, Local $local, ServicioLocalService $servicios): JsonResponse
    {
        return $this->ejecutar(
            fn () => ServicioLocalResource::make($servicios->crear($local, $request->validated())),
            201,
        );
    }

    public function update(ActualizarServicioLocalRequest $request, ServicioLocal $servicio, ServicioLocalService $servicios): JsonResponse
    {
        return $this->ejecutar(fn () => ServicioLocalResource::make($servicios->actualizar($servicio, $request->validated())));
    }

    public function destroy(ServicioLocal $servicio, ServicioLocalService $servicios): JsonResponse
    {
        return $this->ejecutar(function () use ($servicio, $servicios) {
            $this->authorize('gestionarCatalogo', $servicio->local);

            $servicios->desactivar($servicio);

            return response()->json(status: 204);
        });
    }

    public function sincronizarTamanos(SincronizarTamanosRequest $request, ServicioLocal $servicio, ServicioLocalService $servicios): JsonResponse
    {
        return $this->ejecutar(fn () => ServicioLocalTamanoResource::collection(
            $servicios->sincronizarTamanos($servicio, $request->validated('tamanos')),
        ));
    }
}
