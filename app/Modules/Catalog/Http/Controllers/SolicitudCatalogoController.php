<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Local;
use App\Modules\Catalog\Application\SolicitudCatalogoService;
use App\Modules\Catalog\Http\Requests\CrearSolicitudCatalogoRequest;
use App\Modules\Catalog\Http\Resources\SolicitudCatalogoResource;
use Illuminate\Http\JsonResponse;

class SolicitudCatalogoController extends Controller
{
    public function index(Local $local, SolicitudCatalogoService $solicitudes): JsonResponse
    {
        return $this->ejecutar(function () use ($local, $solicitudes) {
            $this->authorize('ver', $local);

            return SolicitudCatalogoResource::collection($solicitudes->listar($local));
        });
    }

    public function store(CrearSolicitudCatalogoRequest $request, Local $local, SolicitudCatalogoService $solicitudes): JsonResponse
    {
        return $this->ejecutar(
            fn () => SolicitudCatalogoResource::make($solicitudes->crear($local, $request->validated())),
            201,
        );
    }
}
