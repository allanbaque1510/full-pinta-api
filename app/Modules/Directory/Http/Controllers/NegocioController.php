<?php

namespace App\Modules\Directory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Negocio;
use App\Modules\Directory\Application\NegocioService;
use App\Modules\Directory\Http\Requests\ActualizarNegocioRequest;
use App\Modules\Directory\Http\Requests\CrearNegocioRequest;
use App\Modules\Directory\Http\Resources\NegocioResource;
use Illuminate\Http\JsonResponse;

class NegocioController extends Controller
{
    public function store(CrearNegocioRequest $request, NegocioService $negocios): JsonResponse
    {
        return $this->ejecutar(fn () => NegocioResource::make(
            $negocios->crear($request->user(), $request->validated('nombre_marca'), $request->validated('ruc')),
        ), 201);
    }

    public function show(Negocio $negocio): JsonResponse
    {
        return $this->ejecutar(function () use ($negocio) {
            $this->authorize('ver', $negocio);

            return NegocioResource::make($negocio->load('plan'));
        });
    }

    public function update(ActualizarNegocioRequest $request, Negocio $negocio, NegocioService $negocios): JsonResponse
    {
        return $this->ejecutar(fn () => NegocioResource::make($negocios->actualizar($negocio, $request->validated())));
    }
}
